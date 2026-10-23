<?php

namespace App\Services;

use App\Enums\Funcao;
use App\Models\Usuarios;
use App\Support\PlanilhaPainel;
use Illuminate\Support\Carbon;

/**
 * Planilha do caixa (spec 017, FR-030, research R-09): cabeçalho (sistema, período, tipo e nível),
 * totais e uma linha por usuário do nível ou por cliente, com as mesmas somas da tela
 * (ApuracaoCaixa e CaixaClientes) e os valores em formato de moeda. A montagem é a do
 * PlanilhaPainel, compartilhada com a planilha de apostas (spec 018).
 */
class ExportacaoCaixa
{
    /**
     * Máximo de linhas por arquivo.
     */
    private const MAXIMO_LINHAS = 5000;

    /**
     * Tamanho das páginas lidas do Caixa clientes.
     */
    private const POR_PAGINA = 100;

    private const TIPOS = ['todas' => 'Todas', 'pre_jogo' => 'Pré-jogo', 'ao_vivo' => 'Ao vivo'];

    /**
     * Colunas de texto (as demais são números) e colunas coloridas pelo sinal do valor.
     */
    private const COLUNAS_TEXTO = ['nome', 'login', 'situacao', 'telefone', 'email'];

    private const COLUNAS_SINAL = ['liquido', 'saldo_caixa', 'resultado_cassino'];

    public function __construct(
        private readonly ApuracaoCaixa $apuracao,
        private readonly CaixaClientes $clientes,
        private readonly PlanilhaPainel $planilha,
        private readonly AlcanceClientes $alcance_clientes,
    ) {}

    /**
     * Caixa da rede do foco: totais e uma linha por usuário do nível abaixo (no vendedor, ele).
     * Devolve o caminho do arquivo temporário.
     */
    public function rede(?Usuarios $foco, Carbon $inicio, Carbon $fim, string $tipo, string $de, string $ate): string
    {
        $apuracao = $this->apuracao->apurar($foco, $inicio, $fim, $tipo);
        ['funcao' => $funcao, 'linhas' => $linhas] = $this->apuracao->linhas_do_nivel($apuracao, $foco);
        usort($linhas, fn (array $a, array $b) => strcasecmp($a['nome'], $b['nome']));

        $com_caixa = $tipo === 'todas';
        $vendedores = $funcao === Funcao::Vendedor;
        $colunas = [
            'nome' => 'Nome', 'login' => 'Login', 'situacao' => 'Situação', 'apostas' => 'Apostas',
            'entradas' => 'Entradas', 'em_aberto' => 'Em aberto', 'valor_premiadas' => 'Premiadas', 'valor_perdidas' => 'Perdidas',
            'saidas' => 'Saídas', 'comissao_vendedor' => 'Comissão vendedor',
            ...($vendedores ? [] : ['comissao_gerente' => 'Comissão gerente']),
            ...($com_caixa ? ['lancamentos' => 'Lançamentos', 'retiradas' => 'Retiradas'] : []),
            'liquido' => 'Líquido',
            ...($com_caixa ? ['saldo_caixa' => 'Saldo de caixa'] : []),
            ...($com_caixa && ! $vendedores ? ['recebido_periodo' => 'Recebido no período'] : []),
        ];

        $nivel = $foco ? "{$foco->funcao()?->value} {$foco->nome}" : 'Banca';
        $cabecalho = [
            ['Caixa', $nivel],
            ['Período', PlanilhaPainel::texto_periodo($de, $ate)],
            ['Tipo de aposta', self::TIPOS[$tipo]],
        ];

        $total = ['nome' => 'Total', ...$apuracao['total']];

        return $this->gerar("Caixa - {$nivel}", $cabecalho, $colunas, array_slice($linhas, 0, self::MAXIMO_LINHAS), $total);
    }

    /**
     * Caixa clientes do período: totais e uma linha por cliente com movimento, só dos clientes da
     * rede do solicitante (spec 019, FR-003c; o Admin vê a banca toda).
     */
    public function clientes(Carbon $inicio, Carbon $fim, string $de, string $ate, Usuarios $solicitante): string
    {
        $restringir = $this->alcance_clientes->subconsulta_ids($solicitante);
        $resumo = $this->clientes->resumo($inicio, $fim, $restringir);
        $linhas = [];

        for ($pagina = 1; count($linhas) < self::MAXIMO_LINHAS; $pagina++) {
            $pagina_clientes = $this->clientes->clientes($inicio, $fim, null, 'nome', 'asc', self::POR_PAGINA, $pagina, $restringir);
            $linhas = [...$linhas, ...$pagina_clientes->items()];

            if ($pagina >= $pagina_clientes->lastPage()) {
                break;
            }
        }

        $colunas = [
            'nome' => 'Cliente', 'telefone' => 'Telefone', 'email' => 'E-mail', 'apostas' => 'Apostas',
            'entradas' => 'Entradas', 'saidas' => 'Saídas', 'encerramentos' => 'Encerramentos',
            'resultado_cassino' => 'Resultado cassino', 'bonus_convertidos' => 'Bônus convertidos',
            'depositos' => 'Depósitos', 'saques' => 'Saques', 'liquido' => 'Líquido',
        ];

        $cabecalho = [
            ['Caixa', 'Clientes'],
            ['Período', PlanilhaPainel::texto_periodo($de, $ate)],
            ['Resultado do site', (float) $resumo['resultado_site']],
            ['Esportes: saldo líquido', (float) $resumo['esportes']['saldo_liquido']],
            ['Cassino: resultado', (float) $resumo['cassino']['resultado']],
            ['Cassino: comissão dos provedores', (float) $resumo['cassino']['comissao_provedores']],
            ['Bônus concedidos', (float) $resumo['bonus']['concedidos']],
            ['Bônus convertidos', (float) $resumo['bonus']['convertidos']],
            ['Depósitos', (float) $resumo['depositos']],
            ['Saques', (float) $resumo['saques']],
        ];

        return $this->gerar('Caixa - Clientes', $cabecalho, $colunas, array_slice($linhas, 0, self::MAXIMO_LINHAS), null);
    }

    /**
     * Grava a planilha pelo PlanilhaPainel com os formatos das colunas do caixa.
     *
     * @param  list<array{0: string, 1: string|float}>  $cabecalho
     * @param  array<string, string>  $colunas
     * @param  list<array<string, mixed>>  $linhas
     * @param  array<string, mixed>|null  $total
     */
    private function gerar(string $titulo, array $cabecalho, array $colunas, array $linhas, ?array $total): string
    {
        return $this->planilha->gerar($titulo, 'Caixa', 'caixa', $cabecalho, $colunas, $linhas, $total, [
            'texto' => self::COLUNAS_TEXTO,
            'inteiro' => ['apostas'],
            'sinal' => self::COLUNAS_SINAL,
        ]);
    }
}
