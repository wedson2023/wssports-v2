<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Regras da banca de texto puro (um parágrafo por linha) para HTML (spec 021, FR-020, research R-09):
 * cada linha não vazia vira um <p>. Registros que já estão em HTML (começam com "<") ficam como estão.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('configuracoes')->select(['id', 'regras'])->orderBy('id')->each(function (object $registro) {
            $texto = trim((string) $registro->regras);

            if ($texto === '' || str_starts_with($texto, '<')) {
                return;
            }

            $paragrafos = array_filter(array_map('trim', preg_split('/\R/u', $texto)), fn (string $linha) => $linha !== '');
            $html = implode('', array_map(fn (string $linha) => '<p>'.htmlspecialchars($linha, ENT_QUOTES, 'UTF-8').'</p>', $paragrafos));

            DB::table('configuracoes')->where('id', $registro->id)->update(['regras' => $html]);
        });
    }

    /**
     * Reverse the migrations: um parágrafo por linha, sem a formatação.
     */
    public function down(): void
    {
        DB::table('configuracoes')->select(['id', 'regras'])->orderBy('id')->each(function (object $registro) {
            $html = (string) $registro->regras;

            if (! str_starts_with(trim($html), '<')) {
                return;
            }

            $linhas = preg_split('/<\/(?:p|h2|h3|li)>|<br\s*\/?>/i', $html);
            $texto = implode("\n", array_filter(array_map(
                fn (string $linha) => trim(html_entity_decode(strip_tags($linha), ENT_QUOTES | ENT_HTML5, 'UTF-8')),
                $linhas,
            ), fn (string $linha) => $linha !== ''));

            DB::table('configuracoes')->where('id', $registro->id)->update(['regras' => $texto]);
        });
    }
};
