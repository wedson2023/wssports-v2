import { formatar_valor, valor_grafico } from '../../utils/charts';
import { Bar, BarFill, Cell, Eye, HeadCell, Lines, Row, Scroll, Table } from './styles';

// tabela da Combinação de risco (spec 014, contracts/paginas §6): jogos, opções, bilhetes, valor e
// prêmio com barra proporcional ao maior prêmio; as linhas em chaves_novas ficam destacadas
export default function RiskCombinations({ combinacoes, chaves_novas = [], ao_ver_bilhetes }) {
    const maior_premio = Math.max(...combinacoes.map((combinacao) => valor_grafico(combinacao.premio)), 1);

    return (
        <Scroll>
            <Table>
                <Row $cabecalho>
                    <HeadCell>Jogos</HeadCell>
                    <HeadCell>Opções</HeadCell>
                    <HeadCell $estreita>Bilhetes</HeadCell>
                    <HeadCell $estreita>Valor</HeadCell>
                    <HeadCell>Prêmio</HeadCell>
                    <HeadCell $icone>&nbsp;</HeadCell>
                </Row>
                {combinacoes.map((combinacao) => (
                    <Row key={combinacao.chave} $nova={chaves_novas.includes(combinacao.chave)}>
                        <Cell><Lines>{combinacao.jogos.map((jogo, indice) => <span key={`${jogo}-${indice}`}>{jogo}</span>)}</Lines></Cell>
                        <Cell><Lines>{combinacao.opcoes.map((opcao, indice) => <span key={`${opcao}-${indice}`}>{opcao}</span>)}</Lines></Cell>
                        <Cell $estreita>{combinacao.quantidade_bilhetes}</Cell>
                        <Cell $estreita>{formatar_valor(combinacao.valor_apostado)}</Cell>
                        <Cell>
                            <span>{formatar_valor(combinacao.premio)}</span>
                            <Bar><BarFill style={{ width: `${(valor_grafico(combinacao.premio) / maior_premio) * 100}%` }} /></Bar>
                        </Cell>
                        <Cell $icone><Eye title="Ver bilhetes" onClick={() => ao_ver_bilhetes(combinacao)}>remove_red_eye</Eye></Cell>
                    </Row>
                ))}
            </Table>
        </Scroll>
    );
}
