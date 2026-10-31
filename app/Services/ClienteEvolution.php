<?php

namespace App\Services;

use App\Exceptions\DestinatarioSemWhatsappException;
use App\Exceptions\RoboIndisponivelException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Única classe que fala com a Evolution API v2 (spec 021, research R-10): instância da banca, QR
 * code, situação da conexão, perfil, desconexão e envio de texto (spec 025). A chave global vai só no cabeçalho "apikey" e
 * nunca para o log; falha de conexão, 5xx ou demora acima do tempo limite viram
 * RoboIndisponivelException.
 */
class ClienteEvolution
{
    /**
     * Nome da instância da banca: o configurado ou o host do site (um servidor atende várias bancas).
     */
    public function instancia(): string
    {
        $configurada = (string) config('services.evolution.instancia');

        if ($configurada !== '') {
            return $configurada;
        }

        $host = (string) parse_url((string) config('app.url'), PHP_URL_HOST);

        return trim(Str::of(strtolower($host))->replaceMatches('/[^a-z0-9]+/', '-')->toString(), '-') ?: 'wssports';
    }

    public function configurado(): bool
    {
        return (string) config('services.evolution.url') !== '' && (string) config('services.evolution.chave') !== '';
    }

    public function existe(): bool
    {
        $instancias = $this->chamar('get', '/instance/fetchInstances', ['instanceName' => $this->instancia()])->json();

        return is_array($instancias) && $this->dados_da_instancia($instancias) !== null;
    }

    /**
     * Cria a instância da banca já com o aviso de mudança de conexão (webhook) configurado.
     */
    public function criar(string $url_webhook): void
    {
        $this->chamar('post', '/instance/create', [
            'instanceName' => $this->instancia(),
            'integration' => 'WHATSAPP-BAILEYS',
            'qrcode' => true,
            'webhook' => $this->webhook($url_webhook),
        ]);
    }

    /**
     * Garante o webhook numa instância que já existia (ex.: criada antes do token mudar).
     */
    public function configurar_webhook(string $url_webhook): void
    {
        $this->chamar('post', '/webhook/set/'.rawurlencode($this->instancia()), ['webhook' => $this->webhook($url_webhook)]);
    }

    /**
     * QR code para conectar (data URL da imagem); null quando a Evolution não devolve um (já conectado).
     */
    public function qrcode(): ?string
    {
        $base64 = $this->chamar('get', '/instance/connect/'.rawurlencode($this->instancia()))->json('base64');

        if (! is_string($base64) || $base64 === '') {
            return null;
        }

        return str_starts_with($base64, 'data:') ? $base64 : 'data:image/png;base64,'.$base64;
    }

    /**
     * Estado da conexão: open, connecting ou close.
     */
    public function estado(): string
    {
        return (string) ($this->chamar('get', '/instance/connectionState/'.rawurlencode($this->instancia()))->json('instance.state') ?? 'close');
    }

    /**
     * Número (só dígitos) e nome do perfil conectados.
     *
     * @return array{numero: ?string, perfil: ?string}
     */
    public function perfil(): array
    {
        $instancias = $this->chamar('get', '/instance/fetchInstances', ['instanceName' => $this->instancia()])->json();
        $dados = is_array($instancias) ? $this->dados_da_instancia($instancias) : null;

        return [
            'numero' => $this->numero($dados['ownerJid'] ?? $dados['owner'] ?? null),
            'perfil' => isset($dados['profileName']) ? mb_substr((string) $dados['profileName'], 0, 100) : null,
        ];
    }

    public function sair(): void
    {
        $this->chamar('delete', '/instance/logout/'.rawurlencode($this->instancia()));
    }

    /**
     * Envia um texto pelo robô da banca (spec 025, research R-01 e R-02). Resposta 4xx é definitiva;
     * sem conexão ou 5xx, temporária. O texto nunca vai para o log, porque pode levar um código.
     */
    public function enviar_texto(string $numero, string $texto): void
    {
        if (! $this->configurado()) {
            throw new RoboIndisponivelException;
        }

        try {
            $resposta = $this->cliente()->post('/message/sendText/'.rawurlencode($this->instancia()), [
                'number' => $numero,
                'text' => $texto,
            ]);
        } catch (ConnectionException) {
            Log::warning('Evolution API sem resposta no envio de mensagem.');

            throw new RoboIndisponivelException;
        }

        if ($resposta->serverError()) {
            Log::warning('Evolution API com erro no envio de mensagem.', ['status' => $resposta->status()]);

            throw new RoboIndisponivelException;
        }

        if ($resposta->clientError()) {
            Log::warning('Evolution API recusou o envio de mensagem.', ['status' => $resposta->status()]);

            throw new DestinatarioSemWhatsappException($resposta->status());
        }
    }

    /**
     * Só dígitos de um JID do WhatsApp (5511999999999@s.whatsapp.net → 5511999999999).
     */
    public function numero(?string $jid): ?string
    {
        $digitos = preg_replace('/\D/', '', (string) strstr((string) $jid.'@', '@', true));

        return $digitos === '' ? null : mb_substr($digitos, 0, 20);
    }

    /**
     * @return array<string, mixed>
     */
    private function webhook(string $url): array
    {
        return [
            'enabled' => true,
            'url' => $url,
            'byEvents' => false,
            'base64' => false,
            'headers' => ['x-webhook-token' => (string) config('services.evolution.webhook_token')],
            'events' => ['CONNECTION_UPDATE'],
        ];
    }

    /**
     * A busca por nome devolve uma lista; cada item vem direto ou dentro de "instance" (versões).
     *
     * @param  array<int|string, mixed>  $instancias
     * @return array<string, mixed>|null
     */
    private function dados_da_instancia(array $instancias): ?array
    {
        foreach (array_is_list($instancias) ? $instancias : [$instancias] as $item) {
            $dados = $item['instance'] ?? $item;
            $nome = $dados['name'] ?? $dados['instanceName'] ?? null;

            if ($nome === $this->instancia()) {
                return $dados;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $dados
     */
    private function chamar(string $metodo, string $caminho, array $dados = []): Response
    {
        if (! $this->configurado()) {
            throw new RoboIndisponivelException;
        }

        try {
            $resposta = $this->cliente()->{$metodo}($caminho, $dados);
        } catch (ConnectionException $erro) {
            Log::warning('Evolution API sem resposta.', ['caminho' => $caminho, 'erro' => $erro->getMessage()]);

            throw new RoboIndisponivelException;
        }

        if ($resposta->serverError()) {
            Log::warning('Evolution API com erro.', ['caminho' => $caminho, 'status' => $resposta->status()]);

            throw new RoboIndisponivelException;
        }

        if ($resposta->clientError() && $resposta->status() !== 404) {
            Log::warning('Evolution API recusou o pedido.', ['caminho' => $caminho, 'status' => $resposta->status(), 'resposta' => $resposta->json()]);

            throw new RoboIndisponivelException('A Evolution API recusou o pedido. Confira a chave e a instância no servidor.');
        }

        return $resposta;
    }

    private function cliente(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('services.evolution.url'), '/'))
            ->withHeaders(['apikey' => (string) config('services.evolution.chave')])
            ->acceptJson()
            ->timeout((int) config('services.evolution.tempo_limite', 10));
    }
}
