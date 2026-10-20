<?php

namespace App\Http\Controllers;

use App\Enums\Funcao;
use App\Http\Controllers\Concerns\GarantirPermissaoCliente;
use App\Http\Requests\DashboardRequest;
use App\Models\ApostasCombinacoesRisco;
use App\Models\Usuarios;
use App\Services\AlcanceDashboard;
use App\Services\ApostasDashboard;
use App\Services\ApuradoDashboard;
use App\Services\CacheDashboard;
use App\Services\CaixaOnline;
use App\Services\CombinacoesRisco;
use App\Support\FusoSistema;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;

/**
 * Seções da Dashboard do painel (spec 014, contracts/api.md §2): uma ação por seção, cada uma com a
 * sua permissão `dashboard.ver_*`, o alcance do nível aplicado nos serviços e cache de 60 s. Os
 * avisos de combinação só leem o que o agendador registrou: nenhuma ação daqui gera aviso.
 */
class DashboardController extends Controller implements HasMiddleware
{
    use GarantirPermissaoCliente;

    public function __construct(
        private readonly CacheDashboard $cache,
        private readonly AlcanceDashboard $alcance,
        private readonly ApostasDashboard $apostas,
    ) {}

    public static function middleware(): array
    {
        return [
            self::permissao_cliente('dashboard.ver_apurado', only: ['apurado']),
            self::permissao_cliente('dashboard.ver_situacao_jogos', only: ['situacao_jogos']),
            self::permissao_cliente('dashboard.ver_apostas', only: ['apostas']),
            self::permissao_cliente('dashboard.ver_combinacao_risco', only: ['combinacoes_risco', 'avisos_combinacoes_risco']),
            self::permissao_cliente('dashboard.ver_apostas_um_jogo', only: ['apostas_um_jogo']),
            self::permissao_cliente('dashboard.ver_vendedores', only: ['vendedores']),
            self::permissao_cliente('dashboard.ver_jogos_mais_apostados', only: ['jogos_mais_apostados']),
            self::permissao_cliente('dashboard.ver_opcoes_mais_apostadas', only: ['opcoes_mais_apostadas']),
            self::permissao_cliente('dashboard.ver_caixa_online', only: ['caixa_online']),
        ];
    }

    public function apurado(DashboardRequest $request, ApuradoDashboard $apurado): JsonResponse
    {
        return $this->responder('apurado', $request, fn (Usuarios $usuario) => $apurado->calcular($usuario));
    }

    public function situacao_jogos(DashboardRequest $request): JsonResponse
    {
        return $this->responder('situacao_jogos', $request, fn (Usuarios $usuario) => $this->apostas->situacao_jogos($usuario));
    }

    public function apostas(DashboardRequest $request): JsonResponse
    {
        return $this->responder('apostas', $request, fn (Usuarios $usuario) => $this->apostas->resumo_apostas($usuario));
    }

    public function combinacoes_risco(DashboardRequest $request, CombinacoesRisco $combinacoes): JsonResponse
    {
        return $this->responder('combinacoes_risco', $request, fn (Usuarios $usuario) => array_map(
            // os vendedores servem só ao agendador; não saem na resposta
            fn (array $combinacao) => array_diff_key($combinacao, ['usuarios_ids' => true]),
            $combinacoes->listar($this->alcance->ids_vendedores($usuario)),
        ));
    }

    /**
     * Avisos registrados pelo agendador depois do último que o usuário viu (FR-047): sem cache e sem
     * registrar nada. Sem `depois_de`, só o marcador do último aviso (primeira abertura).
     */
    public function avisos_combinacoes_risco(DashboardRequest $request): JsonResponse
    {
        $usuario = $request->user();
        $desde = FusoSistema::inicio_do_dia()->setTimezone(FusoSistema::FUSO)->subDay()->toDateString();
        $consulta = ApostasCombinacoesRisco::where('dia', '>=', $desde);
        $ultimo_id = (int) (clone $consulta)->max('id');

        if (! $request->filled('depois_de')) {
            return response()->json(['data' => [], 'ultimo_id' => $ultimo_id]);
        }

        $ids = $usuario->funcao() === Funcao::Admin ? null : ($this->alcance->ids_vendedores($usuario) ?? []);

        $avisos = $consulta->where('id', '>', (int) $request->validated('depois_de'))
            ->orderBy('id')
            ->get()
            ->filter(fn (ApostasCombinacoesRisco $aviso) => $ids === null || array_intersect($aviso->usuarios_ids, $ids) !== [])
            ->map(fn (ApostasCombinacoesRisco $aviso) => [
                'id' => $aviso->id,
                'chave' => $aviso->chave,
                'jogos' => $aviso->jogos,
                'opcoes' => $aviso->opcoes,
                'quantidade_bilhetes' => $aviso->quantidade_bilhetes,
                'premio' => $aviso->premio,
                'registrado_em' => $aviso->created_at->setTimezone(FusoSistema::FUSO)->toIso8601String(),
            ])
            ->values();

        return response()->json(['data' => $avisos, 'ultimo_id' => $ultimo_id]);
    }

    public function apostas_um_jogo(DashboardRequest $request): JsonResponse
    {
        return $this->responder('apostas_um_jogo', $request, fn (Usuarios $usuario) => $this->apostas->apostas_um_jogo($usuario));
    }

    public function vendedores(DashboardRequest $request): JsonResponse
    {
        return $this->responder('vendedores', $request, fn (Usuarios $usuario) => $this->apostas->vendedores($usuario));
    }

    public function jogos_mais_apostados(DashboardRequest $request): JsonResponse
    {
        return $this->responder('jogos_mais_apostados', $request, fn (Usuarios $usuario) => $this->apostas->jogos_mais_apostados($usuario));
    }

    public function opcoes_mais_apostadas(DashboardRequest $request): JsonResponse
    {
        return $this->responder('opcoes_mais_apostadas', $request, fn (Usuarios $usuario) => $this->apostas->opcoes_mais_apostadas($usuario));
    }

    public function caixa_online(DashboardRequest $request, CaixaOnline $caixa): JsonResponse
    {
        return $this->responder('caixa_online', $request, fn () => $caixa->calcular());
    }

    /**
     * Resposta padrão de uma seção, pelo cache do usuário (o `atualizar` recalcula na hora).
     */
    private function responder(string $secao, DashboardRequest $request, Closure $calcular): JsonResponse
    {
        $usuario = $request->user();

        return response()->json($this->cache->lembrar($secao, $usuario, $request->atualizar(), fn () => $calcular($usuario)));
    }
}
