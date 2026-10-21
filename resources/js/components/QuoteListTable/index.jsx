import ActionsMenu from '../ActionsMenu';
import Pagination from '../Pagination';
import {
    Action, Actions, Cell, Empty, HeadCell, MenuCell, Retry, Row, Scroll, Skeleton, Sub, Table,
} from './styles';

// lista da tela "Cotações" (campeonatos, jogos e Regras ativas), no padrão da UsersTable (spec 015):
// tabela no computador; no celular só a coluna principal (com o subtítulo embaixo), o selo e os três
// pontinhos; textos longos cortados. colunas = [{ chave, titulo, valor(item), principal?, sub?(item),
// selo?, secundaria? }]; acoes(item) = [{ chave, icone, titulo, tom, ao_clicar }]
export default function QuoteListTable({
    colunas, itens, carregando, erro, ao_tentar_novamente, acoes, vazio = 'Nada encontrado.', pagina, ultima, ao_trocar_pagina,
}) {
    if (carregando && !itens.length) {
        return <Skeleton>{Array.from({ length: 6 }, (_, indice) => <span key={indice} />)}</Skeleton>;
    }

    if (erro && !itens.length) {
        return (
            <Empty>
                {erro.response?.data?.message ?? 'Não foi possível carregar a lista.'}
                <Retry type="button" onClick={ao_tentar_novamente}>Tentar novamente</Retry>
            </Empty>
        );
    }

    if (!itens.length) return <Empty>{vazio}</Empty>;

    return (
        <>
            <Scroll $atualizando={carregando}>
                <Table>
                    <thead>
                        <Row>
                            {colunas.map((coluna) => (
                                <HeadCell key={coluna.chave} $secundaria={coluna.secundaria} $estreita={coluna.selo}>{coluna.titulo}</HeadCell>
                            ))}
                            <HeadCell $estreita $acoes>Ação</HeadCell>
                        </Row>
                    </thead>
                    <tbody>
                        {itens.map((item) => {
                            const acoes_item = acoes(item);

                            return (
                                <Row key={item.chave ?? item.id}>
                                    {colunas.map((coluna) => (
                                        <Cell key={coluna.chave} $secundaria={coluna.secundaria} $principal={coluna.principal}>
                                            {coluna.principal ? (
                                                <>
                                                    <span title={typeof coluna.valor(item) === 'string' ? coluna.valor(item) : undefined}>{coluna.valor(item)}</span>
                                                    {coluna.sub && <Sub>{coluna.sub(item)}</Sub>}
                                                </>
                                            ) : coluna.valor(item)}
                                        </Cell>
                                    ))}
                                    <Cell>
                                        <Actions>
                                            {acoes_item.map((acao) => (
                                                <Action key={acao.chave} type="button" title={acao.titulo} aria-label={acao.titulo} onClick={acao.ao_clicar} $tom={acao.tom}>
                                                    <i className="material-icons" aria-hidden="true">{acao.icone}</i>
                                                </Action>
                                            ))}
                                        </Actions>
                                        <MenuCell>
                                            <ActionsMenu acoes={acoes_item} />
                                        </MenuCell>
                                    </Cell>
                                </Row>
                            );
                        })}
                    </tbody>
                </Table>
            </Scroll>
            <Pagination pagina={pagina} ultima={ultima} ao_trocar={ao_trocar_pagina} />
        </>
    );
}
