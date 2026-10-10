<?php

namespace App\Services;

use App\Models\Avisos;
use App\Models\AvisosLeituras;
use App\Models\Clientes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

/**
 * Aviso mostrado ao abrir o site (spec 006, FR-028 e FR-030): um aviso vigente ainda não lido pelo
 * aparelho nem pelo cliente logado, sorteado entre os elegíveis, como no sistema antigo.
 */
class EscolhaAvisos
{
    public function atual(string $aparelho, ?Clientes $cliente): ?Avisos
    {
        $candidatos = Avisos::vigentes()
            ->whereDoesntHave('leituras', fn (Builder $leituras) => $leituras->where(fn (Builder $quem) => $quem
                ->where('aparelho', $aparelho)
                ->when($cliente !== null, fn (Builder $c) => $c->orWhere('clientes_id', $cliente->id))))
            ->inRandomOrder()
            ->limit(10)
            ->get();

        // aviso com o arquivo da imagem removido não aparece
        return $candidatos->first(fn (Avisos $aviso) => Storage::disk('public')->exists($aviso->imagem));
    }

    /**
     * Marca o aviso como lido pelo aparelho (e pelo cliente); repetir não duplica.
     */
    public function ler(Avisos $aviso, string $aparelho, ?Clientes $cliente, ?string $ip): void
    {
        $leitura = AvisosLeituras::firstOrCreate(
            ['avisos_id' => $aviso->id, 'aparelho' => $aparelho],
            ['clientes_id' => $cliente?->id, 'ip' => $ip],
        );

        if ($cliente !== null && $leitura->clientes_id === null) {
            $leitura->update(['clientes_id' => $cliente->id]);
        }
    }
}
