<?php

namespace App\Support;

use App\Models\Configuracoes;
use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Conditional;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Montagem das planilhas do painel (Caixa, spec 017, e Apostas, spec 018, research R-08): título
 * com o nome do sistema, quadro do cabeçalho, títulos das colunas em fundo escuro com filtro e
 * painel congelado, linhas alternadas, total destacado, valores em moeda e colunas coloridas pelo
 * sinal. Grava num arquivo temporário único por pedido.
 */
class PlanilhaPainel
{
    private const FORMATO_MOEDA = '"R$" #,##0.00;-"R$" #,##0.00';

    private const FORMATO_INTEIRO = '#,##0';

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

    /**
     * Monta a planilha e grava num arquivo temporário; devolve o caminho.
     *
     * @param  list<array{0: string, 1: string|float}>  $cabecalho  rótulo e valor (float = moeda)
     * @param  array<string, string>  $colunas  chave => título
     * @param  iterable<array<string, mixed>>  $linhas
     * @param  array<string, mixed>|null  $total
     * @param  array{texto?: list<string>, inteiro?: list<string>, sinal?: list<string>}  $formatos  colunas de texto, de inteiro e coloridas pelo sinal; as demais são moeda
     * @param  array<string, int>|null  $larguras  largura fixa por coluna, para planilhas grandes (sem ela, cada coluna se ajusta ao conteúdo)
     */
    public function gerar(string $titulo, string $nome_aba, string $prefixo_arquivo, array $cabecalho, array $colunas, iterable $linhas, ?array $total, array $formatos, ?array $larguras = null): string
    {
        $texto = $formatos['texto'] ?? [];
        $inteiro = $formatos['inteiro'] ?? [];
        $sinal = $formatos['sinal'] ?? [];
        // planilha grande: linhas alternadas por formatação condicional (uma regra só, no lugar de um
        // estilo por linha) e larguras fixas (o ajuste ao conteúdo percorre todas as células)
        $grande = $larguras !== null;

        $planilha = new Spreadsheet;
        $folha = $planilha->getActiveSheet();
        $folha->setTitle($nome_aba);

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
        $posicao = 0;

        foreach ($linhas as $linha) {
            $linha_atual++;
            $this->escrever_valores($folha, $linha_atual, $colunas, $linha, $texto, $inteiro);

            // linhas alternadas, para facilitar a leitura
            if (! $grande && $posicao % 2 === 1) {
                $folha->getStyle("A{$linha_atual}:{$ultima_coluna}{$linha_atual}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::COR_ALTERNADA);
            }

            $posicao++;
        }

        if ($total !== null) {
            $linha_atual++;
            $this->escrever_valores($folha, $linha_atual, $colunas, $total, $texto, $inteiro);
            $linha_total = $folha->getStyle("A{$linha_atual}:{$ultima_coluna}{$linha_atual}");
            $linha_total->getFont()->setBold(true);
            $linha_total->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::COR_TOTAL);
            $linha_total->getBorders()->getTop()->setBorderStyle(Border::BORDER_MEDIUM)->getColor()->setRGB(self::COR_TITULOS);
        }

        if ($linha_atual >= $primeira) {
            $folha->getStyle("A{$primeira}:{$ultima_coluna}{$linha_atual}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB(self::COR_BORDA);

            if ($grande) {
                $alternada = new Conditional;
                $alternada->setConditionType(Conditional::CONDITION_EXPRESSION)->addCondition("MOD(ROW()-{$primeira},2)=1");
                $alternada->getStyle()->getFill()->setFillType(Fill::FILL_SOLID)->getEndColor()->setRGB(self::COR_ALTERNADA);
                $folha->getStyle("A{$primeira}:{$ultima_coluna}{$linha_atual}")->setConditionalStyles([$alternada]);
            }

            foreach (array_keys($colunas) as $indice => $chave) {
                $letra = $this->coluna($indice + 1);
                $faixa = "{$letra}{$primeira}:{$letra}{$linha_atual}";

                if (in_array($chave, $inteiro, true)) {
                    $folha->getStyle($faixa)->getNumberFormat()->setFormatCode(self::FORMATO_INTEIRO);
                } elseif (! in_array($chave, $texto, true)) {
                    $folha->getStyle($faixa)->getNumberFormat()->setFormatCode(self::FORMATO_MOEDA);
                }

                // resultado: verde positivo, vermelho negativo, cinza zero
                if (in_array($chave, $sinal, true)) {
                    for ($linha = $primeira; $linha <= $linha_atual; $linha++) {
                        $this->colorir_sinal($folha, "{$letra}{$linha}", (float) $folha->getCell("{$letra}{$linha}")->getValue());
                    }
                }
            }
        }

        foreach (array_keys($colunas) as $indice => $chave) {
            $dimensao = $folha->getColumnDimension($this->coluna($indice + 1));
            $grande ? $dimensao->setWidth($larguras[$chave] ?? 14) : $dimensao->setAutoSize(true);
        }

        // o tempnam reserva um nome único; a planilha vai no mesmo nome com .xlsx e o reservado é apagado
        $reservado = tempnam(sys_get_temp_dir(), $prefixo_arquivo);
        $caminho = $reservado.'.xlsx';
        (new Xlsx($planilha))->save($caminho);
        $planilha->disconnectWorksheets();
        @unlink($reservado);

        return $caminho;
    }

    /**
     * Período por extenso: "21/10/2026" ou "14/10/2026 a 21/10/2026".
     */
    public static function texto_periodo(string $de, string $ate): string
    {
        $formatar = fn (string $data) => Carbon::createFromFormat('Y-m-d', $data)->format('d/m/Y');

        return $de === $ate ? $formatar($de) : $formatar($de).' a '.$formatar($ate);
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
     * @param  list<string>  $texto
     * @param  list<string>  $inteiro
     */
    private function escrever_valores(Worksheet $folha, int $linha, array $colunas, array $dados, array $texto, array $inteiro): void
    {
        foreach (array_keys($colunas) as $indice => $chave) {
            $valor = $dados[$chave] ?? null;
            $celula = $this->coluna($indice + 1).$linha;

            if ($valor === null) {
                continue;
            }

            if (in_array($chave, $texto, true)) {
                $folha->setCellValueExplicit($celula, (string) $valor, DataType::TYPE_STRING);
            } else {
                $folha->setCellValue($celula, in_array($chave, $inteiro, true) ? (int) $valor : (float) $valor);
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
}
