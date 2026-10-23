<?php

namespace App\Services;

use App\Enums\OrigemApuracao;
use App\Enums\SituacaoEncerramento;
use App\Events\ApostaAtualizada;
use App\Models\Apostas;
use App\Models\ApostasEncerramentos;
use App\Models\Clientes;
use App\Models\ClientesAntecipacoes;
use App\Models\Usuarios;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Antecipação de pendentes pelo painel (spec 019, FR-019, research R-06; o "subir créditos" do
 * antigo): credita na hora os prêmios (spec 009) e os encerramentos (spec 010) do cliente que ainda
 * esperam a janela de segurança, pelas mesmas regras de pagamento. A trava da aposta e o valor já
 * pago garantem que nada seja pago duas vezes, mesmo com a rotina da janela rodando ao mesmo tempo.
 */
class AntecipacaoPendentes
{
    public function __construct(
        private readonly LiquidacaoApostas $liquidacao_apostas,
        private readonly LiquidacaoEncerramentos $liquidacao_encerramentos,
    ) {}

    /**
     * Quantidade e valor dos prêmios e encerramentos esperando a janela.
     *
     * @return array{premios: array{quantidade: int, valor: string}, encerramentos: array{quantidade: int, valor: string}, total: string}
     */
    public function pendentes(Clientes $cliente): array
    {
        $premios = ['quantidade' => 0, 'valor' => '0.00'];

        foreach ($this->apostas_pendentes($cliente) as $aposta) {
            $premios['quantidade']++;
            $premios['valor'] = bcadd($premios['valor'], $this->a_creditar($aposta), 2);
        }

        $encerramentos = $this->encerramentos_pendentes($cliente);
        $valor_encerramentos = bcadd((string) $encerramentos->sum(fn (ApostasEncerramentos $e) => $e->valor), '0', 2);

        return [
            'premios' => $premios,
            'encerramentos' => ['quantidade' => $encerramentos->count(), 'valor' => $valor_encerramentos],
            'total' => bcadd($premios['valor'], $valor_encerramentos, 2),
        ];
    }

    /**
     * Credita os pendentes e registra a antecipação; sem nada creditado, recusa.
     *
     * @return array{creditado: string, premios: array{quantidade: int, valor: string}, encerramentos: array{quantidade: int, valor: string}, saldo: string}
     */
    public function antecipar(Clientes $cliente, Usuarios $autor, ?string $ip): array
    {
        $premios = ['quantidade' => 0, 'valor' => '0.00'];
        $encerramentos = ['quantidade' => 0, 'valor' => '0.00'];

        foreach ($this->apostas_pendentes($cliente) as $pendente) {
            $creditado = DB::transaction(function () use ($pendente, $autor) {
                $aposta = Apostas::lockForUpdate()->findOrFail($pendente->id);
                $antes = (string) $aposta->valor_pago;

                $this->liquidacao_apostas->liquidar($aposta, OrigemApuracao::Antecipação, ignorar_janela: true, autor: $autor);

                return bcsub((string) $aposta->valor_pago, $antes, 2);
            });

            // só conta o que de fato foi creditado (a rotina da janela pode ter pago antes)
            if (bccomp($creditado, '0', 2) > 0) {
                $premios['quantidade']++;
                $premios['valor'] = bcadd($premios['valor'], $creditado, 2);
                ApostaAtualizada::emitir($pendente->codigo, 'apuracao');
            }
        }

        foreach ($this->encerramentos_pendentes($cliente) as $encerramento) {
            $codigo = $this->liquidacao_encerramentos->creditar_encerramento($encerramento->id, $autor);

            if ($codigo !== null) {
                $encerramentos['quantidade']++;
                $encerramentos['valor'] = bcadd($encerramentos['valor'], (string) $encerramento->valor, 2);
                ApostaAtualizada::emitir($codigo, 'encerramento');
            }
        }

        $total = bcadd($premios['valor'], $encerramentos['valor'], 2);

        if (bccomp($total, '0', 2) === 0) {
            throw ValidationException::withMessages(['cliente' => 'Nenhum prêmio ou encerramento pendente.']);
        }

        ClientesAntecipacoes::create([
            'clientes_id' => $cliente->id,
            'usuarios_id' => $autor->id,
            'quantidade_premios' => $premios['quantidade'],
            'valor_premios' => $premios['valor'],
            'quantidade_encerramentos' => $encerramentos['quantidade'],
            'valor_encerramentos' => $encerramentos['valor'],
            'ip' => $ip,
        ]);

        return [
            'creditado' => $total,
            'premios' => $premios,
            'encerramentos' => $encerramentos,
            'saldo' => bcadd((string) $cliente->fresh()->saldo, '0', 2),
        ];
    }

    /**
     * Apostas do cliente marcadas como pendentes com crédito a fazer (não estornos, que são na hora).
     *
     * @return Collection<int, Apostas>
     */
    private function apostas_pendentes(Clientes $cliente): Collection
    {
        return Apostas::where('clientes_id', $cliente->id)
            ->where('liquidacao_pendente', true)
            ->orderBy('id')
            ->get()
            ->filter(fn (Apostas $aposta) => bccomp($this->a_creditar($aposta), '0', 2) > 0)
            ->values();
    }

    private function a_creditar(Apostas $aposta): string
    {
        return bcsub($this->liquidacao_apostas->devido($aposta), (string) $aposta->valor_pago, 2);
    }

    /**
     * Encerramentos registrados que ainda esperam a janela.
     *
     * @return Collection<int, ApostasEncerramentos>
     */
    private function encerramentos_pendentes(Clientes $cliente): Collection
    {
        return ApostasEncerramentos::where('clientes_id', $cliente->id)
            ->where('situacao', SituacaoEncerramento::Registrado)
            ->where('creditar_em', '>', now())
            ->orderBy('id')
            ->get();
    }
}
