<?php

namespace App\Services;

use App\Enums\TipoNotificacao;
use App\Fakes\DadosFake;
use App\Jobs\EnviarNotificacaoPush;
use App\Models\ClientesInscricoesNotificacoes;
use App\Models\ClientesNotificacoes;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Throwable;

/**
 * Notificações push do aplicativo (spec 010, R-13, contracts/tempo_real.md): registra cada aviso uma
 * única vez por cliente (chave única) e envia pelo Web Push com as chaves VAPID próprias, sem
 * servidor externo. Inscrição recusada pelo serviço de push (404/410) é descartada (FR-046).
 */
class NotificacoesPush
{
    /**
     * Tempo de vida do aviso no serviço de push, em segundos: aviso de jogo atrasado não vale.
     */
    private const TTL_JOGO = 600;

    private const TTL_APOSTA = 86400;

    /**
     * Notificações push configuradas (chaves VAPID no .env).
     */
    public static function habilitado(): bool
    {
        return filled(config('services.notificacoes_push.chave_publica')) && filled(config('services.notificacoes_push.chave_privada'));
    }

    /**
     * Registra o aviso do fato (chave) para o cliente; nulo quando ele já foi avisado desse fato
     * (SC-008: sem aviso repetido, mesmo com várias apostas no jogo ou job repetido).
     */
    public function registrar(int $clientes_id, TipoNotificacao $tipo, string $chave, string $titulo, ?string $corpo, string $url, ?int $confrontos_eventos_id = null, ?int $apostas_id = null): ?ClientesNotificacoes
    {
        try {
            return ClientesNotificacoes::create([
                'clientes_id' => $clientes_id,
                'tipo' => $tipo,
                'chave' => mb_substr($chave, 0, 80),
                'confrontos_eventos_id' => $confrontos_eventos_id,
                'apostas_id' => $apostas_id,
                'titulo' => mb_substr($titulo, 0, 120),
                'corpo' => $corpo !== null ? mb_substr($corpo, 0, 255) : null,
                'url' => $url,
            ]);
        } catch (UniqueConstraintViolationException) {
            return null;
        }
    }

    /**
     * Registra e manda para a fila de envio, depois do commit de quem chama.
     */
    public function avisar(int $clientes_id, TipoNotificacao $tipo, string $chave, string $titulo, ?string $corpo, string $url, ?int $confrontos_eventos_id = null, ?int $apostas_id = null): void
    {
        if (! self::habilitado()) {
            return;
        }

        $notificacao = $this->registrar($clientes_id, $tipo, $chave, $titulo, $corpo, $url, $confrontos_eventos_id, $apostas_id);

        if ($notificacao !== null) {
            EnviarNotificacaoPush::dispatch($notificacao->id)->onQueue(EnviarNotificacaoPush::FILA)->afterCommit();
        }
    }

    /**
     * Envia o aviso às inscrições do cliente com o tipo ligado; conta entregues e recusados.
     */
    public function enviar(ClientesNotificacoes $notificacao): void
    {
        if (! self::habilitado()) {
            return;
        }

        $coluna = $notificacao->tipo->coluna_preferencia();
        $inscricoes = ClientesInscricoesNotificacoes::where('clientes_id', $notificacao->clientes_id)->where($coluna, true)->get()->keyBy('endpoint');

        if ($inscricoes->isEmpty()) {
            return;
        }

        $de_jogo = $notificacao->tipo->e_de_jogo();
        $web_push = new WebPush(['VAPID' => [
            'subject' => config('services.notificacoes_push.assunto'),
            'publicKey' => config('services.notificacoes_push.chave_publica'),
            'privateKey' => config('services.notificacoes_push.chave_privada'),
        ]], [
            'TTL' => $de_jogo ? self::TTL_JOGO : self::TTL_APOSTA,
            'urgency' => in_array($notificacao->tipo, [TipoNotificacao::Gol, TipoNotificacao::GolAnulado], true) ? 'high' : 'normal',
        ]);

        $conteudo = json_encode($this->conteudo($notificacao), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        foreach ($inscricoes as $inscricao) {
            $web_push->queueNotification(Subscription::create([
                'endpoint' => $inscricao->endpoint,
                'publicKey' => $inscricao->chave_p256dh,
                'authToken' => $inscricao->chave_auth,
                'contentEncoding' => $inscricao->codificacao,
            ]), $conteudo);
        }

        $entregues = 0;
        $falhas = 0;

        try {
            foreach ($web_push->flush() as $relatorio) {
                $inscricao = $inscricoes->get($relatorio->getEndpoint());

                if ($relatorio->isSuccess()) {
                    $entregues++;
                    $inscricao?->forceFill(['ultimo_envio_em' => now()])->save();

                    continue;
                }

                $falhas++;

                if ($relatorio->isSubscriptionExpired()) {
                    // o aparelho removeu a inscrição ou ela venceu: não vale mais (FR-046)
                    $inscricao?->delete();
                } else {
                    Log::warning("Notificação push {$notificacao->id} recusada: ".$relatorio->getReason());
                }
            }
        } catch (Throwable $erro) {
            Log::warning("Notificação push {$notificacao->id} não enviada: ".class_basename($erro).' - '.$erro->getMessage());
        }

        $notificacao->forceFill(['aparelhos' => $entregues, 'falhas' => $falhas])->save();
    }

    /**
     * Conteúdo lido pelo service worker (contracts/tempo_real.md): o aviso novo do mesmo jogo ou da
     * mesma aposta substitui o anterior (tag).
     *
     * @return array<string, string|null>
     */
    private function conteudo(ClientesNotificacoes $notificacao): array
    {
        $tag = $notificacao->confrontos_eventos_id !== null
            ? 'confronto-'.$notificacao->evento?->confrontos_id
            : 'aposta-'.$notificacao->aposta?->codigo;

        return [
            'titulo' => $notificacao->titulo,
            'corpo' => $notificacao->corpo,
            'url' => $notificacao->url,
            'tag' => $tag,
            'tipo' => $notificacao->tipo->value,
            'icone' => collect(DadosFake::icones())->firstWhere('sizes', '192x192')['src'] ?? null,
        ];
    }
}
