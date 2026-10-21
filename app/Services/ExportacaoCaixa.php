<?php

namespace App\Services;

use App\Enums\Funcao;
use App\Models\Configuracoes;
use App\Models\Usuarios;
use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Planilha do caixa (spec 017, FR-030, research R-09): cabeçalho (sistema, período, tipo e nível),
 * totais e uma linha por usuário do nível ou por cliente, com as mesmas somas da tela
 * (ApuracaoCaixa e CaixaClientes) e os valores em formato de moeda.
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

    private const FORMATO_MOEDA = '"R$" #,##0.00;-"R$" #,##0.00';

    private const TIPOS = ['todas' => 'Todas', 'pre_jogo' => 'Pré-jogo', 'ao_vivo' => 'Ao vivo'];

    /**
     * Colunas de texto (as demais são números) e colunas coloridas pelo sinal do valor.
     */
    private const COLUNAS_TEXTO = ['nome', 'login', 'situacao', 'telefone', 'email'];

    private const COLUNAS_SINAL = ['liquido', 'saldo_caixa', 'resultado_cassino'];

    /**
     * Cores da planilha (RGB): sinal do valor, títulos, rótulos, linhas alternadas, total e bordas.
     */
    private const COR_POSITIVO = '2E7D32';

    private const COR_NEGATIVO = 'C62828';

    private const COR_ZERO = '808080';

    private const COR_TITULOS = '2E2E2E';

    private const COR_ROTULO = 'F2F2F2';

    private const COR_ALTERNADA = 'F7F7F7';

    private const COR_TOTAL = 'E6E6E6';

    private const COR_BORDA = 'D0D0D0';

    public function __construct(
        private readonly ApuracaoCaixa $apuracao,
        private readonly CaixaClientes $clientes,
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
            ['Período', $this->texto_periodo($de, $ate)],
            ['Tipo de aposta', self::TIPOS[$tipo]],
        ];

        $total = ['nome' => 'Total', ...$apuracao['total']];

        return $this->gerar("Caixa - {$nivel}", $cabecalho, $colunas, array_slice($linhas, 0, self::MAXIMO_LINHAS), $total);
    }

    /**
     * Caixa clientes do período: totais e uma linha por cliente com movimento.
     */
    public function clientes(Carbon $inicio, Carbon $fim, string $de, string $ate): string
    {
        $resumo = $this->clientes->resumo($inicio, $fim);
        $linhas = [];

        for ($pagina = 1; count($linhas) < self::MAXIMO_LINHAS; $pagina++) {
            $pagina_clientes = $this->clientes->clientes($inicio, $fim, null, 'nome', 'asc', self::POR_PAGINA, $pagina);
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
            ['Período', $this->texto_periodo($de, $ate)],
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
     * Monta a planilha e grava num arquivo temporário.
     *
     * @param  list<array{0: string, 1: string|float}>  $cabecalho
     * @param  array<string, string>  $colunas
     * @param  list<array<string, mixed>>  $linhas
     * @param  array<string, mixed>|null  $total
     */
    private function gerar(string $titulo, array $cabecalho, array $colunas, array $linhas, ?array $total): string
    {
        $planilha = new Spreadsheet;
        $folha = $planilha->getActiveSheet();
        $folha->setTitle('Caixa');

        $ultima_coluna = $this->coluna(count($colunas));

        $folha->setCellValue('A1', Configuracoes::atual()->nome_sistema.' — '.$titulo);
        $folha->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $folha->getRowDimension(1)->setRowHeight(24);

        // quadro do cabeçalho: rótulos em negrito sobre cinza claro; valores em reais com a cor do sinal
        $linha_atual = 3;

        foreach ($cabecalho as [$rotulo, $valor]) {
            $folha->setCellValue("A{$linha_atual}", $rotulo);
            $folha->setCellValue("B{$linha_atual}", $valor);
            $folha->getStyle("A{$linha_atual}")->getFont()->setBold(true);
            $folha->getStyle("A{$linha_atual}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::COR_ROTULO);
            $folha->getStyle("A{$linha_atual}:B{$linha_atual}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB(self::COR_BORDA);

            if (is_float($valor)) {
                $folha->getStyle("B{$linha_atual}")->getNumberFormat()->setFormatCode(self::FORMATO_MOEDA);
                $this->colorir_sinal($folha, "B{$linha_atual}", $valor);
            }

            $linha_atual++;
        }

        // títulos das colunas: fundo escuro, texto branco, filtro e painel congelado abaixo deles
        $linha_atual++;
        $linha_titulos = $linha_atual;
        $this->escrever_linha($folha, $linha_atual, array_values($colunas), true);
        $titulos = $folha->getStyle("A{$linha_titulos}:{$ultima_coluna}{$linha_titulos}");
        $titulos->getFont()->getColor()->setRGB('FFFFFF');
        $titulos->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::COR_TITULOS);
        $titulos->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $folha->getRowDimension($linha_titulos)->setRowHeight(22);
        $folha->setAutoFilter("A{$linha_titulos}:{$ultima_coluna}{$linha_titulos}");
        $folha->freezePane('B'.($linha_titulos + 1));
        $primeira = $linha_atual + 1;

        foreach ($linhas as $posicao => $linha) {
            $linha_atual++;
            $this->escrever_valores($folha, $linha_atual, $colunas, $linha);

            // linhas alternadas, para facilitar a leitura
            if ($posicao % 2 === 1) {
                $folha->getStyle("A{$linha_atual}:{$ultima_coluna}{$linha_atual}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::COR_ALTERNADA);
            }
        }

        if ($total !== null) {
            $linha_atual++;
            $this->escrever_valores($folha, $linha_atual, $colunas, $total);
            $linha_total = $folha->getStyle("A{$linha_atual}:{$ultima_coluna}{$linha_atual}");
            $linha_total->getFont()->setBold(true);
            $linha_total->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::COR_TOTAL);
            $linha_total->getBorders()->getTop()->setBorderStyle(Border::BORDER_MEDIUM)->getColor()->setRGB(self::COR_TITULOS);
        }

        if ($linha_atual >= $primeira) {
            $folha->getStyle("A{$primeira}:{$ultima_coluna}{$linha_atual}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB(self::COR_BORDA);

            foreach (array_keys($colunas) as $indice => $chave) {
                $letra = $this->coluna($indice + 1);
                $faixa = "{$letra}{$primeira}:{$letra}{$linha_atual}";

                if ($chave === 'apostas') {
                    $folha->getStyle($faixa)->getNumberFormat()->setFormatCode('#,##0');
                } elseif (! in_array($chave, self::COLUNAS_TEXTO, true)) {
                    $folha->getStyle($faixa)->getNumberFormat()->setFormatCode(self::FORMATO_MOEDA);
                }

                // resultado: verde positivo, vermelho negativo, cinza zero
                if (in_array($chave, self::COLUNAS_SINAL, true)) {
                    for ($linha = $primeira; $linha <= $linha_atual; $linha++) {
                        $this->colorir_sinal($folha, "{$letra}{$linha}", (float) $folha->getCell("{$letra}{$linha}")->getValue());
                    }
                }
            }
        }

        foreach (range(1, count($colunas)) as $indice) {
            $folha->getColumnDimension($this->coluna($indice))->setAutoSize(true);
        }

        // o tempnam reserva um nome único; a planilha vai no mesmo nome com .xlsx e o reservado é apagado
        $reservado = tempnam(sys_get_temp_dir(), 'caixa');
        $caminho = $reservado.'.xlsx';
        (new Xlsx($planilha))->save($caminho);
        $planilha->disconnectWorksheets();
        @unlink($reservado);

        return $caminho;
    }

    /**
     * @param  list<string>  $valores
     */
    private function escrever_linha(Worksheet $folha, int $linha, array $valores, bool $negrito): void
    {
        foreach ($valores as $indice => $valor) {
            $folha->setCellValue($this->coluna($indice + 1).$linha, $valor);
        }

        if ($negrito) {
            $folha->getStyle('A'.$linha.':'.$this->coluna(count($valores)).$linha)->getFont()->setBold(true);
        }
    }

    /**
     * @param  array<string, string>  $colunas
     * @param  array<string, mixed>  $dados
     */
    private function escrever_valores(Worksheet $folha, int $linha, array $colunas, array $dados): void
    {
        foreach (array_keys($colunas) as $indice => $chave) {
            $valor = $dados[$chave] ?? null;
            $celula = $this->coluna($indice + 1).$linha;

            if ($valor === null) {
                continue;
            }

            if (in_array($chave, self::COLUNAS_TEXTO, true)) {
                $folha->setCellValueExplicit($celula, (string) $valor, DataType::TYPE_STRING);
            } else {
                $folha->setCellValue($celula, $chave === 'apostas' ? (int) $valor : (float) $valor);
            }
        }
    }

    /**
     * Cor do texto pelo sinal do valor (as mesmas da tela): verde, vermelho ou cinza; em negrito.
     */
    private function colorir_sinal(Worksheet $folha, string $celula, float $valor): void
    {
        $cor = $valor > 0 ? self::COR_POSITIVO : ($valor < 0 ? self::COR_NEGATIVO : self::COR_ZERO);

        $folha->getStyle($celula)->getFont()->setBold(true)->getColor()->setRGB($cor);
    }

    private function coluna(int $indice): string
    {
        return Coordinate::stringFromColumnIndex($indice);
    }

    private function texto_periodo(string $de, string $ate): string
    {
        $formatar = fn (string $data) => Carbon::createFromFormat('Y-m-d', $data)->format('d/m/Y');

        return $de === $ate ? $formatar($de) : $formatar($de).' a '.$formatar($ate);
    }
}
