import { Fragment, useState } from 'react';

import ActionsMenu from '../ActionsMenu';
import useTelaMobile from '../../hooks/useTelaMobile';
import { formatar_valor } from '../../utils/charts';
import { formatar_data_hora } from '../../utils/dates';
import {
    Actions, Alert, Cell, Code, Detail, Details, DetailsRow, Empty, Expand, HeadCell, Money, ProfileLink, Retry, Row, Scroll, Skeleton, Status, Sub, Table,
} from './styles';

const data_hora = (texto) => (texto ? formatar_data_hora(texto) : '—');

const percentual = (valor) => `${String(Number(valor)).replace('.', ',')}%`;

// regra vigente: "Saldo líquido · 10%" ou "A definir" (cadastro público ainda sem regra)
export const texto_ganho = (afiliado) => (afiliado.comissao ? `${afiliado.comissao.tipo_ganho} · ${percentual(afiliado.comissao.percentual)}` : 'A definir');

export const texto_ultima_prestacao = (afiliado) => (afiliado.ultima_prestacao
    ? `${data_hora(afiliado.ultima_prestacao.periodo_fim)} · ${formatar_valor(afiliado.ultima_prestacao.valor_pago)}`
    : 'Nunca');

const seguidores = (afiliado) => (afiliado.quantidade_seguidores === null || afiliado.quantidade_seguidores === undefined
    ? 'Não informado'
    : afiliado.quantidade_seguidores.toLocaleString('pt-BR'));

// trecho do "sobre você" na linha; o texto completo fica na gaveta
const trecho = (texto, tamanho = 80) => (!texto ? '—' : (texto.length > tamanho ? `${texto.slice(0, tamanho)}…` : texto));

const perfil = (afiliado) => (afiliado.link_perfil
    ? <ProfileLink href={afiliado.link_perfil} target="_blank" rel="noopener noreferrer" onClick={(evento) => evento.stopPropagation()}>Abrir perfil</ProfileLink>
    : 'Não informado');

const contato = (afiliado) => [afiliado.email, afiliado.telefone].filter(Boolean).join(' · ') || '—';

// colunas de cada aba (contracts/paginas §1): principal = sempre na linha; secundaria = só no
// computador (no celular, ao expandir)
const colunas_aprovados = (e_admin, lixeira) => [
    { chave: 'gerente', rotulo: 'Gerente', nivel: 'secundaria', texto: (afiliado) => afiliado.gerente?.nome ?? (e_admin ? <Alert>Sem gerente responsável</Alert> : '—') },
    { chave: 'ganho', rotulo: 'Ganho', nivel: 'secundaria', texto: texto_ganho },
    { chave: 'cadastros', rotulo: 'Cadastros', nivel: 'secundaria', texto: (afiliado) => afiliado.totais_periodo?.cadastros ?? 0 },
    { chave: 'depositos', rotulo: 'Depósitos', nivel: 'secundaria', texto: (afiliado) => formatar_valor(afiliado.totais_periodo?.depositos) },
    { chave: 'saques', rotulo: 'Saques', nivel: 'secundaria', texto: (afiliado) => formatar_valor(afiliado.totais_periodo?.saques) },
    { chave: 'comissao', rotulo: 'Comissão', nivel: 'secundaria', texto: (afiliado) => formatar_valor(afiliado.totais_periodo?.comissao) },
    { chave: 'entradas', rotulo: 'Entradas', nivel: 'secundaria', texto: (afiliado) => formatar_valor(afiliado.entradas) },
    { chave: 'receber', rotulo: 'A receber', nivel: 'principal', forte: true, texto: (afiliado) => <Money $negativo={String(afiliado.valor_a_receber ?? '').startsWith('-')}>{formatar_valor(afiliado.valor_a_receber)}</Money> },
    { chave: 'prestacao', rotulo: 'Última prestação', nivel: 'secundaria', texto: texto_ultima_prestacao },
    ...(lixeira ? [{ chave: 'excluido', rotulo: 'Excluído em', nivel: 'secundaria', texto: (afiliado) => data_hora(afiliado.deleted_at) }] : []),
];

