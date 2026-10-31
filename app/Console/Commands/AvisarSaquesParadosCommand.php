<?php

namespace App\Console\Commands;

use App\Enums\SituacaoSaque;
use App\Models\Saques;
use App\Services\DisparoMensagens;
use App\Services\TextosMensagens;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Avisa o Admin do saque parado há 30 minutos (spec 025, FR-011, research R-06), a qualquer hora e uma
 * única vez por saque. Substitui o VerificarSaquesCommand do sistema antigo.
 */
class AvisarSaquesParadosCommand extends Command
{
    protected $signature = 'saques:avisar_parados';

    protected $description = 'Avisa o Admin dos saques aguardando há 30 minutos';

    private const MINUTOS_PARADO = 30;

    public function handle(DisparoMensagens $disparo, TextosMensagens $textos): int
    {
        $ids = Saques::where('situacao', SituacaoSaque::Pendente)
            ->whereNull('aviso_parado_em')
            ->where('created_at', '<=', now()->subMinutes(self::MINUTOS_PARADO))
            ->pluck('id');

        foreach ($ids as $id) {
            DB::transaction(function () use ($id, $disparo, $textos) {
                $saque = Saques::with('cliente')->lockForUpdate()->find($id);

                // pode ter sido decidido ou avisado depois da consulta
                if ($saque?->situacao !== SituacaoSaque::Pendente || $saque->aviso_parado_em !== null) {
                    return;
                }

                $saque->forceFill(['aviso_parado_em' => now()])->save();

                $disparo->enviar($textos->saque_parado($saque));
            });
        }

        $this->info("Saques avisados: {$ids->count()}.");

        return self::SUCCESS;
    }
}
