<?php

namespace App\Http\Controllers;

use App\Http\Requests\PaginaInicialRequest;
use App\Models\Banners;
use App\Models\Configuracoes;
use App\Services\IdentificacaoPublico;
use App\Services\ListagemConfrontos;
use App\Services\ListagemEspeciais;
use App\Services\Publico;
use App\Services\RegrasExibicao;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Tela principal do site (área "/"): jogos pelo mesmo serviço da listagem pública, configurações
 * e banners reais (contracts/paginas.md). Com o token de um cliente (sessão do site), cotações,
 * limites e saldo são os do cliente; com o de um vendedor, as cotações e os limites dele (aposta
 * como no painel, igual ao sistema antigo); sem token, os do visitante.
 */
class PaginaInicialController extends Controller
{
    public function index(PaginaInicialRequest $request, ListagemConfrontos $listagem, RegrasExibicao $regras, IdentificacaoPublico $identificacao, ListagemEspeciais $especiais): Response
    {
        $publico = $this->publico($request, $identificacao);
        $filtros = $request->filtros();
        $aviso = null;

        if (($filtros['tipo'] ?? 'pre_jogo') === 'ao_vivo') {
            try {
                $listagem_jogos = $listagem->ao_vivo($publico, $filtros);
            } catch (HttpException $excecao) {
                // ao vivo indisponível: a tela volta ao futebol pré-jogo e mostra o motivo (FR-033)
                $aviso = $excecao->getMessage();
                $filtros = ['por_pagina' => $filtros['por_pagina'], 'tipo' => 'pre_jogo', 'esporte' => 'FUTEBOL'];
            }
        }

        // Especiais: a lista de categorias no lugar dos jogos, no mesmo formato (spec 006, R-12)
        if (mb_strtoupper($filtros['esporte'] ?? '') === 'ESPECIAL' && ($filtros['tipo'] ?? 'pre_jogo') !== 'ao_vivo') {
            $listagem_jogos = $especiais->listar($publico, [...$filtros, 'especial' => $filtros['campeonato'] ?? null]);
        }

        $listagem_jogos ??= $listagem->pre_jogo($publico, $filtros);

        return Inertia::render('Home', [
            'filtros' => [
                'tipo' => $filtros['tipo'] ?? 'pre_jogo',
                'esporte' => mb_strtoupper($filtros['esporte'] ?? 'FUTEBOL'),
                'dia' => $filtros['dia'] ?? 'hoje',
                'busca' => $filtros['busca'] ?? null,
                'campeonato' => isset($filtros['campeonato']) ? (int) $filtros['campeonato'] : null,
            ],
            'listagem' => $listagem_jogos,
            'configuracoes' => $this->configuracoes($publico, $regras),
            'banners' => Banners::ativos(),
            'aviso' => $aviso,
            'saldo' => $publico->e_cliente() ? (string) $publico->cliente->saldo : null,
            'apostador' => $this->apostador($request, $publico),
        ]);
    }

    /**
     * Cliente ou vendedor do token, ou visitante. Token de gestor do painel não muda a tela (aposta
     * como visitante); token inválido ou vencido volta como visitante com token recusado.
     */
    private function publico(PaginaInicialRequest $request, IdentificacaoPublico $identificacao): Publico
    {
        $publico = $identificacao->identificar($request);

        return $publico->e_gestor() ? Publico::visitante() : $publico;
    }

    /**
     * Como o cupom aposta: "cliente" (saldo), "vendedor" (painel) ou "visitante" (código); nulo
     * quando a requisição veio sem token (a tela ainda não sabe quem está na sessão).
     */
    private function apostador(PaginaInicialRequest $request, Publico $publico): ?string
    {
        if ($request->bearerToken() === null) {
            return null;
        }

        return match (true) {
            $publico->e_cliente() => 'cliente',
            $publico->e_vendedor() => 'vendedor',
            default => 'visitante',
        };
    }

    /**
     * Configurações reais de quem está vendo (visitante, cliente ou vendedor) usadas pela tela (barra de
     * esportes, abas de data e estimativa do cupom).
     *
     * @return array<string, mixed>
     */
    private function configuracoes(Publico $publico, RegrasExibicao $regras): array
    {
        $configuracao = $regras->configuracao($publico);

        return [
            'mensagem_bilhete' => Configuracoes::atual()->mensagem_bilhete,
            'ao_vivo_habilitado' => $regras->ao_vivo_habilitado($publico),
            'esportes_permitidos' => $regras->esportes_permitidos($publico),
            'apostar_outros_esportes' => (bool) $configuracao->apostar_outros_esportes,
            'periodo_jogos' => $regras->periodo($publico)->value,
            'multiplicador' => (int) $configuracao->multiplicador,
            'premio_maximo' => (string) $configuracao->premio_maximo,
            'ganho_multiplo_palpites' => (string) $configuracao->ganho_multiplo_palpites,
            'comissao_por_premio' => $publico->e_vendedor() ? (string) $configuracao->comissao_por_premio : '0.00',
            'valor_minimo_aposta' => (string) $configuracao->valor_minimo_aposta,
            'valor_maximo_aposta' => (string) $configuracao->valor_maximo_aposta,
            'quantidade_minima_opcoes' => (int) $configuracao->quantidade_minima_opcoes,
            'quantidade_maxima_opcoes' => (int) $configuracao->quantidade_maxima_opcoes,
        ];
    }
}
