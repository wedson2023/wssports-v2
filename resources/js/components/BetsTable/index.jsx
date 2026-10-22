import { Fragment, useState } from 'react';

import ActionsMenu from '../ActionsMenu';
import useTelaMobile from '../../hooks/useTelaMobile';
import { formatar_valor } from '../../utils/charts';
import { formatar_data_hora, formatar_dia_mes, formatar_hora } from '../../utils/dates';
import {
    Actions, Cell, Code, Detail, Details, DetailsRow, Empty, Expand, HeadCell, Retry, Row, Scroll, Skeleton, Status, Sub, Table, Tag,
} from './styles';

const situacoes_vendedor = { inativo: 'inativo', excluido: 'excluído' };

// "20/10 07:43", como a coluna HORÁRIO do antigo
const horario = (data) => `${formatar_dia_mes(data)} ${formatar_hora(data)}`;

const nome_vendedor = (linha) => (linha.origem === 'site' ? 'Site' : linha.vendedor?.nome ?? '—');

const cancelada = (linha) => (linha.cancelada_em ? `${formatar_data_hora(linha.cancelada_em)}${linha.cancelada_por ? ` · ${linha.cancelada_por}` : ''}` : '—');

// colunas da lista (contracts/paginas §5): principal = sempre na linha; secundaria = na linha só no
// computador (no celular, nos detalhes ao expandir)
const colunas_da_aba = (aba) => [
    { chave: 'vendedor', rotulo: 'Vendedor', nivel: 'secundaria', texto: nome_vendedor },
    { chave: 'valor', rotulo: 'Valor', nivel: 'secundaria', texto: (linha) => formatar_valor(linha.valor) },
    { chave: 'comissao', rotulo: 'Comissão', nivel: 'secundaria', texto: (linha) => formatar_valor(linha.comissao) },
    { chave: 'premio', rotulo: 'Prêmio', nivel: 'principal', texto: (linha) => formatar_valor(linha.premio), forte: true },
    aba === 'canceladas'
        ? { chave: 'cancelada', rotulo: 'Cancelada', nivel: 'secundaria', texto: cancelada }
        : { chave: 'jogos', rotulo: 'Jogos', nivel: 'secundaria', texto: (linha) => `${linha.jogos_restantes}/${linha.jogos_total}`, dica: 'Jogos ainda sem resultado / jogos da aposta' },
    { chave: 'horario', rotulo: 'Horário', nivel: 'secundaria', texto: (linha) => horario(linha.criada_em) },
];

