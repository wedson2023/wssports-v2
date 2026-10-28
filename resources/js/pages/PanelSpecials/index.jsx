import { useEffect, useRef, useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import Swal from 'sweetalert2';

import ActiveFilters from '../../components/ActiveFilters';
import ConfirmModal from '../../components/ConfirmModal';
import Pagination from '../../components/Pagination';
import PanelShell from '../../components/PanelShell';
import PanelTabs from '../../components/PanelTabs';
import SpecialCloseModal from '../../components/SpecialCloseModal';
import SpecialDrawer from '../../components/SpecialDrawer';
import SpecialFormModal from '../../components/SpecialFormModal';
import SpecialsFilters, { etiquetas_especiais, remover_filtro_especiais } from '../../components/SpecialsFilters';
import SpecialsTable from '../../components/SpecialsTable';
import SpecialVoidOptionModal from '../../components/SpecialVoidOptionModal';
import Spinner from '../../components/Spinner';
import StatCard from '../../components/StatCard';
import useEspeciaisPainel from '../../hooks/useEspeciaisPainel';
import usePainel from '../../hooks/usePainel';
import useTelaMobile from '../../hooks/useTelaMobile';
import api from '../../utils/api';
import { alerta_erro } from '../../utils/alerts';
import {
    Area, Cards, Error, HeaderActions, Legend, Loading, Retry, Search, SearchBox, Section, Skeleton, Tabs, Tool, Top,
} from './styles';

// espera depois da última tecla antes de buscar
const ATRASO_BUSCA = 400;

// itens do menu de três pontinhos de cada especial (FR-006), na ordem das acoes da API
const acoes_especial = {
    detalhes: { icone: 'visibility', titulo: 'Ver detalhes', tom: '#1e88e5' },
    editar: { icone: 'edit', titulo: 'Editar', tom: '#1e88e5' },
    encerrar: { icone: 'flag', titulo: 'Encerrar', tom: '#43a047' },
    corrigir_resultado: { icone: 'published_with_changes', titulo: 'Corrigir resultado', tom: '#ef6c00' },
    cancelar: { icone: 'cancel', titulo: 'Cancelar', tom: '#757575' },
    remover: { icone: 'delete_outline', titulo: 'Remover', tom: '#e53935' },
    restaurar: { icone: 'restore_from_trash', titulo: 'Restaurar', tom: '#43a047' },
};

const quantidade_apostas = (quantidade) => `${quantidade} ${quantidade === 1 ? 'aposta afetada' : 'apostas afetadas'}`;

// aviso curto no canto, sem bloquear a tela
const avisar = (texto) => Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: texto, showConfirmButton: false, timer: 3000 });

