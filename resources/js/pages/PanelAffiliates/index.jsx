import { useEffect, useRef, useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import Swal from 'sweetalert2';

import ActiveFilters from '../../components/ActiveFilters';
import AffiliateApprovalModal from '../../components/AffiliateApprovalModal';
import AffiliateDeleteModal from '../../components/AffiliateDeleteModal';
import AffiliateDrawer from '../../components/AffiliateDrawer';
import AffiliateFormModal from '../../components/AffiliateFormModal';
import AffiliateRejectModal from '../../components/AffiliateRejectModal';
import AffiliateRestoreModal from '../../components/AffiliateRestoreModal';
import AffiliateRuleModal from '../../components/AffiliateRuleModal';
import AffiliateSettlementModal from '../../components/AffiliateSettlementModal';
import AffiliatesFilters, { etiquetas_afiliados, remover_filtro_afiliados } from '../../components/AffiliatesFilters';
import AffiliatesTable from '../../components/AffiliatesTable';
import ClientDrawer from '../../components/ClientDrawer';
import ConfirmModal from '../../components/ConfirmModal';
import Pagination from '../../components/Pagination';
import PanelShell from '../../components/PanelShell';
import PanelTabs from '../../components/PanelTabs';
import Spinner from '../../components/Spinner';
import StatCard from '../../components/StatCard';
import useAfiliadosPainel, { situacoes_por_aba } from '../../hooks/useAfiliadosPainel';
import usePainel from '../../hooks/usePainel';
import useTelaMobile from '../../hooks/useTelaMobile';
import api from '../../utils/api';
import { alerta_erro, confirmar } from '../../utils/alerts';
import {
    Area, Cards, Chip, Chips, Choices, Error, HeaderActions, Loading, Retry, Search, SearchBox, Section, Skeleton, Tabs, Tool, Top,
} from './styles';

// espera depois da última tecla antes de buscar
const ATRASO_BUSCA = 400;

// itens do menu de três pontinhos de cada afiliado (FR-009)
const acoes_afiliado = {
    ver: { icone: 'visibility', titulo: 'Ver detalhe', tom: '#1e88e5' },
    editar: { icone: 'edit', titulo: 'Editar', tom: '#1e88e5' },
    inativar: { icone: 'block', titulo: 'Inativar', tom: '#757575' },
    ativar: { icone: 'check_circle', titulo: 'Ativar', tom: '#43a047' },
    regra: { icone: 'percent', titulo: 'Trocar regra', tom: '#1e88e5' },
    prestar: { icone: 'payments', titulo: 'Prestar contas', tom: '#43a047' },
    link: { icone: 'link', titulo: 'Copiar link', tom: '#1e88e5' },
    excluir: { icone: 'delete_outline', titulo: 'Excluir', tom: '#e53935' },
    aprovar: { icone: 'how_to_reg', titulo: 'Aprovar', tom: '#43a047' },
    recusar: { icone: 'person_off', titulo: 'Recusar', tom: '#e53935' },
    restaurar: { icone: 'restore_from_trash', titulo: 'Restaurar', tom: '#43a047' },
};

// o detalhe do cliente aberto daqui é só de consulta: sem as permissões de alterar
const permissoes_de_alterar = ['clientes.editar', 'clientes.editar_configuracoes'];

// aviso curto no canto, sem bloquear a tela
const avisar = (texto) => Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: texto, showConfirmButton: false, timer: 3000 });

