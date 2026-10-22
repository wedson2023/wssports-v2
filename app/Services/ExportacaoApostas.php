<?php

namespace App\Services;

use App\Enums\ResultadoAposta;
use App\Models\Apostas;
use App\Models\Usuarios;
use App\Support\FusoSistema;
use App\Support\PlanilhaPainel;
use Generator;

/**
 * Planilha da lista de apostas (spec 018, research R-08, FR-021): período, aba, filtros e totais no
 * cabeçalho e uma linha por aposta do filtro, com as mesmas colunas, contagem de jogos e valores da
 * tela (mesma consulta do ListagemApostas). No máximo 50.000 linhas por arquivo.
 */
class ExportacaoApostas
{
    public const MAXIMO_LINHAS = 50000;

    /**
     * Largura fixa das colunas: a planilha pode ter dezenas de milhares de linhas, e ajustar cada
     * coluna ao conteúdo levaria mais de um minuto.
     */
    private const LARGURAS = [
        'codigo' => 16, 'vendedor' => 28, 'cliente' => 28, 'valor' => 14, 'comissao' => 14, 'premio' => 16,
        'situacao' => 14, 'jogos' => 8, 'horario' => 18, 'cancelada_em' => 18, 'cancelada_por' => 24,
    ];

    private const ABAS = ['todas' => 'Todas', 'pre_jogo' => 'Pré-jogo', 'ao_vivo' => 'Ao vivo', 'canceladas' => 'Canceladas'];

    private const RESULTADOS = [
        'Aguardando' => 'Aguardando', 'Vencedor' => 'Premiadas', 'Perdedor' => 'Perdidas',
        'Devolvido' => 'Devolvidas', 'Encerrada' => 'Encerradas',
    ];

    public function __construct(
        private readonly ListagemApostas $listagem,
        private readonly PlanilhaPainel $planilha,
    ) {}

    /**
     * Gera a planilha e devolve o caminho do arquivo temporário; acima do limite, 422.
     *
     * @param  array<string, mixed>  $filtros  formato de ApostasFiltrosRequest::filtros()
     */
    public function gerar(Usuarios $usuario, array $filtros): string
    {
        $quantidade = $this->listagem->consulta($usuario, $filtros)->count();

        abort_if(
            $quantidade > self::MAXIMO_LINHAS,
            422,
            'O filtro tem '.number_format($quantidade, 0, ',', '.').' apostas; o máximo por planilha é '
                .number_format(self::MAXIMO_LINHAS, 0, ',', '.').'. Reduza o período ou use filtros.',
        );

        $canceladas = $filtros['aba'] === 'canceladas';
        $colunas = [
            'codigo' => 'Código', 'vendedor' => 'Vendedor', 'cliente' => 'Cliente', 'valor' => 'Valor',
            'comissao' => 'Comissão', 'premio' => 'Prêmio', 'situacao' => 'Situação', 'jogos' => 'Jogos',
            'horario' => 'Horário',
            ...($canceladas ? ['cancelada_em' => 'Cancelada em', 'cancelada_por' => 'Cancelada por'] : []),
        ];

        return $this->planilha->gerar(
            'Apostas',
            'Apostas',
            'apostas',
            $this->cabecalho($usuario, $filtros),
            $colunas,
            $this->linhas($usuario, $filtros),
            null,
            ['texto' => ['codigo', 'vendedor', 'cliente', 'situacao', 'jogos', 'horario', 'cancelada_em', 'cancelada_por']],
            self::LARGURAS,
        );
    }

    /**
     * Período, aba, filtros aplicados e totais (os mesmos dos cartões da tela).
     *
     * @param  array<string, mixed>  $filtros
     * @return list<array{0: string, 1: string|float}>
     */
    private function cabecalho(Usuarios $usuario, array $filtros): array
    {
        $totais = $this->listagem->resumo($usuario, $filtros);
        $cabecalho = [
            ['Período', PlanilhaPainel::texto_periodo($filtros['de'], $filtros['ate'])],
            ['Aba', self::ABAS[$filtros['aba']]],
        ];

        $aplicados = array_filter([
            $filtros['nome'] !== null ? 'Busca: '.$filtros['nome'].($filtros['todo_periodo'] ? ' (todo o período)' : '') : null,
            $filtros['resultado'] !== null && $filtros['aba'] !== 'canceladas' ? 'Resultado: '.self::RESULTADOS[$filtros['resultado']] : null,
            $filtros['usuarios_id'] !== null ? 'Vendedor: '.Usuarios::withTrashed()->whereKey($filtros['usuarios_id'])->value('nome') : null,
            $filtros['forma_pagamento'] !== null ? 'Forma de pagamento: '.$filtros['forma_pagamento'] : null,
            $filtros['premio_minimo'] !== null ? 'Prêmio mínimo: R$ '.number_format((float) $filtros['premio_minimo'], 2, ',', '.') : null,
        ]);

        $cabecalho[] = ['Filtros', $aplicados === [] ? 'Nenhum' : implode(' · ', $aplicados)];
        $cabecalho[] = ['Apostas', (string) $totais['apostas']];

        if ($filtros['aba'] === 'canceladas') {
            $cabecalho[] = ['Valor cancelado', (float) $totais['valor_cancelado']];

            return $cabecalho;
        }

        $cabecalho[] = ['Entradas', (float) $totais['entradas']];
        $cabecalho[] = ['Comissão', (float) $totais['comissao']];
        $cabecalho[] = ['Saídas', (float) $totais['saidas']];
        $cabecalho[] = ['Em aberto', (float) $totais['em_aberto']];

        if ($totais['encerramentos'] !== null) {
            $cabecalho[] = ['Encerramentos', (float) $totais['encerramentos']];
        }

        return $cabecalho;
    }

    /**
     * Linhas da planilha, da mais nova para a mais antiga, montadas uma a uma enquanto são lidas
     * (cursor: uma consulta só, sem montar todos os models de uma vez).
     *
     * @param  array<string, mixed>  $filtros
     * @return Generator<array<string, mixed>>
     */
    private function linhas(Usuarios $usuario, array $filtros): Generator
    {
        $consulta = $this->listagem->com_colunas_da_lista($this->listagem->consulta($usuario, $filtros))
            ->orderByDesc('apostas.created_at')
            ->orderByDesc('apostas.id');

        foreach ($consulta->cursor() as $aposta) {
            yield $this->linha($aposta);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function linha(Apostas $aposta): array
    {
        $data = fn ($momento) => $momento?->copy()->setTimezone(FusoSistema::FUSO)->format('d/m/Y H:i');

        return [
            'codigo' => $aposta->codigo,
            'vendedor' => $aposta->clientes_id !== null ? 'Site' : $aposta->vendedor_nome,
            'cliente' => $aposta->cliente_exibido,
            'valor' => (string) $aposta->valor,
            'comissao' => (string) $aposta->comissao,
            'premio' => (string) $aposta->premio_exibido,
            'situacao' => $aposta->resultado !== ResultadoAposta::Aguardando ? $aposta->resultado->value : $aposta->situacao->value,
            'jogos' => $aposta->jogos_restantes.'/'.$aposta->jogos_total,
            'horario' => $data($aposta->created_at),
            'cancelada_em' => $data($aposta->cancelada_em),
            'cancelada_por' => $aposta->cancelada_por_nome,
        ];
    }
}