// tela "Especiais" do painel (spec 024; a antiga "Lista de modalidades"): cartões, abas Especiais e
// Lixeira, busca, filtro de situação, gaveta com a exposição por opção e as ações (cadastrar,
// editar, encerrar, corrigir resultado, cancelar, remover, restaurar e anular opção), com o alcance
// pela rede e as permissões aplicados no servidor
export default function PanelSpecials() {
    const { nome_sistema, tema } = usePage().props;
    const { usuario: logado, carregando } = usePainel();
    const pode = (permissao) => Boolean(logado?.permissoes.includes(permissao));
    const { filtros, mudar, trocar_aba, lista, resumo, recarregar, token } = useEspeciaisPainel({ habilitado: pode('especiais.listar') });
    const e_mobile = useTelaMobile();
    const [busca, definir_busca] = useState(filtros.busca);
    const [filtros_abertos, definir_filtros_abertos] = useState(false);
    const [especial_aberto, definir_especial_aberto] = useState(null);
    const [versao_detalhe, definir_versao_detalhe] = useState(0);
    const [edicao, definir_edicao] = useState(null);
    const [fechamento, definir_fechamento] = useState(null);
    const [anulacao, definir_anulacao] = useState(null);
    const [confirmacao, definir_confirmacao] = useState(null);
    const espera = useRef(null);

    const autorizacao = { headers: { Authorization: `Bearer ${token}` } };

    // sem acesso, volta para a Dashboard
    useEffect(() => {
        if (logado && !pode('especiais.listar')) router.visit('/painel');
    }, [logado]);

    useEffect(() => () => window.clearTimeout(espera.current), []);

    if (carregando || !logado || !pode('especiais.listar')) {
        return (
            <>
                <Head title={nome_sistema} />
                <Loading><Spinner tamanho={40} cor={tema.temas} /></Loading>
            </>
        );
    }

    const abas = [
        { chave: 'especiais', rotulo: 'Especiais', icone: 'emoji_events' },
        { chave: 'lixeira', rotulo: 'Lixeira', icone: 'delete_outline' },
    ];

    const buscar = (texto) => {
        definir_busca(texto);
        window.clearTimeout(espera.current);
        espera.current = window.setTimeout(() => mudar({ busca: texto }), ATRASO_BUSCA);
    };

    // depois de cada ação: aviso, lista, cartões e gaveta atualizados sem perder a página
    const concluir = (mensagem) => {
        if (mensagem) avisar(mensagem);

        recarregar();
        definir_versao_detalhe((atual) => atual + 1);
    };

    // a linha da lista não traz as opções: os modais usam o detalhe, com os números da linha
    const carregar_detalhe = async (especial) => {
        const { data } = await api.get(`/especiais/${especial.id}`, autorizacao);

        return { ...especial, ...data.data };
    };

    const abrir_com_detalhe = async (especial, abrir) => {
        try {
            abrir(await carregar_detalhe(especial));
        } catch (erro) {
            alerta_erro(erro);
            recarregar();
        }
    };

    // confirmação com a chamada à API; erro da regra de negócio aparece no aviso e recarrega
    const confirmar_acao = (dados) => definir_confirmacao({
        ...dados,
        executar: async () => {
            try {
                const resposta = await dados.chamada();
                definir_confirmacao(null);
                concluir(dados.sucesso(resposta?.data));
            } catch (erro) {
                definir_confirmacao(null);
                alerta_erro(erro);
                recarregar();
            }
        },
    });

    const cancelar = (especial) => confirmar_acao({
        titulo: 'Cancelar especial',
        icone: 'cancel',
        rotulo: 'Cancelar especial',
        mensagem: `Todos os palpites deste especial serão devolvidos (cotação 1,00). ${especial.apostas_em_aberto ?? 0} apostas em aberto.`,
        chamada: () => api.post(`/especiais/${especial.id}/cancelar`, {}, autorizacao),
        sucesso: (dados) => `Especial cancelado. ${quantidade_apostas(dados?.apostas_afetadas ?? 0)}.`,
    });

    const remover = (especial) => confirmar_acao({
        titulo: 'Remover especial',
        icone: 'delete_outline',
        rotulo: 'Remover',
        mensagem: 'O especial vai para a lixeira.',
        chamada: async () => {
            await api.delete(`/especiais/${especial.id}`, autorizacao);
            definir_especial_aberto(null);
        },
        sucesso: () => 'Especial removido.',
    });

    const restaurar = (especial) => confirmar_acao({
        titulo: 'Restaurar especial',
        icone: 'restore_from_trash',
        rotulo: 'Restaurar',
        mensagem: 'O especial volta para a lista com as opções.',
        chamada: () => api.post(`/especiais/${especial.id}/restaurar`, {}, autorizacao),
        sucesso: () => 'Especial restaurado.',
    });

    // ações pelas acoes da API (FR-006); as mesmas no menu e na gaveta
    const acoes_do_especial = (especial, com_detalhes = true) => (especial.acoes ?? [])
        .filter((chave) => acoes_especial[chave] && (com_detalhes || chave !== 'detalhes'))
        .map((chave) => ({
            chave,
            ...acoes_especial[chave],
            ao_clicar: () => {
                if (chave === 'detalhes') definir_especial_aberto(especial);
                if (chave === 'editar') abrir_com_detalhe(especial, definir_edicao);
                if (chave === 'encerrar') abrir_com_detalhe(especial, (detalhe) => definir_fechamento({ modo: 'encerrar', especial: detalhe }));
                if (chave === 'corrigir_resultado') abrir_com_detalhe(especial, (detalhe) => definir_fechamento({ modo: 'corrigir', especial: detalhe }));
                if (chave === 'cancelar') cancelar(especial);
                if (chave === 'remover') remover(especial);
                if (chave === 'restaurar') restaurar(especial);
            },
        }));

    const etiquetas = etiquetas_especiais(filtros);
    const painel_filtros = filtros_abertos && !e_mobile;
    const titulo_aba = filtros.aba === 'lixeira' ? 'Lixeira' : 'Especiais';

    const filtros_tela = (
        <SpecialsFilters aberto={filtros_abertos} filtros={filtros} ao_aplicar={mudar} ao_fechar={() => definir_filtros_abertos(false)} />
    );

    // cartões (FR-002), só na aba Especiais
    const cartoes = () => {
        if (filtros.aba === 'lixeira') return null;

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
            <>
                <Cards>
                    <StatCard icone="how_to_vote" rotulo="Aceitando palpites" valor={dados.aceitando_palpites} dinheiro={false} titulo="Aguardando, ativos e com a data limite no futuro." />
                    <StatCard icone="hourglass_top" rotulo="Aguardando resultado" valor={dados.aguardando_resultado} dinheiro={false} titulo="Data limite já passou: falta encerrar." />
                    <StatCard icone="payments" rotulo="Valor apostado em aberto" valor={dados.valor_em_aberto} titulo="Apostas ativas sem resultado com palpite especial." />
                    <StatCard icone="trending_up" rotulo="Possível retorno em aberto" valor={dados.possivel_retorno_em_aberto} titulo="Quanto as apostas em aberto pagariam se todas vencessem." />
                </Cards>
                {dados.alcance === 'rede' && <Legend>Números da sua rede</Legend>}
            </>
        );
    };

    return (
        <>
            <Head title={nome_sistema} />
            <PanelShell usuario={logado} titulo={`Especiais · ${titulo_aba} · total ${lista.total.toLocaleString('pt-BR')}`}>
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
                                {filtros.aba === 'especiais' && (
                                    <Tool type="button" aria-expanded={filtros_abertos} onClick={() => definir_filtros_abertos((aberto) => !aberto)}>
                                        <i className="material-icons" aria-hidden="true">filter_alt</i>
                                        {etiquetas.length ? `Filtros (${etiquetas.length})` : 'Filtros'}
                                    </Tool>
                                )}
                                {filtros.aba === 'especiais' && pode('especiais.gerenciar') && (
                                    <Tool type="button" onClick={() => definir_edicao({})}>
                                        <i className="material-icons" aria-hidden="true">add</i>
                                        Novo especial
                                    </Tool>
                                )}
                                <Tool type="button" title="Atualizar" aria-label="Atualizar" $compacto onClick={() => concluir(null)}>
                                    <i className="material-icons" aria-hidden="true">refresh</i>
                                </Tool>
                            </HeaderActions>
                        </Area>
                        {painel_filtros && <Area $area="filtros">{filtros_tela}</Area>}
                        {etiquetas.length > 0 && (
                            <Area $area="etiquetas">
                                <ActiveFilters etiquetas={etiquetas} ao_remover={() => mudar(remover_filtro_especiais())} />
                            </Area>
                        )}
                        <Area $area="busca">
                            <SearchBox>
                                <i className="material-icons" aria-hidden="true">search</i>
                                <Search
                                    type="search"
                                    value={busca}
                                    placeholder="Nome do especial"
                                    aria-label="Buscar especial"
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
                    <SpecialsTable
                        aba={filtros.aba}
                        linhas={lista.linhas}
                        carregando={lista.carregando}
                        erro={lista.erro}
                        ao_tentar_novamente={recarregar}
                        ao_abrir={definir_especial_aberto}
                        acoes={acoes_do_especial}
                    />
                    <Pagination pagina={filtros.pagina} ultima={lista.ultima} ao_trocar={(pagina) => mudar({ pagina })} />
                </Section>
            </PanelShell>

            <SpecialDrawer
                especial={especial_aberto}
                versao={versao_detalhe}
                token={token}
                acoes={(especial) => acoes_do_especial(especial, false)}
                pode_anular={(especial) => especial.situacao === 'Aguardando' && !especial.deleted_at && pode('especiais.encerrar')}
                ao_anular={(especial, opcao) => definir_anulacao({ especial, opcao })}
                ao_fechar={() => definir_especial_aberto(null)}
            />
            <SpecialFormModal
                especial={edicao}
                token={token}
                ao_fechar={() => {
                    definir_edicao(null);
                    recarregar();
                }}
                ao_salvar={(mensagem) => {
                    definir_edicao(null);
                    concluir(mensagem);
                }}
            />
            <SpecialCloseModal
                modo={fechamento?.modo}
                especial={fechamento?.especial}
                token={token}
                ao_fechar={() => {
                    // fechar depois de um erro ("já foi encerrada") também atualiza a lista
                    definir_fechamento(null);
                    concluir(null);
                }}
                ao_concluir={(dados) => {
                    const modo = fechamento?.modo;
                    definir_fechamento(null);
                    concluir(modo === 'corrigir'
                        ? `Resultado corrigido. ${dados.apostas_afetadas} ${dados.apostas_afetadas === 1 ? 'aposta reapurada' : 'apostas reapuradas'}.`
                        : `Especial encerrado. ${quantidade_apostas(dados.apostas_afetadas)}.`);
                }}
            />
            <SpecialVoidOptionModal
                anulacao={anulacao}
                token={token}
                ao_fechar={() => definir_anulacao(null)}
                ao_concluir={(dados) => {
                    definir_anulacao(null);
                    concluir(`Opção anulada. ${quantidade_apostas(dados.apostas_afetadas)}.`);
                }}
            />
            <ConfirmModal
                aberto={Boolean(confirmacao)}
                titulo={confirmacao?.titulo ?? ''}
                icone={confirmacao?.icone}
                mensagem={confirmacao?.mensagem ?? ''}
                rotulo_confirmar={confirmacao?.rotulo}
                ao_confirmar={confirmacao?.executar ?? null}
                ao_fechar={() => definir_confirmacao(null)}
            />
        </>
    );
}
