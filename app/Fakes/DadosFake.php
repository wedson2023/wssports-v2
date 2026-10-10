<?php

namespace App\Fakes;

/**
 * Local único dos dados fake do frontend (Princípio IX). Cada método devolve, no formato que o dado
 * real terá, um dado que o backend ainda não fornece; a spec indicada em cada método o substitui e
 * remove o fake correspondente. Nenhum destes dados entra no envio de uma aposta.
 */
class DadosFake
{
    /**
     * Cor principal do tema e a cor escura derivada de cada uma (research.md, R-04).
     */
    private const CORES_DERIVADAS = [
        '#c40808' => '#a41f1a',
        '#d0af01' => '#9f8601',
        '#008000' => '#005400',
        '#006eb1' => '#024b77',
        '#fe6a00' => '#b94e02',
        '#b91552' => '#930137',
    ];

    /**
     * Tamanhos dos ícones do aplicativo, iguais aos do manifest do sistema antigo.
     */
    private const TAMANHOS_ICONES = [32, 64, 96, 128, 168, 192, 256, 512];

    /**
     * FAKE — tema de cores e logo. Substituído pela spec de configurações visuais.
     *
     * @return array{temas: string, letter: string, cor_fundo: string, logo: string}
     */
    public static function tema(): array
    {
        $temas = '#c40808';

        return [
            'temas' => $temas,
            'letter' => self::CORES_DERIVADAS[$temas],
            'cor_fundo' => '#000000',
            'logo' => '/fakes/logo.png',
        ];
    }

    /**
     * FAKE — telefone do WhatsApp e redes sociais (null = não aparece). Substituído pela spec de
     * configurações visuais.
     *
     * @return array<string, string|null>
     */
    public static function contatos(): array
    {
        return [
            'whatsapp' => '5511999999999',
            'mensagem_whatsapp' => 'Olá! Vim pelo site e gostaria de fazer uma aposta esportiva. Pode me ajudar?',
            'instagram' => 'https://www.instagram.com/',
            'youtube' => 'https://www.youtube.com/',
            'twitter' => 'https://twitter.com/',
            'facebook' => 'https://www.facebook.com/',
            'jogo_responsavel' => 'https://www.gamblingtherapy.org/pt-br/',
        ];
    }

    /**
     * FAKE — indicadores de Acumuladão e de Cassino. Substituídos pelas specs próprias.
     *
     * @return array{acumuladao: bool, cassino: bool}
     */
    public static function indicadores(): array
    {
        return [
            'acumuladao' => true,
            'cassino' => true,
        ];
    }

    /**
     * FAKE — banners do carrossel. Substituídos pela spec de banners.
     *
     * @return list<array{imagem: string, link: string|null}>
     */
    public static function banners(): array
    {
        return [
            ['imagem' => '/fakes/banners/1.jpg', 'link' => null],
        ];
    }

    /**
     * FAKE — texto das regras, em parágrafos de texto simples. Substituído pela spec de regras.
     *
     * @return list<string>
     */
    public static function regras(): array
    {
        return [
            'As apostas são aceitas somente para maiores de 18 anos.',
            'O apostador é responsável por conferir os palpites antes de finalizar a aposta. Após a confirmação, a aposta não pode ser alterada pelo apostador.',
            'O código gerado pelo site deve ser validado por um vendedor dentro do prazo de validade; códigos vencidos são descartados.',
            'Jogos adiados, cancelados ou interrompidos têm o palpite cancelado e a cotação considerada como 1,00.',
            'O prêmio é limitado ao valor máximo definido pela banca, mesmo que a multiplicação das cotações resulte em valor maior.',
            'Em caso de erro evidente de cotação, a banca pode cancelar o palpite afetado.',
        ];
    }

    /**
     * FAKE — ícones do aplicativo (PWA). Substituídos pela spec de configurações visuais.
     *
     * @return list<array{src: string, sizes: string, type: string}>
     */
    public static function icones(): array
    {
        return array_map(fn (int $tamanho) => [
            'src' => "/fakes/icones/icon-{$tamanho}.png",
            'sizes' => "{$tamanho}x{$tamanho}",
            'type' => 'image/png',
        ], self::TAMANHOS_ICONES);
    }
}
