import { formatar_valor } from '../../utils/charts';
import { Cell, Code, Empty, HeadCell, Money, Position, Retry, Row, Scroll, Skeleton, Sub, Table, Tag } from './styles';

// colunas de cada critério (FR-028); principal = fica no celular
const colunas_do_criterio = {
    depositos: [
        { rotulo: 'Qtd. depósitos', texto: (linha) => linha.depositos.quantidade },
        { rotulo: 'Depósitos', texto: (linha) => formatar_valor(linha.depositos.valor), principal: true },
    ],
    saques: [
        { rotulo: 'Qtd. saques', texto: (linha) => linha.saques.quantidade },
        { rotulo: 'Saques pagos', texto: (linha) => formatar_valor(linha.saques.valor), principal: true },
    ],
    valor_apostado: [
        { rotulo: 'Esportes', texto: (linha) => formatar_valor(linha.valor_apostado.esportes) },
        { rotulo: 'Cassino', texto: (linha) => formatar_valor(linha.valor_apostado.cassino) },
        { rotulo: 'Total apostado', texto: (linha) => formatar_valor(linha.valor_apostado.total), principal: true },
    ],
    resultado: [
        {
            rotulo: 'Resultado para a banca',
            texto: (linha) => <Money $sinal={Math.sign(Number(linha.resultado))}>{formatar_valor(linha.resultado)}</Money>,
            principal: true,
        },
    ],
};

// ranking da tela Clientes (spec 019, FR-027 e FR-028): posição (1º a 3º em destaque), cliente,
// saldo, saldo líquido, ativo e as colunas do critério; tocar na linha abre o detalhe
export default function RankingTable({ linhas: todas, criterio, carregando, erro, ao_tentar_novamente, ao_abrir }) {
    const colunas = colunas_do_criterio[criterio];
    // ao trocar o critério, as linhas do critério anterior ainda não têm o bloco do novo: ficam de
    // fora até a lista nova chegar (sem isso a tela quebrava)
    const linhas = todas.filter((linha) => linha[criterio] !== null && linha[criterio] !== undefined);

    if (carregando && !linhas.length) {
        return <Skeleton>{Array.from({ length: 6 }, (_, indice) => <span key={indice} />)}</Skeleton>;
    }

    if (erro && !linhas.length) {
        return (
            <Empty>
                {erro.response?.data?.message ?? 'Não foi possível carregar o ranking.'}
                <Retry type="button" onClick={ao_tentar_novamente}>Tentar novamente</Retry>
            </Empty>
        );
    }

    if (!linhas.length) {
        return (
            <Empty>
                <i className="material-icons" aria-hidden="true">filter_list</i>
                Nenhum cliente com movimento no período.
            </Empty>
        );
    }

    return (
        <Scroll $atualizando={carregando}>
            <Table>
                <thead>
                    <Row>
                        <HeadCell>Posição</HeadCell>
                        <HeadCell $codigo>Cliente</HeadCell>
                        <HeadCell $secundaria>Saldo</HeadCell>
                        <HeadCell $secundaria>Saldo líquido</HeadCell>
                        <HeadCell $secundaria>Ativo</HeadCell>
                        {colunas.map((coluna) => <HeadCell key={coluna.rotulo} $secundaria={!coluna.principal}>{coluna.rotulo}</HeadCell>)}
                    </Row>
                </thead>
                <tbody>
                    {linhas.map((linha) => (
                        <Row key={linha.id} onClick={() => ao_abrir(linha)}>
                            <Cell><Position $destaque={linha.posicao <= 3}>{`${linha.posicao}º`}</Position></Cell>
                            <Cell>
                                <Code>{linha.nome ?? '—'}</Code>
                                <Sub>
                                    <span>{`ID ${linha.id}`}</span>
                                    {linha.excluido && <Tag>excluído</Tag>}
                                </Sub>
                            </Cell>
                            <Cell $secundaria>{formatar_valor(linha.saldo ?? '0')}</Cell>
                            <Cell $secundaria><Money $sinal={Math.sign(Number(linha.saldo_liquido))}>{formatar_valor(linha.saldo_liquido)}</Money></Cell>
                            <Cell $secundaria>{linha.ativo ? 'Sim' : 'Não'}</Cell>
                            {colunas.map((coluna) => <Cell key={coluna.rotulo} $secundaria={!coluna.principal} $forte={coluna.principal}>{coluna.texto(linha)}</Cell>)}
                        </Row>
                    ))}
                </tbody>
            </Table>
        </Scroll>
    );
}