// tela "Afiliados" do painel (spec 022; a antiga "Lista de filiados"): cartões, abas Ativos, Em
// análise (só Admin) e Lixeira, busca, filtros, gaveta do afiliado e as ações (cadastrar, editar,
// ativar/inativar, trocar regra, prestar contas, copiar link, aprovar, recusar, excluir e restaurar),
// com o alcance pela rede aplicado no servidor
export default function PanelAffiliates() {
    const { nome_sistema, tema } = usePage().props;
    const { usuario: logado, carregando } = usePainel();
    const pode = (permissao) => Boolean(logado?.permissoes.includes(permissao));
    const afiliados = useAfiliadosPainel({ habilitado: pode('afiliados.listar') });
    const { filtros, mudar, trocar_aba, lista, resumo, recarregar, token } = afiliados;
    const e_mobile = useTelaMobile();
    const [busca, definir_busca] = useState(filtros.busca);
    const [filtros_abertos, definir_filtros_abertos] = useState(false);
    const [afiliado_aberto, definir_afiliado_aberto] = useState(null);
    const [cliente_aberto, definir_cliente_aberto] = useState(null);
    const [versao_detalhe, definir_versao_detalhe] = useState(0);
    const [edicao, definir_edicao] = useState(null);
    const [aprovacao, definir_aprovacao] = useState(null);
    const [recusa, definir_recusa] = useState(null);
    const [troca_regra, definir_troca_regra] = useState(null);
    const [prestacao, definir_prestacao] = useState(null);
    const [exclusao, definir_exclusao] = useState(null);
    const [conflito, definir_conflito] = useState(null);
    const [link_manual, definir_link_manual] = useState(null);
    const espera = useRef(null);

    const autorizacao = { headers: { Authorization: `Bearer ${token}` } };
    const e_admin = logado?.funcao === 'Admin';
    const ve_analise = e_admin && pode('afiliados.alterar_situacao');

    // sem acesso, volta para a Dashboard
    useEffect(() => {
        if (logado && !pode('afiliados.listar')) router.visit('/painel');
    }, [logado]);

    // aba fora do alcance (Em análise sem ser Admin, Lixeira sem restaurar) volta para Ativos
    useEffect(() => {
        if (!logado) return;

        if ((filtros.aba === 'analise' && !ve_analise) || (filtros.aba === 'lixeira' && !pode('afiliados.restaurar'))) trocar_aba('ativos');
    }, [logado, filtros.aba]);

    useEffect(() => () => window.clearTimeout(espera.current), []);

    if (carregando || !logado || !pode('afiliados.listar')) {
        return (
            <>
                <Head title={nome_sistema} />
                <Loading><Spinner tamanho={40} cor={tema.temas} /></Loading>
            </>
        );
    }

    const abas = [
        { chave: 'ativos', rotulo: 'Ativos', icone: 'group' },
        ve_analise && { chave: 'analise', rotulo: 'Em análise', icone: 'hourglass_top', contador: resumo.dados?.em_analise ?? undefined },
        pode('afiliados.restaurar') && { chave: 'lixeira', rotulo: 'Lixeira', icone: 'delete_outline' },
    ].filter(Boolean);

    const buscar = (texto) => {
        definir_busca(texto);
        window.clearTimeout(espera.current);
        espera.current = window.setTimeout(() => mudar({ busca: texto }), ATRASO_BUSCA);
    };

    // depois de cada ação: aviso, lista, cartões e gaveta atualizados sem perder a página (FR-036)
    const concluir = (mensagem) => {
        if (mensagem) avisar(mensagem);

        recarregar();
        definir_versao_detalhe((atual) => atual + 1);
    };

    // fecha o modal da ação e conclui
    const ao_concluir = (fechar) => (mensagem) => {
        fechar(null);
        concluir(mensagem);
    };

    const alterar_situacao = async (afiliado) => {
        const inativar = afiliado.situacao === 'Ativo';
        const texto = inativar
            ? `Inativar ${afiliado.nome}? Ele não entra mais no painel do afiliado; os clientes continuam ligados e gerando comissão.`
            : `Ativar ${afiliado.nome}? Ele volta a entrar no painel e a ligar clientes novos pelo link.`;

        if (!(await confirmar(texto, inativar ? '#c40808' : '#43a047'))) return;

        try {
            await api.patch(`/afiliados/${afiliado.id}/situacao`, { situacao: inativar ? 'Inativo' : 'Ativo' }, autorizacao);
            concluir(inativar ? 'Afiliado inativado.' : 'Afiliado ativado.');
        } catch (erro) {
            alerta_erro(erro);
            recarregar();
        }
    };

    // link de indicação pelo código público (FR-031); sem acesso à área de transferência, mostra o
    // link para copiar à mão
    const copiar_link = async (afiliado) => {
        const link = `${window.location.origin}/?user=${afiliado.codigo}`;
        const aviso = afiliado.situacao === 'Ativo' ? 'Link copiado.' : 'Link copiado. Ele só liga clientes quando o afiliado estiver Ativo.';

        try {
            await navigator.clipboard.writeText(link);
            avisar(aviso);
        } catch {
            definir_link_manual(link);
        }
    };

    const restaurar = async (afiliado) => {
        if (!(await confirmar(`Restaurar ${afiliado.nome}? Ele volta com a situação que tinha.`, '#43a047'))) return;

        try {
            await api.post(`/afiliados/${afiliado.id}/restaurar`, {}, autorizacao);
            concluir('Afiliado restaurado.');
        } catch (erro) {
            const campos = Object.keys(erro.response?.data?.errors ?? {});

            if (erro.response?.status === 422 && campos.length) definir_conflito({ afiliado, campos });
            else alerta_erro(erro);
        }
    };

    // ações pela permissão, pela aba e pela situação (FR-009); as mesmas no menu e na gaveta
    const acoes_do_afiliado = (afiliado, com_ver = true) => {
        const na_lixeira = Boolean(afiliado.deleted_at);
        const aprovado = ['Ativo', 'Inativo'].includes(afiliado.situacao);
        const pendente = ['Em análise', 'Recusado'].includes(afiliado.situacao);

        return [
            com_ver && pode('afiliados.consultar') && 'ver',
            !na_lixeira && aprovado && pode('afiliados.editar') && 'editar',
            !na_lixeira && aprovado && pode('afiliados.alterar_situacao') && (afiliado.situacao === 'Ativo' ? 'inativar' : 'ativar'),
            !na_lixeira && aprovado && pode('afiliados.editar_comissao') && 'regra',
            !na_lixeira && aprovado && pode('afiliados.prestar_contas') && pode('afiliados.consultar') && 'prestar',
            !na_lixeira && aprovado && 'link',
            !na_lixeira && pendente && ve_analise && 'aprovar',
            !na_lixeira && afiliado.situacao === 'Em análise' && ve_analise && 'recusar',
            !na_lixeira && pode('afiliados.excluir') && 'excluir',
            na_lixeira && pode('afiliados.restaurar') && 'restaurar',
        ].filter(Boolean).map((chave) => ({
            chave,
            ...acoes_afiliado[chave],
            ao_clicar: () => {
                if (chave === 'ver') definir_afiliado_aberto(afiliado);
                if (chave === 'editar') definir_edicao(afiliado);
                if (chave === 'inativar' || chave === 'ativar') alterar_situacao(afiliado);
                if (chave === 'regra') definir_troca_regra(afiliado);
                if (chave === 'prestar') definir_prestacao(afiliado);
                if (chave === 'link') copiar_link(afiliado);
                if (chave === 'aprovar') definir_aprovacao(afiliado);
                if (chave === 'recusar') definir_recusa(afiliado);
                if (chave === 'excluir') definir_exclusao(afiliado);
                if (chave === 'restaurar') restaurar(afiliado);
            },
        }));
    };

    const etiquetas = etiquetas_afiliados(filtros);
    const painel_filtros = filtros_abertos && !e_mobile;
    const titulo_aba = abas.find((aba) => aba.chave === filtros.aba)?.rotulo ?? 'Afiliados';
    const situacoes = situacoes_por_aba[filtros.aba] ?? [];

    const filtros_tela = (
        <AffiliatesFilters
            aberto={filtros_abertos}
            filtros={filtros}
            token={token}
            funcao={logado.funcao}
            ao_aplicar={mudar}
            ao_fechar={() => definir_filtros_abertos(false)}
        />
    );

    // cartões (FR-004): Em análise só para o Admin
    const cartoes = () => {
        const dados = resumo.dados;

        if (resumo.carregando && !dados) return <Skeleton>{Array.from({ length: 4 }, (_, indice) => <span key={indice} />)}</Skeleton>;

        if (resumo.erro && !dados) {
            return (
                <Error>
                    {resumo.erro.response?.data?.message ?? 'Não foi possível carregar os totais.'}
                    <Retry type="button" onClick={recarregar}>Tentar novamente</Retry>
                </Error>
            );
        }

        if (!dados) return null;

        return (
            <Cards>
                <StatCard icone="group" rotulo="Total de afiliados" valor={dados.total_afiliados} dinheiro={false} titulo="Afiliados ativos e inativos do filtro." />
                {dados.em_analise !== null && <StatCard icone="hourglass_top" rotulo="Em análise" valor={dados.em_analise} dinheiro={false} titulo="Cadastros pelo site esperando aprovação." />}
                <StatCard icone="trending_up" rotulo="Comissão do período" valor={dados.comissao_periodo} titulo="Comissão gerada no período escolhido." />
                <StatCard icone="payments" rotulo="A receber" valor={dados.a_receber} titulo="Lançamentos ainda não prestados." />
            </Cards>
        );
    };

    return (
        <>
            <Head title={nome_sistema} />
            <PanelShell usuario={logado} titulo={`Afiliados · ${titulo_aba} · total ${lista.total.toLocaleString('pt-BR')}`}>
                <Section>
                    <Top $com_filtros={painel_filtros} $com_etiquetas={etiquetas.length > 0}>
                        <Area $area="abas">
                            <Tabs>
                                <PanelTabs
                                    abas={abas}
                                    ativa={filtros.aba}
                                    ao_trocar={(aba) => {
                                        definir_busca('');
                                        definir_filtros_abertos(false);
                                        trocar_aba(aba);
                                    }}
                                />
                            </Tabs>
                        </Area>
                        <Area $area="ferramentas">
                            <HeaderActions>
                                <Tool type="button" aria-expanded={filtros_abertos} onClick={() => definir_filtros_abertos((aberto) => !aberto)}>
                                    <i className="material-icons" aria-hidden="true">filter_alt</i>
                                    {etiquetas.length ? `Filtros (${etiquetas.length})` : 'Filtros'}
                                </Tool>
                                {pode('afiliados.cadastrar') && (
                                    <Tool type="button" onClick={() => definir_edicao({})}>
                                        <i className="material-icons" aria-hidden="true">add</i>
                                        Cadastrar
                                    </Tool>
                                )}
                                <Tool type="button" title="Atualizar" aria-label="Atualizar" $compacto onClick={recarregar}>
                                    <i className="material-icons" aria-hidden="true">refresh</i>
                                </Tool>
                            </HeaderActions>
                        </Area>
                        {painel_filtros && <Area $area="filtros">{filtros_tela}</Area>}
                        {etiquetas.length > 0 && (
                            <Area $area="etiquetas">
                                <ActiveFilters etiquetas={etiquetas} ao_remover={(chave) => mudar(remover_filtro_afiliados(filtros, chave))} />
                            </Area>
                        )}
                        <Area $area="escolhas">
                            <Choices>
                                {situacoes.length > 0 && (
                                    <Chips role="group" aria-label="Situação">
                                        {situacoes.map((situacao) => (
                                            <Chip key={situacao.rotulo} type="button" aria-pressed={filtros.situacao === situacao.chave} onClick={() => mudar({ situacao: situacao.chave })}>{situacao.rotulo}</Chip>
                                        ))}
                                    </Chips>
                                )}
                            </Choices>
                        </Area>
                        <Area $area="busca">
                            <SearchBox>
                                <i className="material-icons" aria-hidden="true">search</i>
                                <Search
                                    type="search"
                                    value={busca}
                                    placeholder="Nome, login ou código"
                                    aria-label="Buscar afiliado"
                                    onChange={(evento) => buscar(evento.target.value)}
                                    onKeyDown={(evento) => {
                                        if (evento.key !== 'Enter') return;

                                        window.clearTimeout(espera.current);
                                        mudar({ busca });
                                    }}
                                />
                            </SearchBox>
                        </Area>
                    </Top>

                    {/* no celular os filtros abrem num modal, fora da grade */}
                    {e_mobile && filtros_tela}

                    {cartoes()}
                    <AffiliatesTable
                        aba={filtros.aba}
                        linhas={lista.linhas}
                        carregando={lista.carregando}
                        erro={lista.erro}
                        e_admin={e_admin}
                        ao_tentar_novamente={recarregar}
                        ao_abrir={(afiliado) => pode('afiliados.consultar') && definir_afiliado_aberto(afiliado)}
                        acoes={acoes_do_afiliado}
                    />
                    <Pagination pagina={filtros.pagina} ultima={lista.ultima} ao_trocar={(pagina) => mudar({ pagina })} />
                </Section>
            </PanelShell>

            <AffiliateDrawer
                afiliado={afiliado_aberto}
                versao={versao_detalhe}
                token={token}
                acoes={(afiliado) => acoes_do_afiliado(afiliado, false)}
                ao_copiar_link={copiar_link}
                ao_trocar_regra={pode('afiliados.editar_comissao') ? definir_troca_regra : null}
                ao_ver_cliente={pode('clientes.listar') ? (cliente) => definir_cliente_aberto({ id: cliente.id, nome: cliente.nome }) : null}
                ao_fechar={() => definir_afiliado_aberto(null)}
            />
            <ClientDrawer
                cliente={cliente_aberto}
                versao={versao_detalhe}
                token={token}
                pode={(permissao) => !permissoes_de_alterar.includes(permissao) && pode(permissao)}
                acoes={() => []}
                ao_fechar={() => definir_cliente_aberto(null)}
                ao_alterar={concluir}
            />
            <AffiliateFormModal afiliado={edicao} token={token} usuario={logado} pode={pode} ao_fechar={() => definir_edicao(null)} ao_salvar={ao_concluir(definir_edicao)} />
            <AffiliateApprovalModal afiliado={aprovacao} token={token} ao_fechar={() => definir_aprovacao(null)} ao_concluir={ao_concluir(definir_aprovacao)} />
            <AffiliateRejectModal afiliado={recusa} token={token} ao_fechar={() => definir_recusa(null)} ao_concluir={ao_concluir(definir_recusa)} />
            <AffiliateRuleModal afiliado={troca_regra} token={token} ao_fechar={() => definir_troca_regra(null)} ao_concluir={ao_concluir(definir_troca_regra)} />
            <AffiliateSettlementModal afiliado={prestacao} token={token} ao_fechar={() => definir_prestacao(null)} ao_concluir={ao_concluir(definir_prestacao)} />
            <AffiliateDeleteModal
                afiliado={exclusao}
                token={token}
                ao_fechar={() => definir_exclusao(null)}
                ao_concluir={(mensagem) => {
                    definir_exclusao(null);
                    definir_afiliado_aberto(null);
                    concluir(mensagem);
                }}
            />
            <AffiliateRestoreModal conflito={conflito} token={token} ao_fechar={() => definir_conflito(null)} ao_concluir={ao_concluir(definir_conflito)} />
            <ConfirmModal
                aberto={Boolean(link_manual)}
                titulo="Link de indicação"
                icone="link"
                mensagem={(
                    <>
                        Copie o link abaixo:
                        <br />
                        <input type="text" readOnly value={link_manual ?? ''} onFocus={(evento) => evento.target.select()} style={{ width: '100%', marginTop: 8 }} />
                    </>
                )}
                ao_fechar={() => definir_link_manual(null)}
            />
        </>
    );
}
