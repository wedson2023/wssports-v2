import { useEffect, useState } from 'react';

import Backdrop from '../Backdrop';
import ModalHeader from '../ModalHeader';
import Pagination from '../Pagination';
import PanelTabs from '../PanelTabs';
import Spinner from '../Spinner';
import { texto_ganho } from '../AffiliatesTable';
import useVoltarFecha from '../../hooks/useVoltarFecha';
import api from '../../utils/api';
import { alerta_erro } from '../../utils/alerts';
import { formatar_valor } from '../../utils/charts';
import { formatar_data_hora } from '../../utils/dates';
import {
    Actions, Body, Button, Chip, Chips, Container, Data, DataItem, Empty, Entry, EntryLine, Filters, Input, Label, List, Loading, Money, ProfileLink, Retry, Summary, Title, Top,
} from './styles';

const abas = [
    { chave: 'dados', rotulo: 'Dados', icone: 'person' },
    { chave: 'extrato', rotulo: 'Extrato', icone: 'receipt_long' },
    { chave: 'clientes', rotulo: 'Clientes', icone: 'group' },
    { chave: 'prestacoes', rotulo: 'Prestações', icone: 'payments' },
    { chave: 'regras', rotulo: 'Regras', icone: 'percent' },
];

// rota de cada aba de lista (contracts/paginas §3), 30 por página
const rota_da_aba = { extrato: 'lancamentos', clientes: 'clientes', prestacoes: 'prestacoes', regras: 'comissoes' };
const POR_PAGINA = 30;

const situacoes_extrato = [
    { chave: '', rotulo: 'Todos' },
    { chave: 'aberto', rotulo: 'Em aberto' },
    { chave: 'prestado', rotulo: 'Prestados' },
];

const data_hora = (texto) => (texto ? formatar_data_hora(texto) : '—');
const nao_informado = (valor) => (valor === null || valor === undefined || valor === '' ? 'Não informado' : valor);
const negativo = (valor) => String(valor ?? '').startsWith('-');
const percentual = (valor) => `${String(Number(valor)).replace('.', ',')}%`;

const lista_inicial = { linhas: [], ultima: 1, soma: null, carregando: true, erro: null };

