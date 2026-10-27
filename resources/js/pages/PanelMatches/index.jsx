import { useEffect, useRef, useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import Swal from 'sweetalert2';

import ActiveFilters from '../../components/ActiveFilters';
import ChampionshipFormModal from '../../components/ChampionshipFormModal';
import ChampionshipsTable from '../../components/ChampionshipsTable';
import MatchDrawer from '../../components/MatchDrawer';
import MatchFormModal from '../../components/MatchFormModal';
import MatchLimitModal from '../../components/MatchLimitModal';
import MatchResultModal from '../../components/MatchResultModal';
import MatchesFilters, { etiquetas_confrontos, remover_filtro_confrontos } from '../../components/MatchesFilters';
import MatchesTable from '../../components/MatchesTable';
import Pagination from '../../components/Pagination';
import PanelShell from '../../components/PanelShell';
import PanelTabs from '../../components/PanelTabs';
import Spinner from '../../components/Spinner';
import StatCard from '../../components/StatCard';
import useConfrontosPainel, { atalhos_dia, tipos_jogo } from '../../hooks/useConfrontosPainel';
import usePainel from '../../hooks/usePainel';
import useTelaMobile from '../../hooks/useTelaMobile';
import api from '../../utils/api';
import { alerta_erro, confirmar } from '../../utils/alerts';
import {
    Area, Cards, Chip, Chips, Choices, Error, HeaderActions, Loading, Notice, Retry, Search, SearchBox, Section, Skeleton, Tabs, Tool, Top,
} from './styles';

// espera depois da última tecla antes de buscar
const ATRASO_BUSCA = 400;

// itens do menu de três pontinhos (FR-009 e FR-028)
const itens_acao = {
    ver: { icone: 'visibility', titulo: 'Ver detalhe', tom: '#1e88e5' },
    cotacoes: { icone: 'tune', titulo: 'Alterar cotações', tom: '#1e88e5' },
    limite: { icone: 'price_check', titulo: 'Alterar limite', tom: '#1e88e5' },
    desativar: { icone: 'lock', titulo: 'Desativar', tom: '#757575' },
    ativar: { icone: 'lock_open', titulo: 'Ativar', tom: '#43a047' },
    desativar_rede: { icone: 'lock', titulo: 'Desativar para a minha rede', tom: '#ef6c00' },
    ativar_rede: { icone: 'lock_open', titulo: 'Ativar para a minha rede', tom: '#43a047' },
    lancar: { icone: 'scoreboard', titulo: 'Lançar resultado', tom: '#43a047' },
    corrigir: { icone: 'scoreboard', titulo: 'Corrigir resultado', tom: '#1e88e5' },
    editar: { icone: 'edit', titulo: 'Editar', tom: '#1e88e5' },
    excluir: { icone: 'delete_outline', titulo: 'Excluir', tom: '#e53935' },
    ver_jogos: { icone: 'sports_soccer', titulo: 'Ver jogos', tom: '#1e88e5' },
    favoritar: { icone: 'star', titulo: 'Favoritar', tom: '#f9a825' },
    desfavoritar: { icone: 'star_border', titulo: 'Desfavoritar', tom: '#757575' },
    adicionar_jogo: { icone: 'add', titulo: 'Adicionar jogo', tom: '#43a047' },
};

const nome_jogo = (jogo) => `${jogo.time_casa} x ${jogo.time_fora}`;

// aviso curto no canto, sem bloquear a tela
const avisar = (texto) => Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: texto, showConfirmButton: false, timer: 3000 });

