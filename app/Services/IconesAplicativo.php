<?php

namespace App\Services;

use GdImage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Ícones do aplicativo instalável gerados da logo (spec 021, FR-011 e FR-012, research R-05): a
 * imagem é centralizada num quadrado, sem distorcer, nos tamanhos do manifest (fundo transparente) e
 * da Apple (fundo na cor de fundo do site, porque o iOS pinta o transparente de preto). A pasta tem o
 * hash da logo no nome: cada logo nova muda o endereço e o navegador não mostra os ícones antigos.
 */
class IconesAplicativo
{
    /**
     * Tamanhos do manifest, iguais aos do sistema antigo.
     */
    public const TAMANHOS = [32, 64, 96, 128, 168, 192, 256, 512];

    /**
     * Tamanhos dos ícones da Apple (iPad, iPad retina e iPhone retina).
     */
    public const TAMANHOS_APPLE = [152, 167, 180];

    /**
     * Abaixo deste lado (px) os ícones grandes ficam borrados (FR-013).
     */
    private const LADO_MINIMO = 192;

    private const PASTA = 'icones';

    /**
     * Gera os ícones e devolve a pasta (disco public). A mesma logo de novo reaproveita a pasta. Os
     * arquivos só vão para o disco depois de todos gerados; se a gravação falhar, a pasta é apagada.
     */
    public function gerar(string $conteudo_logo, string $cor_fundo): string
    {
        $pasta = self::PASTA.'/'.sha1($conteudo_logo);
        $disco = Storage::disk('public');

        if ($disco->exists("{$pasta}/icon-512.png")) {
            return $pasta;
        }

        $origem = @imagecreatefromstring($conteudo_logo);

        if ($origem === false) {
            throw new RuntimeException('Não foi possível ler a imagem enviada.');
        }

        $arquivos = [];

        foreach (self::TAMANHOS as $tamanho) {
            $arquivos["{$pasta}/icon-{$tamanho}.png"] = $this->quadrado($origem, $tamanho, null);
        }

        foreach (self::TAMANHOS_APPLE as $tamanho) {
            $arquivos["{$pasta}/touch-icon-{$tamanho}.png"] = $this->quadrado($origem, $tamanho, $cor_fundo);
        }

        try {
            foreach ($arquivos as $caminho => $png) {
                $disco->put($caminho, $png);
            }
        } catch (Throwable $erro) {
            $disco->deleteDirectory($pasta);

            throw $erro;
        }

        return $pasta;
    }

    /**
     * Apaga a pasta, a não ser que a configuração ainda a use. Registros removidos não contam.
     */
    public function remover(?string $pasta): void
    {
        if ($pasta === null || $pasta === '' || ! str_starts_with($pasta, self::PASTA.'/')) {
            return;
        }

        $em_uso = DB::table('configuracoes')->whereNull('deleted_at')->where('pasta_icones', $pasta)->exists();

        if (! $em_uso) {
            Storage::disk('public')->deleteDirectory($pasta);
        }
    }

    /**
     * Se a imagem tem o menor lado abaixo de 192 px (os ícones grandes ficam borrados).
     */
    public function pequena(string $conteudo_logo): bool
    {
        $medidas = @getimagesizefromstring($conteudo_logo);

        return $medidas !== false && min($medidas[0], $medidas[1]) < self::LADO_MINIMO;
    }

    /**
     * PNG quadrado do tamanho pedido com a imagem inteira centralizada; fundo transparente ou na cor
     * informada (#rrggbb).
     */
    private function quadrado(GdImage $origem, int $tamanho, ?string $fundo): string
    {
        $largura = imagesx($origem);
        $altura = imagesy($origem);
        $escala = $tamanho / max($largura, $altura);
        $nova_largura = max(1, (int) round($largura * $escala));
        $nova_altura = max(1, (int) round($altura * $escala));

        $destino = imagecreatetruecolor($tamanho, $tamanho);

        if ($fundo === null) {
            // transparente: copia sem misturar, guardando o canal alfa da logo
            imagealphablending($destino, false);
            imagefill($destino, 0, 0, imagecolorallocatealpha($destino, 0, 0, 0, 127));
            imagesavealpha($destino, true);
        } else {
            [$vermelho, $verde, $azul] = sscanf($fundo, '#%02x%02x%02x');
            imagefill($destino, 0, 0, imagecolorallocate($destino, $vermelho ?? 0, $verde ?? 0, $azul ?? 0));
        }

        imagecopyresampled(
            $destino, $origem,
            intdiv($tamanho - $nova_largura, 2), intdiv($tamanho - $nova_altura, 2), 0, 0,
            $nova_largura, $nova_altura, $largura, $altura,
        );

        ob_start();
        imagepng($destino, null, 9);

        return (string) ob_get_clean();
    }
}
