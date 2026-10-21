import { useEffect, useMemo, useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import Swal from 'sweetalert2';

import AudiencePicker from '../../components/AudiencePicker';
import PanelShell from '../../components/PanelShell';
import PanelTabs from '../../components/PanelTabs';
import QuoteEditorModal from '../../components/QuoteEditorModal';
import QuoteGrid from '../../components/QuoteGrid';
import QuoteHistoryModal from '../../components/QuoteHistoryModal';
import QuoteListTable from '../../components/QuoteListTable';
import QuotePreview from '../../components/QuotePreview';
import RuleBadge from '../../components/RuleBadge';
import SaveBar from '../../components/SaveBar';
import Spinner from '../../components/Spinner';
import useGradeCotacoes from '../../hooks/useGradeCotacoes';
import useListaCotacoes from '../../hooks/useListaCotacoes';
import useNomesCotacoes from '../../hooks/useNomesCotacoes';
import usePainel from '../../hooks/usePainel';
import useSessao from '../../hooks/useSessao';
import api from '../../utils/api';
import { alerta_erro, confirmar } from '../../utils/alerts';
import { formatar_dia_mes, formatar_hora } from '../../utils/dates';
import {
    Bar, Columns, EmptyState, Filters, Flag, Info, Loading, Notice, Section, Segmented, Select, SearchBox, Search, Switch, Tools,
} from './styles';

// abas da tela e a permissão de cada uma (spec 016, FR-001/FR-002)
const abas_tela = [
    { chave: 'publico', rotulo: 'Público', icone: 'groups', permissoes: ['porcentagens_vendedores.editar', 'porcentagens_clientes.editar'] },
    { chave: 'campeonatos', rotulo: 'Campeonatos', icone: 'emoji_events', permissoes: ['porcentagens_campeonatos.editar'] },
    { chave: 'jogos', rotulo: 'Jogos', icone: 'sports_soccer', permissoes: ['porcentagens_confrontos.editar'] },
    { chave: 'tetos', rotulo: 'Tetos', icone: 'vertical_align_top', permissoes: ['confrontos_teto_cotacoes.editar'] },
    { chave: 'regras', rotulo: 'Regras ativas', icone: 'fact_check', permissoes: [] },
];

const todas_permissoes = abas_tela.flatMap((aba) => aba.permissoes);

// rótulos e ícones de cada camada em Regras ativas
const camadas = {
    vendedores: { rotulo: 'Público (usuário)', icone: 'badge' },
    clientes: { rotulo: 'Público (site)', icone: 'public' },
    campeonatos: { rotulo: 'Campeonato', icone: 'emoji_events' },
    confrontos: { rotulo: 'Jogo', icone: 'sports_soccer' },
    tetos: { rotulo: 'Teto', icone: 'vertical_align_top' },
};

const tipos = { pre_jogo: 'Pré-jogo', ao_vivo: 'Ao vivo' };

// aviso curto no canto, sem bloquear a tela
const avisar = (icone, texto) => Swal.fire({ toast: true, position: 'top-end', icon: icone, title: texto, showConfirmButton: false, timer: 2500 });

// erros 422 ("valores.odd1") nos campos da grade
const erros_dos_campos = (erro) => Object.fromEntries(
    Object.entries(erro.response?.data?.errors ?? {})
        .filter(([campo]) => campo.startsWith('valores.'))
        .map(([campo, mensagens]) => [campo.split('.')[1], mensagens[0]]),
);

// aba da URL (?aba=)
const aba_da_url = () => new URLSearchParams(window.location.search).get('aba');

// tela "Cotações" do painel (spec 016): abas Público, Campeonatos, Jogos, Tetos e Regras ativas, com
// a grade por mercado, a prévia do cálculo, o cadeado por cotação e o histórico de cada regra
export default function PanelQuotes() {
    const { nome_sistema, tema } = usePage().props;
    const cor = tema.temas;
    const { sessao } = useSessao();
    const token = sessao?.token;
    const { usuario: logado, carregando } = usePainel();
    const nomes = useNomesCotacoes();
    const cabecalhos = { Authorization: `Bearer ${token}` };

    const pode = (permissao) => Boolean(logado?.permissoes.includes(permissao));
    const abas = useMemo(
        () => (logado ? abas_tela.filter((aba) => (aba.permissoes.length ? aba.permissoes.some(pode) : todas_permissoes.some(pode))) : []),
        [logado],
    );
    const [aba, definir_aba] = useState(null);

    // Público
    const grade_publico = useGradeCotacoes('porcentagem');
    const [publico, definir_publico] = useState(null);
    const [tipo, definir_tipo] = useState('pre_jogo');
    const [dados_publico, definir_dados_publico] = useState(null);
    const [foco, definir_foco] = useState('odd1');
    const [salvando, definir_salvando] = useState(false);
    const [recarga, definir_recarga] = useState(0);

    // Tetos
    const grade_teto = useGradeCotacoes('teto');
    const [tetos_carregados, definir_tetos_carregados] = useState(false);

    // listas e modais
    const [filtro_pais, definir_filtro_pais] = useState('');
    const [paises, definir_paises] = useState([]);
    const [so_ajuste, definir_so_ajuste] = useState(false);
    const [dia, definir_dia] = useState('hoje');
    const [camada_filtro, definir_camada_filtro] = useState('');
    const [editando, definir_editando] = useState(null);
    const [historico, definir_historico] = useState(null);

    const campeonatos = useListaCotacoes('/cotacoes/campeonatos', { pais: filtro_pais || undefined, com_ajuste: so_ajuste ? 1 : undefined }, aba === 'campeonatos');
    const jogos = useListaCotacoes('/cotacoes/confrontos', { dia, com_ajuste: so_ajuste ? 1 : undefined }, aba === 'jogos');
    const regras = useListaCotacoes('/cotacoes/regras-ativas', { camada: camada_filtro || undefined }, aba === 'regras');

    // sem nenhuma permissão de cotação, volta para a Dashboard
    useEffect(() => {
        if (!logado) return;

        if (!abas.length) {
            router.visit('/painel');
            return;
        }

        const pedida = aba_da_url();
        definir_aba(abas.some((item) => item.chave === pedida) ? pedida : abas[0].chave);
    }, [logado]);

    // países do filtro de campeonatos
    useEffect(() => {
        if (aba !== 'campeonatos' || paises.length) return;

        api.get('/cotacoes/paises', { headers: cabecalhos }).then(({ data }) => definir_paises(data.paises)).catch(() => {});
    }, [aba]);

    // porcentagens do público escolhido (usuário: com a soma dos superiores e os cadeados)
    useEffect(() => {
        if (!publico) return undefined;

        let ativo = true;
        definir_dados_publico(null);

        const pedido = publico.tipo === 'usuario'
            ? api.get(`/cotacoes/usuarios/${publico.id}`, { headers: cabecalhos })
            : api.get('/porcentagens-clientes', { params: { clientes_id: publico.tipo === 'cliente' ? publico.id : undefined }, headers: cabecalhos });

        pedido
            .then(({ data }) => ativo && definir_dados_publico(data))
            .catch((erro) => ativo && alerta_erro(erro));

        return () => {
            ativo = false;
        };
    }, [publico?.tipo, publico?.id]);

    useEffect(() => {
        if (dados_publico) grade_publico.carregar(dados_publico[tipo] ?? {});
    }, [dados_publico, tipo]);

    // cadeados do tipo em edição: o de um superior (não destrancável) prevalece
    const cadeados_publico = useMemo(() => {
        const mapa = {};

        (dados_publico?.cadeados ?? []).filter((cadeado) => cadeado.tipo === tipo).forEach((cadeado) => {
            if (!mapa[cadeado.codigo] || !cadeado.pode_destrancar) mapa[cadeado.codigo] = cadeado;
        });

        return mapa;
    }, [dados_publico, tipo]);

    useEffect(() => {
        grade_publico.definir_trancados(Object.fromEntries(Object.entries(cadeados_publico).filter(([, cadeado]) => !cadeado.pode_destrancar)));
    }, [cadeados_publico]);

    // tetos ao abrir a aba
    useEffect(() => {
        if (aba !== 'tetos' || tetos_carregados) return;

        api.get('/confrontos-teto-cotacoes', { headers: cabecalhos })
            .then(({ data }) => {
                grade_teto.carregar(data.tetos);
                definir_tetos_carregados(true);
            })
            .catch(alerta_erro);
    }, [aba]);

    if (carregando || !aba) {
        return (
            <>
                <Head title={nome_sistema} />
                <Loading><Spinner tamanho={40} cor={cor} /></Loading>
            </>
        );
    }

    const pendentes = aba === 'publico' ? grade_publico.quantidade : (aba === 'tetos' ? grade_teto.quantidade : 0);

    // troca de aba sem perder alterações por engano
    const trocar_aba = async (nova) => {
        if (nova === aba) return;
        if (pendentes && !(await confirmar(`Você tem ${pendentes} alterações não salvas. Sair mesmo assim?`, cor))) return;

        if (aba === 'publico') grade_publico.desfazer();
        if (aba === 'tetos') grade_teto.desfazer();
        definir_aba(nova);
        window.history.replaceState(window.history.state, '', `?aba=${nova}`);
    };

    const escolher_publico = async (novo) => {
        if (grade_publico.quantidade && !(await confirmar(`Você tem ${grade_publico.quantidade} alterações não salvas. Trocar de público mesmo assim?`, cor))) return;

        definir_publico(novo);
    };

    const trocar_tipo = async (novo) => {
        if (novo === tipo) return;
        if (grade_publico.quantidade && !(await confirmar(`Você tem ${grade_publico.quantidade} alterações não salvas. Trocar mesmo assim?`, cor))) return;

        definir_tipo(novo);
    };

    const pode_trancar = publico?.tipo === 'usuario' && ['Admin', 'Supervisor'].includes(logado.funcao) && publico.id !== logado.id;

    const alternar_cadeado = async (codigo, trancar) => {
        const cadeado = cadeados_publico[codigo];

        try {
            const { data } = await api({
                method: trancar ? 'post' : 'delete',
                url: '/cotacoes/cadeados',
                data: { camada: 'vendedores', usuarios_id: cadeado?.no_usuario?.id ?? publico.id, tipo, codigos: [codigo] },
                headers: cabecalhos,
            });
            const atualizado = await api.get(`/cotacoes/usuarios/${publico.id}`, { headers: cabecalhos });

            definir_dados_publico((atual) => ({ ...atual, cadeados: atualizado.data.cadeados }));
            avisar('success', trancar ? `${nomes[codigo]} trancada.` : (data.nao_destrancados ? 'Esse cadeado é de um superior.' : `${nomes[codigo]} destrancada.`));
        } catch (erro) {
            alerta_erro(erro);
        }
    };

    const salvar_publico = async () => {
        const valores = grade_publico.alteracoes_para_salvar();

        if (!valores) {
            avisar('error', 'Corrija os campos marcados antes de salvar.');
            return;
        }

        definir_salvando(true);

        try {
            const { data } = publico.tipo === 'usuario'
                ? await api.patch(`/porcentagens-vendedores/${publico.id}`, { tipo, valores }, { headers: cabecalhos })
                : await api.patch('/porcentagens-clientes', { tipo, valores, clientes_id: publico.tipo === 'cliente' ? publico.id : null }, { headers: cabecalhos });

            definir_dados_publico((atual) => ({ ...atual, pre_jogo: data.pre_jogo, ao_vivo: data.ao_vivo }));
            definir_recarga((atual) => atual + 1);
            avisar('success', 'Cotações salvas.');
        } catch (erro) {
            if (erro.response?.status === 422 && Object.keys(erros_dos_campos(erro)).length) {
                grade_publico.definir_erros(erros_dos_campos(erro));
                avisar('error', 'Algumas cotações não foram aceitas.');
            } else {
                alerta_erro(erro);
            }
        } finally {
            definir_salvando(false);
        }
    };

    const salvar_tetos = async () => {
        const valores = grade_teto.alteracoes_para_salvar();

        if (!valores) {
            avisar('error', 'Corrija os campos marcados antes de salvar.');
            return;
        }

        definir_salvando(true);

        try {
            const { data } = await api.patch('/confrontos-teto-cotacoes', { valores }, { headers: cabecalhos });

            grade_teto.carregar(data.tetos);
            definir_recarga((atual) => atual + 1);
            avisar('success', 'Tetos salvos.');
        } catch (erro) {
            if (erro.response?.status === 422 && Object.keys(erros_dos_campos(erro)).length) grade_teto.definir_erros(erros_dos_campos(erro));
            else alerta_erro(erro);
        } finally {
            definir_salvando(false);
        }
    };

    // soma dos superiores: quantas cotações e um exemplo
    const superiores = dados_publico?.superiores?.[tipo] ?? {};
    const codigos_superiores = Object.keys(superiores);

    // histórico de uma regra (Regras ativas, listas e aba Público)
    const abrir_historico = (titulo, filtros) => definir_historico({ titulo, filtros, chave: JSON.stringify(filtros) });

    const historico_publico = () => {
        if (publico.tipo === 'usuario') abrir_historico(publico.nome, { camada: 'vendedores', usuarios_id: publico.id, tipo });
        else abrir_historico(publico.tipo === 'cliente' ? publico.nome : 'Clientes do site', { camada: 'clientes', clientes_id: publico.tipo === 'cliente' ? publico.id : undefined, tipo });
    };

    // "Editar" de Regras ativas: abre a aba e o item certos
    const editar_regra = (regra) => {
        if (regra.camada === 'vendedores') {
            definir_publico({ tipo: 'usuario', id: regra.referencia.id, nome: regra.referencia.nome, funcao: '' });
            definir_tipo(regra.tipo);
            trocar_aba('publico');
        } else if (regra.camada === 'clientes') {
            definir_publico(regra.referencia.id ? { tipo: 'cliente', id: regra.referencia.id, nome: regra.referencia.nome } : { tipo: 'clientes' });
            definir_tipo(regra.tipo);
            trocar_aba('publico');
        } else if (regra.camada === 'tetos') {
            trocar_aba('tetos');
        } else {
            definir_editando({ camada: regra.camada, item: { id: regra.referencia.id, nome: regra.referencia.nome } });
        }
    };

    const nome_referencia = (regra) => {
        if (regra.camada === 'clientes') return regra.referencia.nome ?? 'Clientes do site';
        if (regra.camada === 'tetos') return 'Teto geral';

        return regra.referencia.nome ?? `#${regra.referencia.id}`;
    };

    const filtros_historico_regra = (regra) => ({
        vendedores: { camada: 'vendedores', usuarios_id: regra.referencia.id, tipo: regra.tipo },
        clientes: { camada: 'clientes', clientes_id: regra.referencia.id ?? undefined, tipo: regra.tipo },
        campeonatos: { camada: 'campeonatos', campeonatos_id: regra.referencia.id, alvo: regra.alvo, usuarios_id: regra.usuario?.id },
        confrontos: { camada: 'confrontos', confrontos_id: regra.referencia.id, alvo: regra.alvo, usuarios_id: regra.usuario?.id },
        tetos: { camada: 'tetos' },
    }[regra.camada]);

    const editor_publico = publico && (
        !dados_publico || !nomes ? (
            <EmptyState><Spinner tamanho={32} cor="currentColor" /></EmptyState>
        ) : (
            <Columns>
                <div>
                    {publico.tipo === 'usuario' && (
                        <Info>
                            <i className="material-icons" aria-hidden="true">account_tree</i>
                            {codigos_superiores.length
                                ? `Os superiores somam ajuste em ${codigos_superiores.length} cotações (ex.: ${nomes[codigos_superiores[0]]} ${superiores[codigos_superiores[0]] > 0 ? '+' : ''}${superiores[codigos_superiores[0]]}%). A prévia mostra a soma.`
                                : 'Os superiores não têm ajuste neste tipo.'}
                        </Info>
                    )}
                    {publico.tipo === 'cliente' && (
                        <Info>
                            <i className="material-icons" aria-hidden="true">info</i>
                            A regra geral dos clientes soma com a deste cliente.
                        </Info>
                    )}
                    <QuoteGrid
                        nomes={nomes}
                        grade={grade_publico}
                        modo="porcentagem"
                        cadeados={publico.tipo === 'usuario' ? cadeados_publico : {}}
                        pode_trancar={pode_trancar}
                        ao_alternar_cadeado={alternar_cadeado}
                        ao_focar={definir_foco}
                        cor={cor}
                    />
                </div>
                <QuotePreview
                    parametros={{ publico: publico.tipo, publico_id: publico.id, tipo }}
                    codigo={foco}
                    nome={nomes[foco]}
                    camada="publico"
                    valor={grade_publico.valor_atual(foco)}
                    pendente={foco in grade_publico.pendentes}
                    rotulo_superiores={publico.tipo === 'cliente' ? 'Regra geral' : 'Superiores'}
                    token={token}
                    recarga={recarga}
                    acima_da_barra={grade_publico.quantidade > 0}
                />
            </Columns>
        )
    );

    return (
        <>
            <Head title={nome_sistema} />
            <PanelShell usuario={logado} titulo="Cotações">
                <Section>
                    <PanelTabs abas={abas} ativa={aba} ao_trocar={trocar_aba} />

                    {aba === 'publico' && (
                        <>
                            <Bar>
                                <AudiencePicker
                                    publico={publico}
                                    ao_escolher={escolher_publico}
                                    pode_clientes={pode('porcentagens_clientes.editar')}
                                    pode_usuarios={pode('porcentagens_vendedores.editar')}
                                    token={token}
                                />
                                <Segmented role="group" aria-label="Tipo">
                                    {Object.entries(tipos).map(([chave, rotulo]) => (
                                        <button key={chave} type="button" aria-pressed={tipo === chave} onClick={() => trocar_tipo(chave)}>{rotulo}</button>
                                    ))}
                                </Segmented>
                                {publico && <Tools type="button" onClick={historico_publico}><i className="material-icons" aria-hidden="true">history</i>Histórico</Tools>}
                            </Bar>
                            {!publico && (
                                <EmptyState>
                                    <i className="material-icons" aria-hidden="true">tune</i>
                                    Escolha um público para ajustar as cotações.
                                </EmptyState>
                            )}
                            {editor_publico}
                            <SaveBar quantidade={grade_publico.quantidade} salvando={salvando} ao_desfazer={grade_publico.desfazer} ao_salvar={salvar_publico} />
                        </>
                    )}

                    {aba === 'campeonatos' && (
                        <>
                            <Filters>
                                <SearchBox>
                                    <i className="material-icons" aria-hidden="true">search</i>
                                    <Search type="search" placeholder="Buscar campeonato" value={campeonatos.busca} onChange={(evento) => campeonatos.definir_busca(evento.target.value)} aria-label="Buscar campeonato" />
                                </SearchBox>
                                <Select value={filtro_pais} onChange={(evento) => definir_filtro_pais(evento.target.value)} aria-label="País">
                                    <option value="">Todos os países</option>
                                    {paises.map((pais) => <option key={pais.pais} value={pais.pais}>{pais.pais}</option>)}
                                </Select>
                                <Switch>
                                    <input type="checkbox" checked={so_ajuste} onChange={(evento) => definir_so_ajuste(evento.target.checked)} />
                                    Só com ajuste
                                </Switch>
                            </Filters>
                            <QuoteListTable
                                colunas={[
                                    {
                                        chave: 'nome',
                                        titulo: 'Campeonato',
                                        principal: true,
                                        valor: (item) => (
                                            <>
                                                {item.bandeira && <Flag src={`https://api.oddbrasil.com/flags/${item.bandeira}.png`} alt="" loading="lazy" />}
                                                {item.nome}
                                            </>
                                        ),
                                        sub: (item) => item.pais,
                                    },
                                    { chave: 'pais', titulo: 'País', secundaria: true, valor: (item) => item.pais },
                                    { chave: 'jogos', titulo: 'Jogos futuros', secundaria: true, valor: (item) => item.jogos_futuros },
                                    { chave: 'selo', titulo: 'Ajuste', selo: true, valor: (item) => <RuleBadge resumo={item.resumo} /> },
                                ]}
                                itens={campeonatos.itens}
                                carregando={campeonatos.carregando}
                                erro={campeonatos.erro}
                                ao_tentar_novamente={campeonatos.recarregar}
                                vazio="Nenhum campeonato encontrado. Limpe os filtros."
                                acoes={(item) => [
                                    { chave: 'editar', icone: 'tune', titulo: 'Editar cotações', tom: '#1e88e5', ao_clicar: () => definir_editando({ camada: 'campeonatos', item }) },
                                    { chave: 'historico', icone: 'history', titulo: 'Histórico', tom: '#8e8e8e', ao_clicar: () => abrir_historico(item.nome, { camada: 'campeonatos', campeonatos_id: item.id }) },
                                ]}
                                pagina={campeonatos.pagina}
                                ultima={campeonatos.meta?.last_page}
                                ao_trocar_pagina={campeonatos.definir_pagina}
                            />
                        </>
                    )}

                    {aba === 'jogos' && (
                        <>
                            <Filters>
                                <SearchBox>
                                    <i className="material-icons" aria-hidden="true">search</i>
                                    <Search type="search" placeholder="Buscar time" value={jogos.busca} onChange={(evento) => jogos.definir_busca(evento.target.value)} aria-label="Buscar time" />
                                </SearchBox>
                                <Segmented role="group" aria-label="Dia">
                                    {[['hoje', 'Hoje'], ['amanha', 'Amanhã'], ['depois_de_amanha', 'Depois']].map(([chave, rotulo]) => (
                                        <button key={chave} type="button" aria-pressed={dia === chave} onClick={() => definir_dia(chave)}>{rotulo}</button>
                                    ))}
                                </Segmented>
                                <Switch>
                                    <input type="checkbox" checked={so_ajuste} onChange={(evento) => definir_so_ajuste(evento.target.checked)} />
                                    Só com ajuste
                                </Switch>
                            </Filters>
                            <Notice>
                                <i className="material-icons" aria-hidden="true">info</i>
                                O valor do jogo vale só para o pré-jogo e só antes do início.
                            </Notice>
                            <QuoteListTable
                                colunas={[
                                    { chave: 'hora', titulo: 'Hora', secundaria: true, valor: (item) => `${formatar_dia_mes(item.data_inicio)} ${formatar_hora(item.data_inicio)}` },
                                    {
                                        chave: 'jogo',
                                        titulo: 'Jogo',
                                        principal: true,
                                        valor: (item) => `${item.time_casa} x ${item.time_fora}`,
                                        sub: (item) => `${formatar_hora(item.data_inicio)} · ${item.campeonato?.nome ?? ''}`,
                                    },
                                    { chave: 'campeonato', titulo: 'Campeonato', secundaria: true, valor: (item) => item.campeonato?.nome },
                                    { chave: 'selo', titulo: 'Ajuste', selo: true, valor: (item) => <RuleBadge resumo={item.resumo} unidade="" /> },
                                ]}
                                itens={jogos.itens}
                                carregando={jogos.carregando}
                                erro={jogos.erro}
                                ao_tentar_novamente={jogos.recarregar}
                                vazio="Nenhum jogo encontrado para o filtro."
                                acoes={(item) => [
                                    {
                                        chave: 'editar',
                                        icone: 'tune',
                                        titulo: 'Editar cotações',
                                        tom: '#1e88e5',
                                        ao_clicar: () => definir_editando({ camada: 'confrontos', item: { ...item, nome: `${item.time_casa} x ${item.time_fora}` } }),
                                    },
                                    { chave: 'historico', icone: 'history', titulo: 'Histórico', tom: '#8e8e8e', ao_clicar: () => abrir_historico(`${item.time_casa} x ${item.time_fora}`, { camada: 'confrontos', confrontos_id: item.id }) },
                                ]}
                                pagina={jogos.pagina}
                                ultima={jogos.meta?.last_page}
                                ao_trocar_pagina={jogos.definir_pagina}
                            />
                        </>
                    )}

                    {aba === 'tetos' && (
                        <>
                            <Notice>
                                <i className="material-icons" aria-hidden="true">vertical_align_top</i>
                                O teto vale para todos os públicos: nenhuma cotação passa deste valor.
                                <Tools type="button" onClick={() => abrir_historico('Teto geral', { camada: 'tetos' })}><i className="material-icons" aria-hidden="true">history</i>Histórico</Tools>
                            </Notice>
                            {!tetos_carregados || !nomes ? (
                                <EmptyState><Spinner tamanho={32} cor="currentColor" /></EmptyState>
                            ) : (
                                <Columns>
                                    <div>
                                        <QuoteGrid nomes={nomes} grade={grade_teto} modo="teto" ao_focar={definir_foco} cor={cor} />
                                    </div>
                                    <QuotePreview
                                        parametros={{ publico: 'clientes', tipo: 'pre_jogo' }}
                                        codigo={foco}
                                        nome={nomes[foco]}
                                        camada="teto"
                                        valor={grade_teto.valor_atual(foco)}
                                        pendente={foco in grade_teto.pendentes}
                                        token={token}
                                        recarga={recarga}
                                        acima_da_barra={grade_teto.quantidade > 0}
                                    />
                                </Columns>
                            )}
                            <SaveBar quantidade={grade_teto.quantidade} salvando={salvando} ao_desfazer={grade_teto.desfazer} ao_salvar={salvar_tetos} />
                        </>
                    )}

                    {aba === 'regras' && (
                        <>
                            <Filters>
                                <Select value={camada_filtro} onChange={(evento) => definir_camada_filtro(evento.target.value)} aria-label="Camada">
                                    <option value="">Todas as camadas</option>
                                    {Object.entries(camadas).map(([chave, { rotulo }]) => <option key={chave} value={chave}>{rotulo}</option>)}
                                </Select>
                            </Filters>
                            <QuoteListTable
                                colunas={[
                                    {
                                        chave: 'referencia',
                                        titulo: 'Regra',
                                        principal: true,
                                        valor: (regra) => nome_referencia(regra),
                                        sub: (regra) => `${camadas[regra.camada].rotulo}${regra.tipo ? ` · ${tipos[regra.tipo]}` : ''}`,
                                    },
                                    { chave: 'camada', titulo: 'Camada', secundaria: true, valor: (regra) => camadas[regra.camada].rotulo },
                                    {
                                        chave: 'alvo',
                                        titulo: 'Vale para',
                                        secundaria: true,
                                        valor: (regra) => {
                                            if (regra.alvo === 'Vendedores') return `Vendedores de ${regra.usuario?.nome ?? '—'}${regra.usuario?.excluido ? ' (excluído)' : (regra.usuario && !regra.usuario.ativo ? ' (inativo)' : '')}`;
                                            if (regra.camada === 'vendedores' && regra.usuario) return regra.usuario.excluido ? 'usuário excluído' : (regra.usuario.ativo ? '—' : 'usuário inativo');

                                            return regra.alvo ?? '—';
                                        },
                                    },
                                    { chave: 'tipo', titulo: 'Tipo', secundaria: true, valor: (regra) => tipos[regra.tipo] ?? '—' },
                                    {
                                        chave: 'selo',
                                        titulo: 'Ajuste',
                                        selo: true,
                                        valor: (regra) => <RuleBadge resumo={{ quantidade: 1, alvos: [`${regra.quantidade} ${regra.quantidade === 1 ? 'cotação' : 'cotações'}`], minimo: regra.minimo, maximo: regra.maximo }} unidade={regra.camada === 'confrontos' || regra.camada === 'tetos' ? '' : '%'} />,
                                    },
                                ]}
                                itens={regras.itens.map((regra) => ({ ...regra, chave: `${regra.camada}-${regra.referencia.id}-${regra.alvo}-${regra.usuario?.id}-${regra.tipo}` }))}
                                carregando={regras.carregando}
                                erro={regras.erro}
                                ao_tentar_novamente={regras.recarregar}
                                vazio="Nenhuma regra ajustada ainda."
                                acoes={(regra) => [
                                    { chave: 'editar', icone: 'tune', titulo: 'Editar', tom: '#1e88e5', ao_clicar: () => editar_regra(regra) },
                                    { chave: 'historico', icone: 'history', titulo: 'Histórico', tom: '#8e8e8e', ao_clicar: () => abrir_historico(nome_referencia(regra), filtros_historico_regra(regra)) },
                                ]}
                                pagina={regras.pagina}
                                ultima={regras.meta?.last_page}
                                ao_trocar_pagina={regras.definir_pagina}
                            />
                        </>
                    )}
                </Section>
            </PanelShell>

            {editando && (
                <QuoteEditorModal
                    camada={editando.camada}
                    item={editando.item}
                    logado={logado}
                    nomes={nomes}
                    token={token}
                    cor={cor}
                    ao_fechar={() => definir_editando(null)}
                    ao_salvo={(id, resumo) => (editando.camada === 'campeonatos' ? campeonatos : jogos).atualizar_item(id, { resumo })}
                />
            )}
            <QuoteHistoryModal regra={historico} nomes={nomes} token={token} ao_fechar={() => definir_historico(null)} />
        </>
    );
}