// tela "Confrontos" do painel (spec 023; a antiga "Lista de Jogos" e a antiga "Campeonatos"): cartões,
// abas Jogos, Mais apostados, Pendentes (só Admin) e Campeonatos, busca, filtros, gaveta do jogo e as
// ações (cotações, limite, ativar/desativar para todos ou para a rede, resultado, cadastro manual),
// com o alcance e as permissões aplicados no servidor
export default function PanelMatches() {
    const { nome_sistema, tema } = usePage().props;
    const { usuario: logado, carregando } = usePainel();
    const pode = (permissao) => Boolean(logado?.permissoes.includes(permissao));
    const confrontos = useConfrontosPainel({ habilitado: pode('confrontos.listar') });
    const { filtros, mudar, trocar_aba, lista, resumo, recarregar, token } = confrontos;
    const e_mobile = useTelaMobile();
    const [busca, definir_busca] = useState(filtros.busca);
    const [filtros_abertos, definir_filtros_abertos] = useState(false);
    const [jogo_aberto, definir_jogo_aberto] = useState(null);
    const [aba_gaveta, definir_aba_gaveta] = useState('dados');
    const [versao_detalhe, definir_versao_detalhe] = useState(0);
    const [limite, definir_limite] = useState(null);
    const [resultado, definir_resultado] = useState(null);
    const [edicao_jogo, definir_edicao_jogo] = useState(null);
    const [edicao_campeonato, definir_edicao_campeonato] = useState(null);
    const espera = useRef(null);

    const autorizacao = { headers: { Authorization: `Bearer ${token}` } };
    const e_admin = logado?.funcao === 'Admin';

    // sem acesso, volta para a Dashboard
    useEffect(() => {
        if (logado && !pode('confrontos.listar')) router.visit('/painel');
    }, [logado]);

    // aba fora do alcance (Pendentes sem lançar resultado, Campeonatos sem listar) volta para Jogos
    useEffect(() => {
        if (!logado) return;

        if ((filtros.aba === 'pendentes' && !pode('confrontos.lancar_resultado')) || (filtros.aba === 'campeonatos' && !pode('campeonatos.listar'))) trocar_aba('jogos');
    }, [logado, filtros.aba]);

    useEffect(() => () => window.clearTimeout(espera.current), []);

    if (carregando || !logado || !pode('confrontos.listar')) {
        return (
            <>
                <Head title={nome_sistema} />
                <Loading><Spinner tamanho={40} cor={tema.temas} /></Loading>
            </>
        );
    }

    const abas = [
        { chave: 'jogos', rotulo: 'Jogos', icone: 'sports_soccer' },
        { chave: 'mais_apostados', rotulo: 'Mais apostados', icone: 'local_fire_department' },
        pode('confrontos.lancar_resultado') && { chave: 'pendentes', rotulo: 'Pendentes', icone: 'pending_actions', contador: resumo.dados?.pendentes ?? undefined },
        pode('campeonatos.listar') && { chave: 'campeonatos', rotulo: 'Campeonatos', icone: 'emoji_events' },
    ].filter(Boolean);

    const de_jogos = ['jogos', 'mais_apostados'].includes(filtros.aba);

    const buscar = (texto) => {
        definir_busca(texto);
        window.clearTimeout(espera.current);
        espera.current = window.setTimeout(() => mudar({ busca: texto }), ATRASO_BUSCA);
    };

    // depois de cada ação: aviso, lista, cartões e gaveta atualizados sem perder a página (FR-037)
    const concluir = (mensagem) => {
        if (mensagem) avisar(mensagem);

        recarregar();
        definir_versao_detalhe((atual) => atual + 1);
    };

    // executa a chamada com o retorno imediato; erro do servidor no alerta
    const executar = async (chamada, mensagem) => {
        try {
            await chamada();
            concluir(mensagem);
        } catch (erro) {
            alerta_erro(erro);
            recarregar();
        }
    };

    const abrir_jogo = (jogo, aba = 'dados') => {
        definir_aba_gaveta(aba);
        definir_jogo_aberto(jogo);
    };

    // ativar/desativar para todos (FR-010): pré-jogo e ao vivo
    const alterar_situacao_jogo = async (jogo) => {
        const desativar = jogo.ativo;
        const aviso = desativar && jogo.apostas_em_aberto > 0 ? ' As apostas já feitas continuam valendo; o jogo só deixa de receber apostas novas.' : '';
        const texto = desativar ? `Desativar ${nome_jogo(jogo)} para todos?${aviso}` : `Ativar ${nome_jogo(jogo)} para todos?`;

        if (!(await confirmar(texto, desativar ? '#c40808' : '#43a047'))) return;

        executar(() => api.patch(`/confrontos/${jogo.id}/situacao`, { ativo: !desativar }, autorizacao), desativar ? 'Jogo desativado.' : 'Jogo ativado.');
    };

    // desativar para a rede (FR-011): não permitidos com alvo Vendedores do próprio usuário,
    // pré-jogo e, com a permissão, ao vivo
    const alterar_rede_jogo = async (jogo) => {
        const proprio = jogo.restricoes?.nao_permitido_proprio ?? {};
        const desativar = !proprio.pre_jogo_id;
        const aviso = desativar && jogo.apostas_em_aberto > 0 ? ' As apostas já feitas continuam valendo; o jogo só deixa de receber apostas novas.' : '';
        const texto = desativar ? `Desativar ${nome_jogo(jogo)} para a sua rede?${aviso}` : `Ativar ${nome_jogo(jogo)} para a sua rede?`;

        if (!(await confirmar(texto, desativar ? '#c40808' : '#43a047'))) return;

        executar(async () => {
            const corpo = { confrontos_id: jogo.id, alvo: 'Vendedores', usuarios_id: logado.id };

            if (desativar) {
                await api.post('/confrontos-nao-permitidos', corpo, autorizacao);
                if (jogo.acoes?.desativar_rede_ao_vivo && !proprio.ao_vivo_id) await api.post('/confrontos-ao-vivo-nao-permitidos', corpo, autorizacao);
            } else {
                await api.delete(`/confrontos-nao-permitidos/${proprio.pre_jogo_id}`, autorizacao);
                if (proprio.ao_vivo_id && jogo.acoes?.desativar_rede_ao_vivo) await api.delete(`/confrontos-ao-vivo-nao-permitidos/${proprio.ao_vivo_id}`, autorizacao);
            }
        }, desativar ? 'Jogo desativado para a sua rede.' : 'Jogo ativado para a sua rede.');
    };

    const excluir_jogo = async (jogo) => {
        if (!(await confirmar(`Excluir o jogo manual ${nome_jogo(jogo)}?`))) return;

        executar(() => api.delete(`/confrontos/${jogo.id}`, autorizacao), 'Jogo excluído.');
    };

    const alterar_cotacoes = (camada, id, nome) => router.visit(`/painel/cotacoes?aba=${camada}&${camada === 'jogos' ? 'confrontos_id' : 'campeonatos_id'}=${id}&nome=${encodeURIComponent(nome)}`);

    // ações do jogo pelo que o servidor libera (acoes) e pelo jogo (FR-009); as mesmas no menu e na
    // gaveta. Os itens da aba Pendentes não trazem acoes: só lançar e ver
    const acoes_do_jogo = (jogo, com_ver = true) => {
        const acoes = jogo.acoes ?? { lancar_resultado: pode('confrontos.lancar_resultado') };
        const desativado_rede = Boolean(jogo.restricoes?.nao_permitido_proprio?.pre_jogo_id);

        return [
            com_ver && 'ver',
            acoes.alterar_cotacoes && 'cotacoes',
            acoes.alterar_limite && 'limite',
            acoes.alterar_situacao && (jogo.ativo ? 'desativar' : 'ativar'),
            acoes.desativar_rede && (desativado_rede ? 'ativar_rede' : 'desativar_rede'),
            acoes.lancar_resultado && (jogo.resultado ? 'corrigir' : 'lancar'),
            acoes.editar && 'editar',
            acoes.excluir && 'excluir',
        ].filter(Boolean).map((chave) => ({
            chave,
            ...itens_acao[chave],
            ao_clicar: () => {
                if (chave === 'ver') abrir_jogo(jogo, filtros.aba === 'mais_apostados' ? 'mais_apostados' : 'dados');
                if (chave === 'cotacoes') alterar_cotacoes('jogos', jogo.id, nome_jogo(jogo));
                if (chave === 'limite') definir_limite(jogo);
                if (chave === 'desativar' || chave === 'ativar') alterar_situacao_jogo(jogo);
                if (chave === 'desativar_rede' || chave === 'ativar_rede') alterar_rede_jogo(jogo);
                if (chave === 'lancar' || chave === 'corrigir') abrir_resultado(jogo);
                if (chave === 'editar') definir_edicao_jogo(jogo);
                if (chave === 'excluir') excluir_jogo(jogo);
            },
        }));
    };

    // o modal do placar precisa do resultado completo (tempos, escanteios e gols): vem da gaveta
    const abrir_resultado = async (jogo) => {
        if (!jogo.resultado) {
            definir_resultado({ jogo, atual: null });

            return;
        }

        try {
            const { data } = await api.get(`/confrontos/${jogo.id}`, autorizacao);
            definir_resultado({ jogo, atual: data.data.resultado });
        } catch (erro) {
            alerta_erro(erro);
        }
    };

    // ações do campeonato (FR-028)
    const acoes_do_campeonato = (campeonato) => {
        const acoes = campeonato.acoes ?? {};
        const desativado_rede = Boolean(campeonato.restricoes?.nao_permitido_proprio_id);

        return [
            'ver_jogos',
            acoes.alterar_cotacoes && 'cotacoes',
            acoes.favoritar && (campeonato.favorito ? 'desfavoritar' : 'favoritar'),
            acoes.alterar_situacao && (campeonato.ativo ? 'desativar' : 'ativar'),
            acoes.desativar_rede && (desativado_rede ? 'ativar_rede' : 'desativar_rede'),
            acoes.adicionar_jogo && 'adicionar_jogo',
            acoes.editar && 'editar',
            acoes.excluir && 'excluir',
        ].filter(Boolean).map((chave) => ({
            chave,
            ...itens_acao[chave],
            ao_clicar: async () => {
                if (chave === 'ver_jogos') {
                    definir_busca('');
                    trocar_aba('jogos', { campeonato: { id: campeonato.id, nome: campeonato.nome }, dia: 'hoje', data: null, situacao: null, manual: false });
                }

                if (chave === 'cotacoes') alterar_cotacoes('campeonatos', campeonato.id, campeonato.nome);

                if (chave === 'favoritar' || chave === 'desfavoritar') {
                    executar(() => api.patch(`/campeonatos/${campeonato.id}/favorito`, { favorito: !campeonato.favorito }, autorizacao), campeonato.favorito ? 'Campeonato desfavoritado.' : 'Campeonato favoritado.');
                }

                if (chave === 'desativar' || chave === 'ativar') {
                    const desativar = campeonato.ativo;

                    if (await confirmar(desativar ? `Desativar ${campeonato.nome} para todos? Os jogos dele saem do site.` : `Ativar ${campeonato.nome} para todos?`, desativar ? '#c40808' : '#43a047')) {
                        executar(() => api.patch(`/campeonatos/${campeonato.id}/situacao`, { ativo: !desativar }, autorizacao), desativar ? 'Campeonato desativado.' : 'Campeonato ativado.');
                    }
                }

                if (chave === 'desativar_rede' || chave === 'ativar_rede') {
                    const desativar = !desativado_rede;

                    if (await confirmar(desativar ? `Desativar ${campeonato.nome} para a sua rede?` : `Ativar ${campeonato.nome} para a sua rede?`, desativar ? '#c40808' : '#43a047')) {
                        executar(() => (desativar
                            ? api.post('/campeonatos-nao-permitidos', { campeonatos_id: campeonato.id, alvo: 'Vendedores', usuarios_id: logado.id }, autorizacao)
                            : api.delete(`/campeonatos-nao-permitidos/${campeonato.restricoes.nao_permitido_proprio_id}`, autorizacao)), desativar ? 'Campeonato desativado para a sua rede.' : 'Campeonato ativado para a sua rede.');
                    }
                }

                if (chave === 'adicionar_jogo') definir_edicao_jogo({ campeonato_inicial: { id: campeonato.id, nome: campeonato.nome } });
                if (chave === 'editar') definir_edicao_campeonato(campeonato);

                if (chave === 'excluir') {
                    const jogos = campeonato.total_jogos ?? 0;
                    const aviso = jogos ? ` Este campeonato tem ${jogos} ${jogos === 1 ? 'jogo, que também será excluído' : 'jogos, que também serão excluídos'}.` : '';

                    if (await confirmar(`Excluir o campeonato manual ${campeonato.nome}?${aviso}`)) {
                        executar(() => api.delete(`/campeonatos/${campeonato.id}`, autorizacao), 'Campeonato excluído.');
                    }
                }
            },
        }));
    };

    const etiquetas = etiquetas_confrontos(filtros);
    const tem_filtros = filtros.aba !== 'pendentes';
    const painel_filtros = filtros_abertos && !e_mobile && tem_filtros;
    const titulo_aba = abas.find((aba) => aba.chave === filtros.aba)?.rotulo ?? 'Jogos';
    const texto_dia = filtros.data ? filtros.data.split('-').reverse().join('/') : (atalhos_dia.find((atalho) => atalho.chave === filtros.dia)?.rotulo ?? 'Hoje');

    const filtros_tela = tem_filtros && (
        <MatchesFilters
            aberto={filtros_abertos}
            filtros={filtros}
            token={token}
            ao_aplicar={mudar}
            ao_fechar={() => definir_filtros_abertos(false)}
        />
    );

    // cartões (FR-004): Pendentes de resultado só para quem lança resultado (Admin)
    const cartoes = () => {
        if (filtros.aba === 'campeonatos') return null;

        const dados = resumo.dados;

        if (resumo.carregando && !dados) return <Skeleton>{Array.from({ length: 4 }, (_, indice) => <span key={indice} />)}</Skeleton>;

        if (resumo.erro && !dados) {
            return (
                <Error>
                    {resumo.erro.response?.data?.message ?? 'Não foi possível carregar os totais.'}
                    <Retry type="button" onClick={() => recarregar()}>Tentar novamente</Retry>
                </Error>
            );
        }

        if (!dados) return null;

        return (
            <Cards>
                <StatCard icone="sports_soccer" rotulo="Jogos" valor={dados.jogos} dinheiro={false} titulo="Jogos do dia e do tipo escolhidos." />
                <StatCard icone="live_tv" rotulo="Ao vivo agora" valor={dados.ao_vivo} dinheiro={false} titulo="Jogos em andamento agora." />
                <StatCard icone="lock" rotulo="Desativados" valor={dados.desativados} dinheiro={false} titulo={e_admin || logado.funcao === 'Supervisor' ? 'Jogos do filtro desativados para todos.' : 'Jogos do filtro desativados para todos ou para a sua rede.'} />
                {dados.pendentes !== null && <StatCard icone="pending_actions" rotulo="Pendentes de resultado" valor={dados.pendentes} dinheiro={false} titulo="Jogos que começaram há mais de 2 horas, sem resultado e com apostas aguardando." />}
            </Cards>
        );
    };

    const tabela = () => {
        if (filtros.aba === 'campeonatos') {
            return (
                <ChampionshipsTable
                    linhas={lista.linhas}
                    carregando={lista.carregando}
                    erro={lista.erro}
                    ao_tentar_novamente={() => recarregar()}
                    acoes={acoes_do_campeonato}
                />
            );
        }

        return (
            <MatchesTable
                aba={filtros.aba}
                linhas={lista.linhas}
                carregando={lista.carregando}
                erro={lista.erro}
                ao_tentar_novamente={() => recarregar()}
                ao_abrir={(jogo) => abrir_jogo(jogo, filtros.aba === 'mais_apostados' ? 'mais_apostados' : 'dados')}
                acoes={acoes_do_jogo}
            />
        );
    };

    const cadastrar = filtros.aba === 'campeonatos'
        ? pode('campeonatos.cadastrar') && { rotulo: 'Cadastrar campeonato', ao_clicar: () => definir_edicao_campeonato({}) }
        : filtros.aba === 'jogos' && pode('confrontos.cadastrar') && { rotulo: 'Cadastrar jogo', ao_clicar: () => definir_edicao_jogo({}) };

    return (
        <>
            <Head title={nome_sistema} />
            <PanelShell usuario={logado} titulo={`Confrontos · ${titulo_aba}${de_jogos ? ` · ${texto_dia}` : ''} · total ${lista.total.toLocaleString('pt-BR')}`}>
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
                                {tem_filtros && (
                                    <Tool type="button" aria-expanded={filtros_abertos} onClick={() => definir_filtros_abertos((aberto) => !aberto)}>
                                        <i className="material-icons" aria-hidden="true">filter_alt</i>
                                        {etiquetas.length ? `Filtros (${etiquetas.length})` : 'Filtros'}
                                    </Tool>
                                )}
                                {cadastrar && (
                                    <Tool type="button" onClick={cadastrar.ao_clicar}>
                                        <i className="material-icons" aria-hidden="true">add</i>
                                        {cadastrar.rotulo}
                                    </Tool>
                                )}
                                <Tool type="button" title="Atualizar" aria-label="Atualizar" $compacto onClick={() => recarregar()}>
                                    <i className="material-icons" aria-hidden="true">refresh</i>
                                </Tool>
                            </HeaderActions>
                        </Area>
                        {painel_filtros && <Area $area="filtros">{filtros_tela}</Area>}
                        {etiquetas.length > 0 && (
                            <Area $area="etiquetas">
                                <ActiveFilters etiquetas={etiquetas} ao_remover={(chave) => mudar(remover_filtro_confrontos(chave))} />
                            </Area>
                        )}
                        <Area $area="escolhas">
                            <Choices>
                                {de_jogos && (
                                    <Chips role="group" aria-label="Tipo de jogo">
                                        {tipos_jogo.map((tipo) => (
                                            <Chip key={tipo.chave} type="button" aria-pressed={filtros.tipo === tipo.chave} onClick={() => mudar({ tipo: tipo.chave })}>{tipo.rotulo}</Chip>
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
                                    placeholder={filtros.aba === 'campeonatos' ? 'Nome do campeonato' : 'Time ou campeonato'}
                                    aria-label="Buscar"
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
                    {lista.falha_atualizacao && <Notice role="status">Não foi possível atualizar. Tentando de novo em instantes.</Notice>}
                    {tabela()}
                    <Pagination pagina={filtros.pagina} ultima={lista.ultima} ao_trocar={(pagina) => mudar({ pagina })} />
                </Section>
            </PanelShell>

            <MatchDrawer
                jogo={jogo_aberto}
                aba_inicial={aba_gaveta}
                versao={versao_detalhe}
                token={token}
                pode_abrir_aposta={pode('apostas.listar')}
                acoes={(jogo) => acoes_do_jogo(jogo, false)}
                ao_lancar_resultado={abrir_resultado}
                ao_fechar={() => definir_jogo_aberto(null)}
            />
            <MatchLimitModal
                jogo={limite}
                token={token}
                ao_fechar={() => definir_limite(null)}
                ao_salvo={() => {
                    definir_limite(null);
                    concluir('Limite alterado.');
                }}
            />
            <MatchResultModal
                jogo={resultado?.jogo ?? null}
                resultado={resultado?.atual ?? null}
                token={token}
                ao_fechar={() => definir_resultado(null)}
                ao_salvo={(mensagem) => {
                    definir_resultado(null);
                    concluir(mensagem);
                }}
            />
            <MatchFormModal
                jogo={edicao_jogo}
                campeonato_inicial={edicao_jogo?.campeonato_inicial ?? null}
                token={token}
                ao_fechar={() => definir_edicao_jogo(null)}
                ao_salvar={(mensagem) => {
                    definir_edicao_jogo(null);
                    concluir(mensagem);
                }}
            />
            <ChampionshipFormModal
                campeonato={edicao_campeonato}
                token={token}
                ao_fechar={() => definir_edicao_campeonato(null)}
                ao_salvar={(mensagem) => {
                    definir_edicao_campeonato(null);
                    concluir(mensagem);
                }}
            />
        </>
    );
}
