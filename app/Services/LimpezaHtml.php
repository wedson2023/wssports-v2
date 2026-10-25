<?php

namespace App\Services;

use HTMLPurifier;
use HTMLPurifier_Config;
use Illuminate\Support\Facades\File;

/**
 * HTML das regras da banca limpo no servidor (spec 021, FR-018, research R-08): só os elementos que
 * o editor gera (parágrafos, títulos, negrito, itálico, sublinhado, riscado, listas, quebra, link e
 * cor/alinhamento por estilo); scripts, eventos, estilos livres e o resto são removidos. Links só
 * http, https, mailto e tel, sempre abrindo em nova aba.
 */
class LimpezaHtml
{
    private ?HTMLPurifier $purificador = null;

    public function limpar(?string $html): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }

        return trim($this->purificador()->purify($html));
    }

    /**
     * Texto visível do HTML, para contar os caracteres que o administrador escreveu.
     */
    public function texto(string $html): string
    {
        return trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    private function purificador(): HTMLPurifier
    {
        if ($this->purificador !== null) {
            return $this->purificador;
        }

        $cache = storage_path('framework/cache/htmlpurifier');
        File::ensureDirectoryExists($cache);

        $configuracao = HTMLPurifier_Config::createDefault();
        $configuracao->set('Cache.SerializerPath', $cache);
        $configuracao->set('HTML.Allowed', 'p[style],h2[style],h3[style],strong,em,u,s,ul,ol,li,br,a[href|target|rel],span[style]');
        $configuracao->set('CSS.AllowedProperties', ['color' => true, 'text-align' => true]);
        $configuracao->set('URI.AllowedSchemes', ['http' => true, 'https' => true, 'mailto' => true, 'tel' => true]);
        $configuracao->set('HTML.TargetBlank', true);
        $configuracao->set('HTML.TargetNoopener', true);
        $configuracao->set('HTML.TargetNoreferrer', true);
        $configuracao->set('Attr.AllowedFrameTargets', ['_blank']);

        return $this->purificador = new HTMLPurifier($configuracao);
    }
}
