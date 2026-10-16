<?php

namespace App\Services;

use App\Exceptions\FalhaProvedorCassinoException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Cliente HTTP do provedor do cassino TBS2 (spec 011, R-01, contracts/provedor.md §1). A chave do hall
 * vai no corpo das chamadas e nunca é registrada: o log traz só o comando e o código de erro.
 */
class ProvedorCassino
{
    /**
     * Catálogo de jogos (gamesList).
     *
     * @return array<string, mixed> o "content" da resposta (gameList, gameLabels, gameTitles)
     *
     * @throws FalhaProvedorCassinoException
     */
    public function listar_jogos(): array
    {
        return $this->enviar('gamesList', $this->url(), [], 'tempo_limite_lista');
    }

    /**
     * Abre um jogo (openGame) com o login do cliente; no demo, sem dinheiro.
     *
     * @return array<string, mixed> o "content" da resposta (game, gameRes)
     *
     * @throws FalhaProvedorCassinoException
     */
    public function abrir_jogo(string $login, string $codigo_provedor, bool $demo): array
    {
        $endereco = rtrim((string) config('app.url'), '/');

        return $this->enviar('openGame', $this->url().'openGame/', [
            'login' => $login,
            'gameId' => $codigo_provedor,
            'demo' => $demo ? '1' : '0',
            'domain' => $endereco,
            'exitUrl' => $endereco.'/close.php',
            'language' => 'pt',
            'continent' => config('cassino.continente'),
        ], 'tempo_limite_abertura');
    }

    /**
     * Histórico de uma sessão como o provedor a registrou (gameSessionsLog).
     *
     * @return array<string, mixed>
     *
     * @throws FalhaProvedorCassinoException
     */
    public function historico_sessao(string $sessao_provedor): array
    {
        return $this->enviar('gameSessionsLog', $this->url(), [
            'sessionsId' => $sessao_provedor,
            'count' => 100,
            'page' => 1,
        ], 'tempo_limite_historico');
    }

    /**
     * @param  array<string, mixed>  $campos
     * @return array<string, mixed>
     *
     * @throws FalhaProvedorCassinoException
     */
    private function enviar(string $comando, string $url, array $campos, string $tempo_limite): array
    {
        $config = config('services.provedor_cassino');

        try {
            /** @var Response $resposta */
            $resposta = Http::asJson()
                ->acceptJson()
                ->timeout((int) $config[$tempo_limite])
                ->post($url, ['cmd' => $comando, 'hall' => $config['hall'], 'key' => $config['chave'], ...$campos]);
        } catch (Throwable $erro) {
            Log::warning("Provedor do cassino ({$comando}): falha na chamada (".class_basename($erro).').');

            throw new FalhaProvedorCassinoException;
        }

        $dados = $resposta->json();

        if (! $resposta->successful() || ! is_array($dados)) {
            Log::warning("Provedor do cassino ({$comando}): resposta inválida (status {$resposta->status()}).");

            throw new FalhaProvedorCassinoException;
        }

        if (($dados['status'] ?? null) !== 'success') {
            $codigo = (string) ($dados['error'] ?? '') ?: null;
            Log::warning("Provedor do cassino ({$comando}): recusado com o erro \"{$codigo}\".");

            throw new FalhaProvedorCassinoException($codigo);
        }

        return is_array($dados['content'] ?? null) ? $dados['content'] : [];
    }

    private function url(): string
    {
        return rtrim((string) config('services.provedor_cassino.url_api'), '/').'/';
    }
}
