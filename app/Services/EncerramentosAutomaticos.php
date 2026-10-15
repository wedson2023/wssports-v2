<?php

namespace App\Services;

use App\Enums\SituacaoEncerramentoAutomatico;
use App\Events\ApostaAtualizada;
use App\Exceptions\RegraApostaException;
use App\Models\Apostas;
use App\Models\ApostasEncerramentosAutomaticos;
use App\Models\Clientes;
use App\Models\Usuarios;
use App\Support\CodigoAposta;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Encerramento automático por valor-alvo (spec 010, FR-030, FR-031, R-12): programar, alterar,
 * desativar e conferir. A execução fica no EncerramentoApostas.
 */
class EncerramentosAutomaticos
{
    public function __construct(
        private CalculoEncerramento $calculo,
        private ConfiguracoesEncerramento $configuracoes,
        private EncerramentoApostas $encerramento,
    ) {}

    /**
     * Programa (ou troca) o valor-alvo: maior que a oferta atual e no máximo o prêmio potencial.
     *
     * @throws RegraApostaException
     */
    public function programar(Clientes $cliente, ?Usuarios $autor, string $codigo, string $valor_alvo): ApostasEncerramentosAutomaticos
    {
        if (! $this->configuracoes->automatico_habilitado()) {
            throw new RegraApostaException('Encerramento automático indisponível no momento.');
        }

        $valor_alvo = bcadd($valor_alvo, '0', 2);

        $automatico = DB::transaction(function () use ($cliente, $autor, $codigo, $valor_alvo) {
            $aposta = $this->aposta_do_cliente($cliente, $codigo, travar: true);
            $oferta = $this->calculo->oferta($aposta);

            if (! $oferta['disponivel']) {
                throw new RegraApostaException($oferta['motivo'] ?? 'Esta aposta não pode ser encerrada.');
            }

            if (bccomp($valor_alvo, $oferta['oferta'], 2) <= 0) {
                throw new RegraApostaException('O valor-alvo deve ser maior que a oferta atual de '.RegrasAposta::reais($oferta['oferta']).'.');
            }

            if (bccomp($valor_alvo, $aposta->total_a_pagar(), 2) > 0) {
                throw new RegraApostaException('O valor-alvo deve ser no máximo o prêmio potencial de '.RegrasAposta::reais($aposta->total_a_pagar()).'.');
            }

            $this->cancelar_da_aposta($aposta, 'Alterado');

            return ApostasEncerramentosAutomaticos::create([
                'apostas_id' => $aposta->id,
                'clientes_id' => $cliente->id,
                'usuarios_id' => $autor?->id,
                'valor_alvo' => $valor_alvo,
                'situacao' => SituacaoEncerramentoAutomatico::Ativo,
            ]);
        });

        ApostaAtualizada::emitir($automatico->aposta->codigo, 'automatico');

        return $automatico;
    }

    /**
     * Desativa o automático ativo da aposta (FR-031).
     *
     * @return bool se havia um automático ativo
     */
    public function cancelar(Clientes $cliente, string $codigo, string $motivo): bool
    {
        $cancelou = DB::transaction(fn () => $this->cancelar_da_aposta($this->aposta_do_cliente($cliente, $codigo, travar: true), $motivo) > 0);

        if ($cancelou) {
            ApostaAtualizada::emitir(CodigoAposta::normalizar($codigo), 'automatico');
        }

        return $cancelou;
    }

    /**
     * Cancela o automático ativo de uma aposta já travada por quem chama.
     *
     * @return int quantos foram cancelados (0 ou 1)
     */
    public function cancelar_da_aposta(Apostas $aposta, string $motivo): int
    {
        return ApostasEncerramentosAutomaticos::cancelar_ativo($aposta->id, $motivo);
    }

    /**
     * Confere os automáticos ativos (das apostas com palpite nos jogos informados, ou todos) e
     * executa os que alcançaram o alvo. Sem oferta no momento, o automático segue aguardando.
     *
     * @param  list<int>|null  $confrontos_ids
     * @return int quantos foram executados
     */
    public function conferir(?array $confrontos_ids = null): int
    {
        if (! $this->configuracoes->automatico_habilitado()) {
            return 0;
        }

        $ids = ApostasEncerramentosAutomaticos::ativos()
            ->when($confrontos_ids !== null, fn ($consulta) => $consulta->whereIn('apostas_id', DB::table('apostas_palpites')
                ->whereIn('confrontos_id', $confrontos_ids)
                ->whereNull('deleted_at')
                ->select('apostas_id')))
            ->pluck('id');

        $executados = 0;

        foreach ($ids as $id) {
            try {
                $executados += $this->encerramento->executar_automatico((int) $id) !== null ? 1 : 0;
            } catch (Throwable $erro) {
                Log::error("Encerramento automático {$id} não conferido: ".class_basename($erro).' - '.$erro->getMessage());
            }
        }

        return $executados;
    }

    private function aposta_do_cliente(Clientes $cliente, string $codigo, bool $travar = false): Apostas
    {
        $consulta = Apostas::buscar_pelo_codigo($codigo)->where('clientes_id', $cliente->id);
        $aposta = ($travar ? $consulta->lockForUpdate() : $consulta)->first();

        abort_if($aposta === null, 404, 'Aposta não encontrada.');

        return $aposta;
    }
}
