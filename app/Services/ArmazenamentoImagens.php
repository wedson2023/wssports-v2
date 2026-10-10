<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use SplFileInfo;

/**
 * Imagens de avisos, banners e logo no disco public. O nome do arquivo é o hash do conteúdo: trocar
 * a imagem muda o endereço (o navegador não mostra a antiga guardada no cache) e enviar a mesma
 * imagem de novo mantém o endereço.
 */
class ArmazenamentoImagens
{
    private const EXTENSOES = [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/webp' => 'webp',
    ];

    /**
     * Endereço público (relativo ao site) de um caminho do disco public.
     */
    public static function url(string $caminho): string
    {
        return '/storage/'.ltrim($caminho, '/');
    }

    /**
     * Grava a imagem em {pasta}/{sha1}.{ext} e devolve o caminho. Com largura e altura, a imagem é
     * ajustada a esse tamanho (esticada, sem corte, como no sistema antigo) e salva em JPEG.
     */
    public function salvar(SplFileInfo $arquivo, string $pasta, ?int $largura = null, ?int $altura = null): string
    {
        $conteudo = file_get_contents($arquivo->getRealPath());

        if ($largura !== null && $altura !== null) {
            $conteudo = $this->redimensionar($conteudo, $largura, $altura);
            $extensao = 'jpg';
        } else {
            $extensao = self::EXTENSOES[(new \finfo(FILEINFO_MIME_TYPE))->buffer($conteudo)] ?? 'png';
        }

        $caminho = $pasta.'/'.sha1($conteudo).'.'.$extensao;

        if (! Storage::disk('public')->exists($caminho)) {
            Storage::disk('public')->put($caminho, $conteudo);
        }

        return $caminho;
    }

    /**
     * Apaga a imagem, a não ser que outro aviso, banner ou a logo ainda use o mesmo arquivo (dois
     * envios da mesma imagem geram o mesmo nome). Registros removidos (soft delete) não contam.
     */
    public function remover(?string $caminho): void
    {
        if ($caminho === null || $caminho === '') {
            return;
        }

        $em_uso = DB::table('avisos')->whereNull('deleted_at')->where('imagem', $caminho)->exists()
            || DB::table('banners')->whereNull('deleted_at')->where('imagem', $caminho)->exists()
            || DB::table('configuracoes')->whereNull('deleted_at')->where('logo', $caminho)->exists();

        if (! $em_uso) {
            Storage::disk('public')->delete($caminho);
        }
    }

    private function redimensionar(string $conteudo, int $largura, int $altura): string
    {
        $origem = @imagecreatefromstring($conteudo);

        if ($origem === false) {
            throw new RuntimeException('Não foi possível ler a imagem enviada.');
        }

        $destino = imagecreatetruecolor($largura, $altura);
        imagecopyresampled($destino, $origem, 0, 0, 0, 0, $largura, $altura, imagesx($origem), imagesy($origem));

        ob_start();
        imagejpeg($destino, null, 85);

        return (string) ob_get_clean();
    }
}
