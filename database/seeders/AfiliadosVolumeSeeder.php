<?php

namespace Database\Seeders;

use App\Enums\EventoLancamentoAfiliado;
use App\Enums\TipoGanhoAfiliado;
use App\Models\Afiliados;
use App\Models\Clientes;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Volume para medir a abertura do painel do afiliado (spec 012, SC-007): 10.000 lançamentos para o
 * afiliado "afiliado.exemplo". Só para desenvolvimento; não é chamado pelo DatabaseSeeder.
 * Uso: php artisan db:seed --class=AfiliadosVolumeSeeder
 */
class AfiliadosVolumeSeeder extends Seeder
{
    private const QUANTIDADE = 10000;

    private const LOTE = 1000;

    public function run(): void
    {
        $afiliado = Afiliados::where('login', 'afiliado.exemplo')->first();
        $cliente = Clientes::query()->first();

        if ($afiliado === null || $cliente === null) {
            $this->command?->warn('Rode antes o AfiliadosSeeder e o ClientesSeeder.');

            return;
        }

        $agora = now();

        for ($inicio = 0; $inicio < self::QUANTIDADE; $inicio += self::LOTE) {
            $linhas = [];

            for ($posicao = $inicio; $posicao < $inicio + self::LOTE; $posicao++) {
                $linhas[] = [
                    'afiliados_id' => $afiliado->id,
                    'clientes_id' => $cliente->id,
                    'evento' => EventoLancamentoAfiliado::Depósito->value,
                    // prefixo próprio: estes lançamentos não vêm de um depósito real
                    'chave_evento' => "volume:{$afiliado->id}:{$posicao}",
                    'tipo_ganho' => TipoGanhoAfiliado::QualquerDepósito->value,
                    'base' => '100.00',
                    'percentual' => '10.00',
                    'valor' => '10.00',
                    'ocorrido_em' => $agora->copy()->subMinutes(self::QUANTIDADE - $posicao),
                    'created_at' => $agora,
                    'updated_at' => $agora,
                ];
            }

            DB::table('afiliados_lancamentos')->insertOrIgnore($linhas);
        }
    }
}
