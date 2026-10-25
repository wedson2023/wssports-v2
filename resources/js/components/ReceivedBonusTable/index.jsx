import { Fragment, useState } from 'react';

import ActionsMenu from '../ActionsMenu';
import useTelaMobile from '../../hooks/useTelaMobile';
import { formatar_valor } from '../../utils/charts';
import { formatar_data_hora } from '../../utils/dates';
import {
    Actions, Bar, Cell, Code, Detail, Details, DetailsRow, Empty, Expand, HeadCell, Progress, Retry, Row, Scroll, Skeleton, Status, Sub, Table,
} from './styles';

const data_hora = (texto) => (texto ? formatar_data_hora(texto) : '—');

// "R$ 120,00 / R$ 250,00" e quanto falta; sem rollover, só o aviso
const rollover = (bonus) => {
    if (!bonus.rollover) return 'Sem rollover';

    return `${formatar_valor(bonus.rollover.apostado)} / ${formatar_valor(bonus.rollover.exigido)}`;
};

// o que mostra em destaque no cartão do celular: quanto falta em andamento, senão a situação
export const texto_falta = (bonus) => (bonus.situacao === 'Em andamento' && bonus.rollover ? `falta ${formatar_valor(bonus.rollover.falta)}` : bonus.situacao);

// colunas (contracts/paginas §7): principal = sempre na linha; secundaria = só no computador
const colunas = [
    { chave: 'promocao', rotulo: 'Promoção', nivel: 'secundaria', texto: (bonus) => bonus.promocao.nome },
    { chave: 'modalidade', rotulo: 'Modalidade', nivel: 'secundaria', texto: (bonus) => bonus.modalidade },
    { chave: 'valor', rotulo: 'Valor', nivel: 'principal', texto: (bonus) => formatar_valor(bonus.valor), forte: true },
    { chave: 'saldo', rotulo: 'Saldo bônus', nivel: 'secundaria', texto: (bonus) => formatar_valor(bonus.saldo_promocional) },
    { chave: 'recebido', rotulo: 'Recebido em', nivel: 'secundaria', texto: (bonus) => data_hora(bonus.recebido_em) },
    { chave: 'expira', rotulo: 'Expira em', nivel: 'secundaria', texto: (bonus) => data_hora(bonus.expira_em) },
];

// barra do progresso do rollover, com o "falta" embaixo
const progresso = (bonus) => (
    <>
        <span>{rollover(bonus)}</span>
        {bonus.rollover && (
            <Bar>
                <Progress value={Number(bonus.rollover.apostado)} max={Math.max(Number(bonus.rollover.exigido), 0.01)} aria-label="Progresso do rollover" />
                {bonus.situacao === 'Em andamento' && <Sub><span>{`falta ${formatar_valor(bonus.rollover.falta)}`}</span></Sub>}
            </Bar>
        )}
    </>
);

// bônus recebidos da tela Bônus (spec 020, FR-020 a FR-022): cliente na primeira coluna, progresso
// do rollover, situação em selo colorido e o menu de ações; tocar na linha abre o detalhe;
// acoes(bonus) devolve os itens do menu
export default function ReceivedBonusTable({ linhas, carregando, erro, ao_tentar_novamente, ao_abrir, acoes }) {
    const [abertos, definir_abertos] = useState([]);
    const e_mobile = useTelaMobile();
    const colunas_visiveis = (e_mobile ? colunas.filter((coluna) => coluna.nivel === 'principal') : colunas).length + 4;

    const alternar = (id) => definir_abertos((atuais) => (atuais.includes(id) ? atuais.filter((item) => item !== id) : [...atuais, id]));

    if (carregando && !linhas.length) {
        return <Skeleton>{Array.from({ length: 6 }, (_, indice) => <span key={indice} />)}</Skeleton>;
    }

    if (erro && !linhas.length) {
        return (
            <Empty>
                {erro.response?.data?.message ?? 'Não foi possível carregar os bônus.'}
                <Retry type="button" onClick={ao_tentar_novamente}>Tentar novamente</Retry>
            </Empty>
        );
    }

    if (!linhas.length) {
        return (
            <Empty>
                <i className="material-icons" aria-hidden="true">redeem</i>
                Nenhum bônus nesta situação. Troque a situação, a busca ou os filtros.
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
                        <HeadCell $secundaria>Rollover</HeadCell>
                        <HeadCell>Situação</HeadCell>
                        <HeadCell $acoes><span>Ação</span></HeadCell>
                    </Row>
                </thead>
                <tbody>
                    {linhas.map((bonus) => {
                        const aberto = abertos.includes(bonus.id);
                        const itens = acoes(bonus);

                        return (
                            <Fragment key={bonus.id}>
                                <Row $aberto={aberto} onClick={(evento) => !evento.target.closest('button') && ao_abrir(bonus)}>
                                    <Cell>
                                        <Code>{bonus.cliente.nome}</Code>
                                        <Sub><span>{`ID ${bonus.cliente.id}${bonus.cliente.excluido ? ' · excluído' : ''}`}</span></Sub>
                                    </Cell>
                                    {colunas.map((coluna) => (
                                        <Cell key={coluna.chave} $secundaria={coluna.nivel === 'secundaria'} $forte={coluna.forte}>{coluna.texto(bonus)}</Cell>
                                    ))}
                                    <Cell $secundaria>{progresso(bonus)}</Cell>
                                    <Cell><Status $situacao={bonus.situacao}>{e_mobile ? texto_falta(bonus) : bonus.situacao}</Status></Cell>
                                    <Cell>
                                        <Actions>
                                            {itens.length > 0 && <ActionsMenu acoes={itens} rotulo={`Ações do bônus de ${bonus.cliente.nome}`} />}
                                            {e_mobile && (
                                                <Expand
                                                    type="button"
                                                    title={aberto ? 'Ver menos' : 'Ver todas as informações'}
                                                    aria-label={aberto ? 'Ver menos do bônus' : 'Ver todas as informações do bônus'}
                                                    aria-expanded={aberto}
                                                    onClick={() => alternar(bonus.id)}
                                                >
                                                    <i className="material-icons" aria-hidden="true">{aberto ? 'expand_less' : 'expand_more'}</i>
                                                </Expand>
                                            )}
                                        </Actions>
                                    </Cell>
                                </Row>
                                {aberto && e_mobile && (
                                    <DetailsRow>
                                        <td colSpan={colunas_visiveis}>
                                            <Details>
                                                {colunas.filter((coluna) => coluna.nivel === 'secundaria').map((coluna) => (
                                                    <Detail key={coluna.chave} $so_celular>
                                                        <span>{coluna.rotulo}</span>
                                                        <span>{coluna.texto(bonus)}</span>
                                                    </Detail>
                                                ))}
                                                <Detail $so_celular>
                                                    <span>Rollover</span>
                                                    <span>{rollover(bonus)}</span>
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
