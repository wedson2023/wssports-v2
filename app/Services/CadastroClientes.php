<?php

namespace App\Services;

use App\Enums\CategoriaPromocao;
use App\Enums\OrigemTransacao;
use App\Events\ClienteCadastrado;
use App\Models\Clientes;
use App\Models\ClientesPromocoes;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cadastro do cliente, feito de forma atômica: cliente, configurações (com os valores padrão das
 * colunas) e promoções de primeiro cadastro (quando ele aceita receber promoções).
 */
class CadastroClientes
{
    public function __construct(private SaldoClientes $saldo, private RolloverClientes $rollover) {}

    /**
     * @param  array<string, mixed>  $dados  dados validados do cadastro
     */
    public function cadastrar(array $dados): Clientes
    {
        $aceita_promocao = (bool) ($dados['aceita_promocao'] ?? true);

        try {
            $cliente = DB::transaction(function () use ($dados, $aceita_promocao) {
                $cliente = Clientes::create([...Arr::except($dados, 'aceita_promocao'), 'ativo' => true]);

                // sem tabela padrão: os demais valores vêm do padrão das colunas
                $cliente->configuracoes()->create(['aceita_promocao' => $aceita_promocao]);

                if ($aceita_promocao) {
                    $this->aplicar_promocoes_de_cadastro($cliente);
                }

                return $cliente;
            });
        } catch (UniqueConstraintViolationException) {
            // dois cadastros simultâneos com o mesmo dado único: o segundo cai aqui
            throw ValidationException::withMessages([
                'telefone' => 'O telefone, o CPF ou o e-mail já está cadastrado.',
            ]);
        }

        ClienteCadastrado::dispatch($cliente);

        // recarrega para trazer os saldos gravados (padrão do banco ou creditados pela promoção)
        return $cliente->refresh()->load('configuracoes');
    }

    private function aplicar_promocoes_de_cadastro(Clientes $cliente): void
    {
        $promocoes = ClientesPromocoes::vigentes()
            ->where('categoria', CategoriaPromocao::PrimeiroCadastro)
            ->get();

        foreach ($promocoes as $promocao) {
            $transacao = $this->saldo->creditar(
                $cliente,
                $promocao->modalidade->carteira(),
                (string) $promocao->valor,
                OrigemTransacao::Promoção,
                referencia_id: $promocao->id,
                observacao: "Promoção: {$promocao->nome}",
            );

            // o bônus com rollover passa a ser acompanhado (spec 004, FR-047)
            $this->rollover->criar_bonus($cliente, $transacao, $promocao);
        }
    }
}