// detalhe do afiliado (spec 022, FR-024 a FR-026, contracts/paginas §3; o antigo "acessar
// transações"): gaveta à direita (tela cheia no celular) com os dados e as listas do extrato, dos
// clientes, das prestações e das regras, cada uma carregada ao abrir; versao muda quando uma ação de
// fora altera o afiliado. acoes(afiliado) devolve os atalhos; ao_ver_cliente abre o detalhe do cliente
export default function AffiliateDrawer({ afiliado, versao, token, acoes, ao_copiar_link, ao_trocar_regra, ao_ver_cliente, ao_fechar }) {
    const aberto = Boolean(afiliado);
    const [aba, definir_aba] = useState('dados');
    const [detalhe, definir_detalhe] = useState(null);
    const [pagina, definir_pagina] = useState(1);
    const [lista, definir_lista] = useState(lista_inicial);
    const [extrato, definir_extrato] = useState({ situacao: '', de: '', ate: '' });
    const [recarga, definir_recarga] = useState(0);

    const cabecalhos = { headers: { Authorization: `Bearer ${token}` } };

    useVoltarFecha(aberto, ao_fechar);

    useEffect(() => {
        if (!aberto) return;

        definir_aba('dados');
        definir_extrato({ situacao: '', de: '', ate: '' });
    }, [afiliado?.id]);

    useEffect(() => {
        if (!aberto) return undefined;

        let ativo = true;
        api.get(`/afiliados/${afiliado.id}`, cabecalhos)
            .then(({ data }) => ativo && definir_detalhe(data.data))
            .catch((erro) => {
                if (!ativo) return;

                alerta_erro(erro);
                ao_fechar();
            });

        return () => {
            ativo = false;
        };
    }, [afiliado?.id, versao]);

    useEffect(() => {
        if (!aberto) return undefined;

        const ao_teclar = (evento) => { if (evento.key === 'Escape') ao_fechar(); };
        window.addEventListener('keydown', ao_teclar);

        return () => window.removeEventListener('keydown', ao_teclar);
    }, [aberto, ao_fechar]);

    // trocar de aba ou de filtro do extrato volta para a página 1
    useEffect(() => definir_pagina(1), [aba, extrato.situacao, extrato.de, extrato.ate]);

    useEffect(() => {
        if (!aberto || aba === 'dados') return undefined;

        let ativo = true;
        const filtros_extrato = aba === 'extrato' ? {
            ...(extrato.situacao ? { situacao: extrato.situacao } : {}),
            ...(extrato.de && extrato.ate ? { de: extrato.de, ate: extrato.ate } : {}),
        } : {};

        definir_lista((atual) => ({ ...atual, carregando: true, erro: null }));
        api.get(`/afiliados/${afiliado.id}/${rota_da_aba[aba]}`, { params: { ...filtros_extrato, page: pagina, por_pagina: POR_PAGINA }, ...cabecalhos })
            .then(({ data }) => ativo && definir_lista({ linhas: data.data, ultima: data.meta.last_page, soma: data.meta.soma ?? null, carregando: false, erro: null }))
            .catch((erro) => ativo && definir_lista((atual) => ({ ...atual, carregando: false, erro })));

        return () => {
            ativo = false;
        };
    }, [afiliado?.id, aba, pagina, extrato.situacao, extrato.de, extrato.ate, versao, recarga]);

    const dados = detalhe && detalhe.id === afiliado?.id ? detalhe : null;

    const linha = (rotulo, valor) => (
        <DataItem key={rotulo}>
            <span>{rotulo}</span>
            <span>{valor ?? '—'}</span>
        </DataItem>
    );

    const conteudo_lista = (renderizar, vazio) => {
        if (lista.carregando && !lista.linhas.length) return <Loading><Spinner tamanho={30} cor="currentColor" /></Loading>;

        if (lista.erro && !lista.linhas.length) {
            return (
                <Empty>
                    {lista.erro.response?.data?.message ?? 'Não foi possível carregar.'}
                    <Retry type="button" onClick={() => definir_recarga((atual) => atual + 1)}>Tentar novamente</Retry>
                </Empty>
            );
        }

        if (!lista.linhas.length) return <Empty>{vazio}</Empty>;

        return (
            <>
                <List>{lista.linhas.map(renderizar)}</List>
                <Pagination pagina={pagina} ultima={lista.ultima} ao_trocar={definir_pagina} />
            </>
        );
    };

    const aba_dados = () => (
        <>
            <Title>Dados pessoais</Title>
            <Data>
                {linha('ID', dados.id)}
                {linha('Login', dados.login)}
                {linha('E-mail', nao_informado(dados.email))}
                {linha('CPF', nao_informado(dados.cpf))}
                {linha('Telefone', dados.telefone ? `+${dados.ddi} ${dados.telefone}` : 'Não informado')}
                {linha('Cidade/UF', [dados.cidade, dados.estado].filter(Boolean).join('/') || 'Não informado')}
                {linha('Endereço', nao_informado(dados.endereco))}
                {linha('PIX', dados.meio_pagamento ? `${dados.meio_pagamento.pix_tipo_chave} · ${dados.meio_pagamento.pix_chave}` : 'Não informado')}
            </Data>

            <Title>Perfil</Title>
            <Data>
                {linha('Rede social', nao_informado(dados.rede_social))}
                {linha('Seguidores', dados.quantidade_seguidores === null ? 'Não informado' : dados.quantidade_seguidores.toLocaleString('pt-BR'))}
                {linha('Perfil', dados.link_perfil ? <ProfileLink href={dados.link_perfil} target="_blank" rel="noopener noreferrer">{dados.link_perfil}</ProfileLink> : 'Não informado')}
            </Data>
            <Summary>{dados.sobre || 'Sem texto "Sobre você".'}</Summary>

            <Title>Gestão</Title>
            <Data>
                {linha('Origem', dados.origem_cadastro)}
                {linha('Gerente', dados.gerente?.nome ?? 'Sem gerente responsável')}
                {linha('Cadastrado por', dados.cadastrado_por?.nome ?? (dados.origem_cadastro === 'Público' ? 'Cadastro pelo site' : '—'))}
                {dados.decidido_por && linha(dados.situacao === 'Recusado' ? 'Recusado por' : 'Aprovado por', `${dados.decidido_por.nome} · ${data_hora(dados.decidido_em)}`)}
                {dados.motivo_recusa && linha('Motivo da recusa', dados.motivo_recusa)}
                {linha('Cadastro', data_hora(dados.created_at))}
                {linha('Alterado em', data_hora(dados.updated_at))}
                {dados.deleted_at && linha('Excluído em', data_hora(dados.deleted_at))}
            </Data>

            <Actions>
                {acoes(dados).map((acao) => (
                    <Button key={acao.chave} type="button" $tom={acao.tom} onClick={acao.ao_clicar}>
                        <i className="material-icons" aria-hidden="true">{acao.icone}</i>
                        {acao.titulo}
                    </Button>
                ))}
            </Actions>
        </>
    );

    const aba_extrato = () => (
        <>
            <Filters>
                <Chips role="group" aria-label="Situação dos lançamentos">
                    {situacoes_extrato.map((item) => (
                        <Chip key={item.chave} type="button" aria-pressed={extrato.situacao === item.chave} onClick={() => definir_extrato((atual) => ({ ...atual, situacao: item.chave }))}>{item.rotulo}</Chip>
                    ))}
                </Chips>
                <Label>
                    De
                    <Input type="date" value={extrato.de} onChange={(evento) => definir_extrato((atual) => ({ ...atual, de: evento.target.value }))} />
                </Label>
                <Label>
                    Até
                    <Input type="date" value={extrato.ate} onChange={(evento) => definir_extrato((atual) => ({ ...atual, ate: evento.target.value }))} />
                </Label>
            </Filters>
            {lista.soma !== null && (
                <Summary>
                    {'Soma do filtro: '}
                    <Money $negativo={negativo(lista.soma)}>{formatar_valor(lista.soma)}</Money>
                </Summary>
            )}
            {conteudo_lista((lancamento) => (
                <Entry key={lancamento.id}>
                    <EntryLine>
                        <strong>{lancamento.evento}</strong>
                        <Money $negativo={negativo(lancamento.valor)}>{formatar_valor(lancamento.valor)}</Money>
                    </EntryLine>
                    <EntryLine>
                        <span>{lancamento.cliente ? `${lancamento.cliente.id} · ${lancamento.cliente.nome}` : '—'}</span>
                        <span>{`${formatar_valor(lancamento.base)} × ${percentual(lancamento.percentual)}`}</span>
                    </EntryLine>
                    <EntryLine>
                        <span>{data_hora(lancamento.ocorrido_em)}</span>
                        <span>{lancamento.prestado ? 'Prestado' : 'Em aberto'}</span>
                    </EntryLine>
                </Entry>
            ), 'Nenhum lançamento no filtro.')}
        </>
    );

    const aba_clientes = () => conteudo_lista((cliente) => (
        <Entry key={cliente.id}>
            <EntryLine>
                <strong>{`${cliente.id} · ${cliente.nome}`}</strong>
                <span>{formatar_valor(cliente.total_depositado)}</span>
            </EntryLine>
            <EntryLine>
                <span>{[cliente.telefone, cliente.email].filter(Boolean).join(' · ') || '—'}</span>
                <span>{data_hora(cliente.created_at)}</span>
            </EntryLine>
            {ao_ver_cliente && (
                <EntryLine>
                    <Retry type="button" onClick={() => ao_ver_cliente(cliente)}>Ver cliente</Retry>
                </EntryLine>
            )}
        </Entry>
    ), 'Nenhum cliente indicado.');

    const aba_prestacoes = () => conteudo_lista((prestacao) => (
        <Entry key={prestacao.id}>
            <EntryLine>
                <strong>{data_hora(prestacao.periodo_fim)}</strong>
                <span>{`Pago ${formatar_valor(prestacao.valor_pago)}`}</span>
            </EntryLine>
            <EntryLine>
                <span>{`Apurado ${formatar_valor(prestacao.valor_apurado)} · ${prestacao.quantidade_lancamentos} lançamentos`}</span>
                <span>{prestacao.usuario?.nome ?? '—'}</span>
            </EntryLine>
            <EntryLine>
                <span>{`Desde ${data_hora(prestacao.periodo_inicio)}`}</span>
                <span>{prestacao.observacao ?? ''}</span>
            </EntryLine>
        </Entry>
    ), 'Nenhuma prestação de contas ainda.');

    const aba_regras = () => (
        <>
            {ao_trocar_regra && (
                <Actions>
                    <Button type="button" onClick={() => ao_trocar_regra(dados)}>
                        <i className="material-icons" aria-hidden="true">percent</i>
                        Trocar regra
                    </Button>
                </Actions>
            )}
            {conteudo_lista((regra) => (
                <Entry key={regra.id}>
                    <EntryLine>
                        <strong>{`${regra.tipo_ganho} · ${percentual(regra.percentual)}`}</strong>
                        <span>{regra.vigente_ate ? 'Encerrada' : 'Vigente'}</span>
                    </EntryLine>
                    <EntryLine>
                        <span>{`${data_hora(regra.vigente_desde)} — ${regra.vigente_ate ? data_hora(regra.vigente_ate) : 'agora'}`}</span>
                        <span>{regra.usuario?.nome ?? '—'}</span>
                    </EntryLine>
                </Entry>
            ), 'O afiliado ainda não tem regra de ganho.')}
        </>
    );

    const conteudo = { dados: aba_dados, extrato: aba_extrato, clientes: aba_clientes, prestacoes: aba_prestacoes, regras: aba_regras };

    return (
        <>
            <Backdrop visivel={aberto} ao_fechar={ao_fechar} camada={45} />
            <Container $visivel={aberto} role="dialog" aria-modal="true" aria-label={afiliado ? `Afiliado ${afiliado.nome}` : 'Afiliado'}>
                {aberto && (
                    <>
                        <ModalHeader icone="handshake" titulo={afiliado.nome} descricao="Dados, extrato, clientes, prestações e regras do afiliado." ao_fechar={ao_fechar} />
                        {dados && (
                            <Top>
                                <span>
                                    {`Código ${dados.codigo} · ${dados.situacao} · ${texto_ganho(dados)}`}
                                    {ao_copiar_link && !dados.deleted_at && (
                                        <Retry type="button" onClick={() => ao_copiar_link(dados)}>Copiar link</Retry>
                                    )}
                                </span>
                                <span>
                                    {'A receber: '}
                                    <Money $negativo={negativo(dados.valor_a_receber)}>{formatar_valor(dados.valor_a_receber)}</Money>
                                </span>
                            </Top>
                        )}
                        <PanelTabs abas={abas} ativa={aba} ao_trocar={definir_aba} />
                        <Body>
                            {!dados && <Loading><Spinner tamanho={30} cor="currentColor" /></Loading>}
                            {dados && conteudo[aba]()}
                        </Body>
                    </>
                )}
            </Container>
        </>
    );
}