const colunas_analise = [
    { chave: 'contato', rotulo: 'Contato', nivel: 'secundaria', texto: contato },
    { chave: 'local', rotulo: 'Cidade/UF', nivel: 'secundaria', texto: (afiliado) => [afiliado.cidade, afiliado.estado].filter(Boolean).join('/') || '—' },
    { chave: 'rede', rotulo: 'Rede', nivel: 'principal', texto: (afiliado) => afiliado.rede_social ?? 'Não informado' },
    { chave: 'seguidores', rotulo: 'Seguidores', nivel: 'principal', forte: true, texto: seguidores },
    { chave: 'perfil', rotulo: 'Perfil', nivel: 'secundaria', texto: perfil },
    { chave: 'sobre', rotulo: 'Sobre', nivel: 'secundaria', texto: (afiliado) => trecho(afiliado.sobre) },
    { chave: 'cadastro', rotulo: 'Cadastrado em', nivel: 'secundaria', texto: (afiliado) => data_hora(afiliado.created_at) },
    { chave: 'recusa', rotulo: 'Recusa', nivel: 'secundaria', texto: (afiliado) => (afiliado.situacao === 'Recusado'
        ? `${afiliado.decidido_por?.nome ?? '—'} · ${data_hora(afiliado.decidido_em)}${afiliado.motivo_recusa ? ` · ${afiliado.motivo_recusa}` : ''}`
        : '—') },
];

// afiliados da tela Afiliados (spec 022, FR-005, FR-006 e FR-021), no padrão das listas do painel:
// nome e código na primeira coluna, situação em selo colorido e o menu de ações; tocar na linha
// abre a gaveta; no celular, cartões com o restante ao expandir. acoes(afiliado) devolve os itens
export default function AffiliatesTable({ aba, linhas, carregando, erro, e_admin, ao_tentar_novamente, ao_abrir, acoes }) {
    const [abertos, definir_abertos] = useState([]);
    const e_mobile = useTelaMobile();
    const colunas = aba === 'analise' ? colunas_analise : colunas_aprovados(e_admin, aba === 'lixeira');
    const colunas_visiveis = (e_mobile ? colunas.filter((coluna) => coluna.nivel === 'principal') : colunas).length + 3;

    const alternar = (id) => definir_abertos((atuais) => (atuais.includes(id) ? atuais.filter((item) => item !== id) : [...atuais, id]));

    if (carregando && !linhas.length) {
        return <Skeleton>{Array.from({ length: 6 }, (_, indice) => <span key={indice} />)}</Skeleton>;
    }

    if (erro && !linhas.length) {
        return (
            <Empty>
                {erro.response?.data?.message ?? 'Não foi possível carregar os afiliados.'}
                <Retry type="button" onClick={ao_tentar_novamente}>Tentar novamente</Retry>
            </Empty>
        );
    }

    if (!linhas.length) {
        return (
            <Empty>
                <i className="material-icons" aria-hidden="true">handshake</i>
                {aba === 'analise' ? 'Nenhum cadastro para analisar.' : 'Nenhum afiliado encontrado. Troque a busca ou os filtros.'}
            </Empty>
        );
    }

    return (
        <Scroll $atualizando={carregando}>
            <Table>
                <thead>
                    <Row>
                        <HeadCell $codigo>Afiliado</HeadCell>
                        {colunas.map((coluna) => (
                            <HeadCell key={coluna.chave} $secundaria={coluna.nivel === 'secundaria'}>{coluna.rotulo}</HeadCell>
                        ))}
                        <HeadCell>Situação</HeadCell>
                        <HeadCell $acoes><span>Ação</span></HeadCell>
                    </Row>
                </thead>
                <tbody>
                    {linhas.map((afiliado) => {
                        const aberto = abertos.includes(afiliado.id);
                        const itens = acoes(afiliado);

                        return (
                            <Fragment key={afiliado.id}>
                                <Row $aberto={aberto} onClick={(evento) => !evento.target.closest('button, a') && ao_abrir(afiliado)}>
                                    <Cell>
                                        <Code>{afiliado.nome}</Code>
                                        <Sub><span>{aba === 'analise' ? `ID ${afiliado.id} · ${afiliado.login}` : `ID ${afiliado.id} · ${afiliado.codigo}`}</span></Sub>
                                    </Cell>
                                    {colunas.map((coluna) => (
                                        <Cell key={coluna.chave} $secundaria={coluna.nivel === 'secundaria'} $forte={coluna.forte}>{coluna.texto(afiliado)}</Cell>
                                    ))}
                                    <Cell><Status $situacao={afiliado.situacao}>{afiliado.situacao}</Status></Cell>
                                    <Cell>
                                        <Actions>
                                            {itens.length > 0 && <ActionsMenu acoes={itens} rotulo={`Ações do afiliado ${afiliado.nome}`} />}
                                            {e_mobile && (
                                                <Expand
                                                    type="button"
                                                    title={aberto ? 'Ver menos' : 'Ver todas as informações'}
                                                    aria-label={aberto ? 'Ver menos do afiliado' : 'Ver todas as informações do afiliado'}
                                                    aria-expanded={aberto}
                                                    onClick={() => alternar(afiliado.id)}
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
                                                        <span>{coluna.texto(afiliado)}</span>
                                                    </Detail>
                                                ))}
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
