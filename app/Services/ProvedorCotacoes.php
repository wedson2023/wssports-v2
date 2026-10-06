<?php

namespace App\Services;

use App\Exceptions\FalhaProvedorException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Cliente HTTP dos provedores de cotações (contrato em specs/003-confrontos/contracts/provedor.md).
 * Uma rota por assunto, como no sistema antigo: campeonatos, confrontos e cotações do pré-jogo,
 * ao vivo e conferência. A API exige a chave nos parâmetros da URL, então nem a URL nem a
 * mensagem original da exceção vão para o log.
 */
class ProvedorCotacoes
{
    /**
     * Todos os campeonatos do pré-jogo.
     *
     * @return array<int|string, mixed>
     */
    public function buscar_campeonatos(): array
    {
        return $this->enviar(
            'campeonatos',
            fn () => $this->cliente('tempo_limite_campeonatos')->get($this->url('url_pre_jogo', 'campeonatos')),
        );
    }

    /**
     * Confrontos do pré-jogo, sem os dos campeonatos desativados.
     *
     * @param  list<int>  $campeonatos_desativados
     * @return array<int|string, mixed>
     */
    public function buscar_confrontos(array $campeonatos_desativados): array
    {
        return $this->enviar(
            'confrontos',
            fn () => $this->cliente('tempo_limite_confrontos')
                ->post($this->url('url_pre_jogo', 'confrontos'), ['campeonatos_id' => $campeonatos_desativados]),
        );
    }

    /**
     * Cotações e jogadores dos confrontos do pré-jogo, sem os dos campeonatos desativados.
     *
     * @param  list<int>  $campeonatos_desativados
     * @return array<int|string, mixed>
     */
    public function buscar_cotacoes(array $campeonatos_desativados): array
    {
        return $this->enviar(
            'cotações',
            fn () => $this->cliente('tempo_limite_cotacoes')
                ->post($this->url('url_pre_jogo', 'cotacao'), ['campeonatos_id' => $campeonatos_desativados]),
        );
    }

    /**
     * Jogos em andamento, sem os dos campeonatos desativados.
     *
     * @param  list<int>  $campeonatos_desativados
     * @return array<int|string, mixed>
     */
    public function buscar_ao_vivo(array $campeonatos_desativados): array
    {
        return $this->enviar(
            'ao vivo',
            fn () => $this->cliente('tempo_limite_ao_vivo')
                ->post($this->url('url_ao_vivo', 'aovivo'), ['campeonatos_id' => $campeonatos_desativados]),
        );
    }

    /**
     * Minuto de um jogo no segundo provedor (conferência do ao vivo).
     */
    public function consultar_minuto(int $codigo_externo): int
    {
        $resposta = $this->enviar(
            'conferência',
            fn () => $this->cliente('tempo_limite_conferencia')
                ->get($this->url('url_conferencia', "confrontos/{$codigo_externo}")),
        );

        if (! isset($resposta['minuto_exato']) || ! is_numeric($resposta['minuto_exato'])) {
            throw new FalhaProvedorException('Resposta da conferência sem o minuto do jogo.');
        }

        return (int) $resposta['minuto_exato'];
    }

    private function cliente(string $chave_tempo_limite): PendingRequest
    {
        $configuracao = config('services.provedor_cotacoes');

        return Http::acceptJson()
            ->withHeaders(['Accept-Encoding' => 'gzip'])
            ->withQueryParameters([
                'key' => (string) $configuracao['chave'],
                'app' => (string) ($configuracao['app'] ?: config('app.url')),
            ])
            ->timeout($configuracao[$chave_tempo_limite]);
    }

    private function url(string $chave, string $rota): string
    {
        $url = config("services.provedor_cotacoes.{$chave}");

        if (blank($url)) {
            throw new FalhaProvedorException("Endereço do provedor não configurado ({$chave}).");
        }

        return rtrim($url, '/')."/{$rota}";
    }

    /**
     * Executa a chamada e converte qualquer falha em FalhaProvedorException, sem expor a chave.
     *
     * @param  callable(): Response  $chamada
     * @return array<int|string, mixed>
     */
    private function enviar(string $nome, callable $chamada): array
    {
        try {
            $resposta = $chamada();
        } catch (FalhaProvedorException $erro) {
            throw $erro;
        } catch (Throwable $erro) {
            throw new FalhaProvedorException("Falha ao chamar o provedor ({$nome}): ".class_basename($erro).'.');
        }

        if (! $resposta->successful()) {
            throw new FalhaProvedorException("Provedor ({$nome}) respondeu com status {$resposta->status()}.");
        }

        $dados = $resposta->json();

        if (! is_array($dados)) {
            throw new FalhaProvedorException("Resposta do provedor ({$nome}) não é um JSON válido.");
        }

        return $dados;
    }
}
