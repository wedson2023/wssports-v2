<?php

namespace App\Services;

use App\Exceptions\RegraApostaException;
use App\Models\Clientes;
use App\Models\ClientesInscricoesNotificacoes;

/**
 * Inscrições dos aparelhos nas notificações push (spec 010, FR-040, FR-041, R-15): uma por endpoint
 * (aparelho/navegador); se o aparelho passar a outro cliente, a inscrição passa junto.
 */
class InscricoesNotificacoes
{
    /**
     * Tipos de aviso, ligados por padrão na criação (FR-041).
     */
    public const PREFERENCIAS = [
        'receber_gols',
        'receber_intervalo',
        'receber_fim_jogo',
        'receber_resultado_aposta',
        'receber_encerramento_automatico',
    ];

    /**
     * Cria ou atualiza a inscrição do aparelho.
     *
     * @param  array{endpoint: string, chaves?: array{p256dh?: string, auth?: string}, codificacao?: ?string, preferencias?: array<string, bool>}  $dados
     *
     * @throws RegraApostaException quando falta a chave do aparelho numa inscrição nova
     */
    public function gravar(Clientes $cliente, array $dados, ?string $user_agent): ClientesInscricoesNotificacoes
    {
        $hash = ClientesInscricoesNotificacoes::hash_endpoint($dados['endpoint']);
        $inscricao = ClientesInscricoesNotificacoes::withTrashed()->where('endpoint_hash', $hash)->first();
        $chaves = $dados['chaves'] ?? [];
        $nova = $inscricao === null || $inscricao->trashed();

        if ($inscricao === null && (empty($chaves['p256dh']) || empty($chaves['auth']))) {
            throw new RegraApostaException('Informe as chaves do aparelho para ativar as notificações.');
        }

        $inscricao ??= new ClientesInscricoesNotificacoes(['endpoint' => $dados['endpoint'], 'endpoint_hash' => $hash]);

        $inscricao->fill(array_filter([
            'clientes_id' => $cliente->id,
            'chave_p256dh' => $chaves['p256dh'] ?? null,
            'chave_auth' => $chaves['auth'] ?? null,
            'codificacao' => $dados['codificacao'] ?? ($inscricao->codificacao ?? 'aes128gcm'),
            'aparelho' => $user_agent !== null ? self::aparelho($user_agent) : null,
        ], fn ($valor) => $valor !== null));

        // inscrição nova (ou reativada) começa com todos os tipos ligados
        if ($nova) {
            $inscricao->fill(array_fill_keys(self::PREFERENCIAS, true));
        }

        $inscricao->fill(array_intersect_key($dados['preferencias'] ?? [], array_flip(self::PREFERENCIAS)));
        $inscricao->deleted_at = null;
        $inscricao->save();

        return $inscricao;
    }

    public function buscar(Clientes $cliente, string $endpoint): ?ClientesInscricoesNotificacoes
    {
        return ClientesInscricoesNotificacoes::where('clientes_id', $cliente->id)
            ->where('endpoint_hash', ClientesInscricoesNotificacoes::hash_endpoint($endpoint))
            ->first();
    }

    /**
     * Desativa as notificações do aparelho (soft delete).
     */
    public function remover(Clientes $cliente, string $endpoint): void
    {
        $this->buscar($cliente, $endpoint)?->delete();
    }

    /**
     * Descrição curta do aparelho pelo user agent (ex.: "Chrome no Android"), para o cliente
     * reconhecer onde ativou.
     */
    public static function aparelho(string $user_agent): string
    {
        $navegador = match (true) {
            str_contains($user_agent, 'SamsungBrowser') => 'Samsung Internet',
            str_contains($user_agent, 'Edg/') => 'Edge',
            str_contains($user_agent, 'Firefox/') || str_contains($user_agent, 'FxiOS') => 'Firefox',
            str_contains($user_agent, 'Chrome/') || str_contains($user_agent, 'CriOS') => 'Chrome',
            str_contains($user_agent, 'Safari/') => 'Safari',
            default => 'Navegador',
        };

        $sistema = match (true) {
            str_contains($user_agent, 'Android') => 'Android',
            str_contains($user_agent, 'iPhone') => 'iPhone',
            str_contains($user_agent, 'iPad') => 'iPad',
            str_contains($user_agent, 'Windows') => 'Windows',
            str_contains($user_agent, 'Mac OS') => 'Mac',
            str_contains($user_agent, 'Linux') => 'Linux',
            default => null,
        };

        return mb_substr($sistema !== null ? "{$navegador} no {$sistema}" : $navegador, 0, 255);
    }
}