// lista de apostas do painel (spec 018, FR-007, FR-008, FR-027), no padrão da lista do caixa: código
// e cliente na primeira coluna, situação em selo colorido, tocar na linha abre o bilhete, três
// pontinhos com as ações e o botão de expandir com todas as informações (no celular, as colunas
// secundárias vão para lá). acoes(linha) devolve os itens do menu
export default function BetsTable({ linhas, aba, carregando, erro, ao_tentar_novamente, ao_abrir, acoes }) {
    const [abertos, definir_abertos] = useState([]);
    const e_mobile = useTelaMobile();
    const colunas = colunas_da_aba(aba);
    const colunas_visiveis = (e_mobile ? colunas.filter((coluna) => coluna.nivel === 'principal') : colunas).length + 3;

    const alternar = (codigo) => definir_abertos((atuais) => (atuais.includes(codigo) ? atuais.filter((item) => item !== codigo) : [...atuais, codigo]));

    if (carregando && !linhas.length) {
        return <Skeleton>{Array.from({ length: 6 }, (_, indice) => <span key={indice} />)}</Skeleton>;
    }

    if (erro && !linhas.length) {
        return (
            <Empty>
                {erro.response?.data?.message ?? 'Não foi possível carregar a lista de apostas.'}
                <Retry type="button" onClick={ao_tentar_novamente}>Tentar novamente</Retry>
            </Empty>
        );
    }

    if (!linhas.length) {
        return (
            <Empty>
                <i className="material-icons" aria-hidden="true">receipt_long</i>
                Nenhuma aposta encontrada. Troque o período ou os filtros.
            </Empty>
        );
    }

    return (
        <Scroll $atualizando={carregando}>
            <Table>
                <thead>
                    <Row>
                        <HeadCell $codigo>Código</HeadCell>
                        {colunas.map((coluna) => (
                            <HeadCell key={coluna.chave} $secundaria={coluna.nivel === 'secundaria'} title={coluna.dica ?? coluna.rotulo}>{coluna.rotulo}</HeadCell>
                        ))}
                        <HeadCell>Situação</HeadCell>
                        <HeadCell $acoes><span>Ação</span></HeadCell>
                    </Row>
                </thead>
                <tbody>
                    {linhas.map((linha) => {
                        const aberto = abertos.includes(linha.codigo);

                        return (
                            <Fragment key={linha.codigo}>
                                {/* tocar na linha (fora dos botões) abre o bilhete */}
                                <Row $aberto={aberto} onClick={(evento) => !evento.target.closest('button') && ao_abrir(linha)}>
                                    <Cell>
                                        <Code title={`Ver o bilhete ${linha.codigo}`}>{linha.codigo.toUpperCase()}</Code>
                                        <Sub title={linha.cliente ?? ''}>
                                            <span>{linha.cliente ?? '—'}</span>
                                            {linha.forma_pagamento && linha.forma_pagamento !== 'Dinheiro' && <Tag>{linha.forma_pagamento}</Tag>}
                                            {situacoes_vendedor[linha.vendedor?.situacao] && <Tag>{`vendedor ${situacoes_vendedor[linha.vendedor.situacao]}`}</Tag>}
                                        </Sub>
                                    </Cell>
                                    {colunas.map((coluna) => (
                                        <Cell key={coluna.chave} $secundaria={coluna.nivel === 'secundaria'} $forte={coluna.forte}>{coluna.texto(linha)}</Cell>
                                    ))}
                                    <Cell><Status $situacao={linha.situacao_exibida}>{linha.situacao_exibida}</Status></Cell>
                                    <Cell>
                                        <Actions>
                                            <ActionsMenu acoes={acoes(linha)} rotulo={`Ações da aposta ${linha.codigo}`} />
                                            <Expand
                                                type="button"
                                                title={aberto ? 'Ver menos' : 'Ver todas as informações'}
                                                aria-label={aberto ? `Ver menos da aposta ${linha.codigo}` : `Ver todas as informações da aposta ${linha.codigo}`}
                                                aria-expanded={aberto}
                                                onClick={() => alternar(linha.codigo)}
                                            >
                                                <i className="material-icons" aria-hidden="true">{aberto ? 'expand_less' : 'expand_more'}</i>
                                            </Expand>
                                        </Actions>
                                    </Cell>
                                </Row>
                                {aberto && (
                                    <DetailsRow>
                                        <td colSpan={colunas_visiveis}>
                                            <Details>
                                                {colunas.filter((coluna) => coluna.nivel === 'secundaria').map((coluna) => (
                                                    <Detail key={coluna.chave} $so_celular>
                                                        <span>{coluna.rotulo}</span>
                                                        <span>{coluna.texto(linha)}</span>
                                                    </Detail>
                                                ))}
                                                <Detail>
                                                    <span>Tipo</span>
                                                    <span>{linha.tipo}</span>
                                                </Detail>
                                                <Detail>
                                                    <span>Forma de pagamento</span>
                                                    <span>{linha.forma_pagamento ?? '—'}</span>
                                                </Detail>
                                                <Detail>
                                                    <span>Feita em</span>
                                                    <span>{formatar_data_hora(linha.criada_em)}</span>
                                                </Detail>
                                            </Details>
                                        </td>
                                    </DetailsRow>
                                )}
                            </Fragment>
                        );
                    })}
                </tbody>
            </Table>
        </Scroll>
    );
}
