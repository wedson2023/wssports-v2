<?php

namespace App\Jobs;

use App\Enums\TipoNotificacao;
use App\Models\ClientesNotificacoesEnvios;
use App\Models\Usuarios;
use App\Services\NotificacoesManuais;
use App\Services\NotificacoesPush;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Envia um aviso manual da banca a cada cliente do público (spec 019, R-10), em blocos, e soma no
 * envio quantos aparelhos receberam e quantos recusaram. Uma tentativa só: a chave única por
 * cliente (aviso-{envio}) impede aviso repetido se o job for refeito à mão.
 */
class EnviarNotificacoesManuais implements ShouldQueue
{
    use Queueable;

    /**
     * Mesma fila dos avisos automáticos.
     */
    public const FILA = EnviarNotificacaoPush::FILA;

    private const LOTE = 500;

    public int $tries = 1;

    /**
     * Tempo para percorrer muitos clientes.
     */
    public int $timeout = 1800;

    public function __construct(public int $clientes_notificacoes_envios_id) {}

    public function handle(NotificacoesManuais $manuais, NotificacoesPush $push): void
    {
        $envio = ClientesNotificacoesEnvios::find($this->clientes_notificacoes_envios_id);
        $autor = $envio ? Usuarios::withTrashed()->find($envio->usuarios_id) : null;

        if ($envio === null || $autor === null) {
            return;
        }

        $publico = $manuais->publico($autor, [
            'publico' => $envio->publico->value,
            'clientes_ids' => $envio->clientes_ids,
            'dias' => $envio->dias_sem_interagir,
        ]);

        $entregues = 0;
        $falhas = 0;

        $publico->select('clientes.id')->chunkById(self::LOTE, function ($clientes) use ($envio, $push, &$entregues, &$falhas) {
            foreach ($clientes as $cliente) {
                $notificacao = $push->registrar(
                    $cliente->id,
                    TipoNotificacao::AvisoBanca,
                    'aviso-'.$envio->id,
                    $envio->titulo,
                    $envio->mensagem,
                    '/',
                    clientes_notificacoes_envios_id: $envio->id,
                );

                if ($notificacao === null) {
                    continue;
                }

                $push->enviar($notificacao);
                $entregues += (int) $notificacao->aparelhos;
                $falhas += (int) $notificacao->falhas;
            }
        }, 'clientes.id', 'id');

        $envio->forceFill(['entregues' => $entregues, 'falhas' => $falhas, 'concluido_em' => now()])->save();
    }
}
