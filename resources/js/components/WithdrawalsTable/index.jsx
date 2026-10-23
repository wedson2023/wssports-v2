import { Fragment, useState } from 'react';

import ActionsMenu from '../ActionsMenu';
import useTelaMobile from '../../hooks/useTelaMobile';
import { formatar_valor } from '../../utils/charts';
import { formatar_data_hora } from '../../utils/dates';
import {
    Actions, Cell, Code, Detail, Details, DetailsRow, Empty, Expand, HeadCell, Retry, Row, Scroll, Skeleton, Status, Sub, Table,
} from './styles';

// modo do saque (spec 007): baixa manual ou envio automático pelo Mercado Pago
const formas = { Manual: 'Manual', Automático: 'Mercado Pago' };

// quem decidiu, quando e como
const decisao = (saque) => {
    if (!saque.decidido_em) return '—';

    return [saque.decidido_por?.nome, formatar_data_hora(saque.decidido_em), formas[saque.modo]].filter(Boolean).join(' · ');
};

const pix = (saque) => (saque.pix?.chave ? `${saque.pix.tipo_chave}: ${saque.pix.chave}` : '—');

// colunas da fila (contracts/paginas §6): principal = sempre na linha; secundaria = só no computador
const colunas = [
    { chave: 'pix', rotulo: 'Pix', nivel: 'secundaria', texto: pix },
    { chave: 'valor', rotulo: 'Valor', nivel: 'principal', texto: (saque) => formatar_valor(saque.valor), forte: true },
    { chave: 'saldo', rotulo: 'Saldo do cliente', nivel: 'secundaria', texto: (saque) => formatar_valor(saque.cliente?.saldo ?? '0') },
    { chave: 'pedido', rotulo: 'Pedido em', nivel: 'secundaria', texto: (saque) => formatar_data_hora(saque.created_at) },
    { chave: 'decisao', rotulo: 'Decisão', nivel: 'secundaria', texto: decisao },
];

// fila de saques da tela Clientes (spec 019, FR-025 e FR-026), no padrão das listas do painel:
// cliente na primeira coluna, situação em selo colorido e, só nos pendentes, as ações do menu;
// acoes(saque) devolve os itens do menu
export default function WithdrawalsTable({ linhas, carregando, erro, ao_tentar_novamente, acoes }) {
    const [abertos, definir_abertos] = useState([]);
    const e_mobile = useTelaMobile();
    const colunas_visiveis = (e_mobile ? colunas.filter((coluna) => coluna.nivel === 'principal') : colunas).length + 3;

    const alternar = (id) => definir_abertos((atuais) => (atuais.includes(id) ? atuais.filter((item) => item !== id) : [...atuais, id]));

    if (carregando && !linhas.length) {
        return <Skeleton>{Array.from({ length: 6 }, (_, indice) => <span key={indice} />)}</Skeleton>;
    }

    if (erro && !linhas.length) {
        return (
            <Empty>
                {erro.response?.data?.message ?? 'Não foi possível carregar os saques.'}
                <Retry type="button" onClick={ao_tentar_novamente}>Tentar novamente</Retry>
            </Empty>
        );
    }

    if (!linhas.length) {
        return (
            <Empty>
                <i className="material-icons" aria-hidden="true">download</i>
                Nenhum saque encontrado. Troque a situação, a busca ou o período.
            </Empty>
        );
    }

    return (
        <Scroll $atualizando={carregando}>
            <Table>
                <thead>
                    <Row>
                        <HeadCell $codigo>Cliente</HeadCell>
                        {colunas.map((coluna) => (
                            <HeadCell key={coluna.chave} $secundaria={coluna.nivel === 'secundaria'}>{coluna.rotulo}</HeadCell>
                        ))}
                        <HeadCell>Situação</HeadCell>
                        <HeadCell $acoes><span>Ação</span></HeadCell>
                    </Row>
                </thead>
                <tbody>
                    {linhas.map((saque) => {
                        const aberto = abertos.includes(saque.id);
                        const itens = acoes(saque);

                        return (
                            <Fragment key={saque.id}>
                                <Row $aberto={aberto} onClick={(evento) => !evento.target.closest('button') && alternar(saque.id)}>
                                    <Cell>
                                        <Code>{saque.cliente?.nome ?? '—'}</Code>
                                        <Sub><span>{`ID ${saque.cliente?.id ?? '—'}`}</span></Sub>
                                    </Cell>
                                    {colunas.map((coluna) => (
                                        <Cell key={coluna.chave} $secundaria={coluna.nivel === 'secundaria'} $forte={coluna.forte}>{coluna.texto(saque)}</Cell>
                                    ))}
                                    <Cell><Status $situacao={saque.situacao}>{saque.situacao}</Status></Cell>
                                    <Cell>
                                        <Actions>
                                            {itens.length > 0 && <ActionsMenu acoes={itens} rotulo={`Ações do saque de ${saque.cliente?.nome ?? ''}`} />}
                                            <Expand
                                                type="button"
                                                title={aberto ? 'Ver menos' : 'Ver todas as informações'}
                                                aria-label={aberto ? 'Ver menos do saque' : 'Ver todas as informações do saque'}
                                                aria-expanded={aberto}
                                                onClick={() => alternar(saque.id)}
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
                                                        <span>{coluna.texto(saque)}</span>
                                                    </Detail>
                                                ))}
                                                <Detail>
                                                    <span>Titular</span>
                                                    <span>{saque.pix?.nome_titular ?? '—'}</span>
                                                </Detail>
                                                <Detail>
                                                    <span>Telefone</span>
                                                    <span>{saque.cliente?.telefone ?? '—'}</span>
                                                </Detail>
                                                <Detail>
                                                    <span>Motivo</span>
                                                    <span>{saque.motivo_recusa ?? saque.motivo_falha ?? '—'}</span>
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
