<?php

namespace App\Services;

use App\Enums\Carteira;
use App\Enums\ModoSaque;
use App\Enums\OrigemTransacao;
use App\Enums\SituacaoSaque;
use App\Enums\TipoMeioPagamento;
use App\Exceptions\SaldoInsuficienteException;
use App\Jobs\PagarSaqueMercadoPago;
use App\Models\Clientes;
use App\Models\ClientesConfiguracoes;
use App\Models\ClientesMeiosPagamento;
use App\Models\Configuracoes;
use App\Models\Saques;
use App\Models\Usuarios;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Pedido de saque do cliente (spec 007, FR-035 a FR-040, research R-13): validações na ordem da
 * spec, débito na hora e, no modo automático até o limite, envio ao Mercado Pago.
 */
class SolicitacaoSaques
{
    public function __construct(private SaldoClientes $saldo, private RolloverClientes $rollover) {}

    public function solicitar(Clientes $cliente, string $valor, ?Usuarios $autor, ?string $ip): Saques
    {
        $configuracoes = Configuracoes::atual();
        $limites = $cliente->configuracoes;

        if (! $configuracoes->saques_habilitados) {
            $this->recusar('Saques indisponíveis no momento.');
        }

        if (! $cliente->ativo) {
            $this->recusar('Pagamento pendente ou conta desativada.');
        }

        if ($limites?->bloquear_saque) {
            $this->recusar('Seu saque está bloqueado. Fale com o atendimento.');
        }

        $pix = $this->chave_pix($cliente);
        $faltando = array_keys(array_filter([
            'email' => ! $cliente->email,
            'cpf' => ! $cliente->cpf,
            'pix' => $pix === null,
        ]));

        if ($faltando !== []) {
            throw CriacaoDepositos::cadastro_incompleto('Complete seus dados para sacar.', $faltando);
        }

        $saldo = (string) $cliente->saldo;

        if (bccomp($saldo, '0', 2) < 0) {
            $this->recusar('Seu saldo está negativo por estorno de pagamento.');
        }

        if (bccomp($saldo, $valor, 2) < 0) {
            $this->recusar('Só restam '.RegrasAposta::reais($saldo).' do seu saldo.');
        }

        if (bccomp($valor, (string) $configuracoes->saque_minimo, 2) < 0) {
            $this->recusar('O saque mínimo é de '.RegrasAposta::reais((string) $configuracoes->saque_minimo).'.');
        }

        $this->conferir_limites_do_dia($cliente, $valor, $limites);

        $falta_rollover = $this->rollover->restante_deposito($cliente->id);

        if (bccomp($falta_rollover, '0', 2) > 0) {
            $this->recusar('Só é possível sacar depois de cumprir o rollover do depósito. Faltam '.RegrasAposta::reais($falta_rollover).'.');
        }

        $automatico = $configuracoes->modo_saque === ModoSaque::Automático
            && bccomp($valor, (string) $configuracoes->saque_automatico_maximo, 2) <= 0;

        return DB::transaction(function () use ($cliente, $valor, $autor, $ip, $pix, $automatico) {
            try {
                // o bloqueio da linha do cliente no SaldoClientes impede dois saques do mesmo saldo
                $transacao = $this->saldo->debitar($cliente, Carteira::Saldo, $valor, OrigemTransacao::Saque, $autor, null, 'Saque via Pix');
            } catch (SaldoInsuficienteException) {
                $this->recusar('Só restam '.RegrasAposta::reais((string) $cliente->refresh()->saldo).' do seu saldo.');
            }

            $saque = Saques::create([
                'codigo' => (string) Str::uuid(),
                'clientes_id' => $cliente->id,
                'valor' => $valor,
                'pix_tipo_chave' => $pix->pix_tipo_chave,
                'pix_chave' => $pix->pix_chave,
                'pix_nome_titular' => $pix->pix_nome_titular,
                'modo' => $automatico ? ModoSaque::Automático : ModoSaque::Manual,
                'situacao' => SituacaoSaque::Pendente,
                'clientes_transacoes_id' => $transacao->id,
                'usuarios_id' => $autor?->id,
                'ip' => $ip,
            ]);

            $transacao->forceFill(['referencia_id' => $saque->id])->save();

            if ($automatico) {
                PagarSaqueMercadoPago::dispatch($saque->id)->afterCommit();
            }

            return $saque;
        });
    }

    /**
     * Quantidade e soma dos saques do dia (horário de Brasília), sem contar os recusados.
     */
    private function conferir_limites_do_dia(Clientes $cliente, string $valor, ?ClientesConfiguracoes $limites): void
    {
        if ($limites === null) {
            return;
        }

        $inicio = now('America/Sao_Paulo')->startOfDay()->utc();
        $do_dia = Saques::where('clientes_id', $cliente->id)
            ->where('situacao', '!=', SituacaoSaque::Recusado)
            ->where('created_at', '>=', $inicio)
            ->get(['valor']);

        if ($do_dia->count() >= $limites->quantidade_maxima_saques_diaria) {
            $this->recusar("Só é possível realizar {$limites->quantidade_maxima_saques_diaria} saque(s) por dia.");
        }

        $somado = $do_dia->reduce(fn (string $total, Saques $saque) => bcadd($total, (string) $saque->valor, 2), '0.00');
        $maximo = (string) $limites->valor_maximo_saque_diario;

        if (bccomp(bcadd($somado, $valor, 2), $maximo, 2) > 0) {
            $resta = bcsub($maximo, $somado, 2);
            $resta = bccomp($resta, '0', 2) > 0 ? $resta : '0.00';

            $this->recusar('O limite de saque diário é de '.RegrasAposta::reais($maximo).'; restam '.RegrasAposta::reais($resta).' hoje.');
        }
    }

    /**
     * Chave Pix de destino: a principal entre as de Pix, ou a mais antiga.
     */
    private function chave_pix(Clientes $cliente): ?ClientesMeiosPagamento
    {
        return $cliente->meios_pagamento()
            ->where('tipo', TipoMeioPagamento::Pix)
            ->whereNotNull('pix_chave')
            ->orderByDesc('principal')
            ->orderBy('id')
            ->first();
    }

    private function recusar(string $mensagem): never
    {
        throw ValidationException::withMessages(['valor' => $mensagem]);
    }
}
