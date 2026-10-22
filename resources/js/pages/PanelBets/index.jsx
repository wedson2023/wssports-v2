import { useEffect, useRef, useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import Swal from 'sweetalert2';

import ActiveFilters from '../../components/ActiveFilters';
import BetsFilters, { opcoes_resultado } from '../../components/BetsFilters';
import BetsTable from '../../components/BetsTable';
import Pagination from '../../components/Pagination';
import PanelShell from '../../components/PanelShell';
import PanelTabs from '../../components/PanelTabs';
import PeriodFilter from '../../components/PeriodFilter';
import Spinner from '../../components/Spinner';
import StatCard from '../../components/StatCard';
import TicketModal from '../../components/TicketModal';
import WinnersModal from '../../components/WinnersModal';
import useApostasPainel, { parametros_api } from '../../hooks/useApostasPainel';
import useImpressao from '../../hooks/useImpressao';
import usePainel from '../../hooks/usePainel';
import useTelaMobile from '../../hooks/useTelaMobile';
import api from '../../utils/api';
import { alerta_atencao, alerta_erro, confirmar } from '../../utils/alerts';
import { formatar_valor } from '../../utils/charts';
import { compartilhar_texto } from '../../utils/share';
import {
    Cards, Check, Error, FullTabs, Header, HeaderActions, Loading, Retry, Search, SearchBox, SearchRow, Section, Skeleton, Tool,
} from './styles';

// espera depois da última tecla antes de buscar
const ATRASO_BUSCA = 400;

const abas = [
    { chave: 'todas', rotulo: 'Todas', icone: 'receipt_long' },
    { chave: 'pre_jogo', rotulo: 'Pré-jogo', icone: 'schedule' },
    { chave: 'ao_vivo', rotulo: 'Ao vivo', icone: 'play_circle_outline' },
    { chave: 'canceladas', rotulo: 'Canceladas', icone: 'delete_outline' },
];

// itens do menu de três pontinhos de cada linha
const acoes_linha = {
    ver: { icone: 'remove_red_eye', titulo: 'Ver bilhete', tom: '#1e88e5' },
    compartilhar: { icone: 'share', titulo: 'Compartilhar', tom: '#43a047' },
    cancelar: { icone: 'delete', titulo: 'Cancelar aposta', tom: '#e53935' },
};

// busca de 8 letras ou números: pode ser o código de uma aposta (vale em qualquer data)
const parece_codigo = (texto) => /^[A-Za-z0-9]{8}$/.test(texto.trim());

// aviso curto no canto, sem bloquear a tela
const avisar = (texto) => Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: texto, showConfirmButton: false, timer: 2500 });

// nome do arquivo no Content-Disposition
const nome_arquivo = (cabecalho, padrao) => /filename="?([^"]+)"?/.exec(cabecalho ?? '')?.[1] ?? padrao;

// a resposta de erro de um download vem como blob: lê o JSON para mostrar a mensagem do servidor
const ler_erro_blob = async (erro) => {
    if (erro.response?.data instanceof Blob) {
        try {
            erro.response.data = JSON.parse(await erro.response.data.text());
        } catch {
            // sem JSON: mensagem padrão
        }
    }

    return erro;
};

// tela "Apostas" do painel (spec 018): período, abas Todas/Pré-jogo/Ao vivo/Canceladas, busca por
// código ou nome, filtros, cartões de totais, lista paginada, bilhete com 2ª via, cancelar aposta e
// editar palpites, premiados do período e planilha
export default function PanelBets() {
    const { nome_sistema, tema } = usePage().props;
    const { usuario: logado, carregando } = usePainel();
    const pode = (permissao) => Boolean(logado?.permissoes.includes(permissao));
    const apostas = useApostasPainel({ habilitado: pode('apostas.listar') });
    const { filtros, mudar, lista, resumo, recarregar, token } = apostas;
    const { imprimir_bilhete } = useImpressao();
    const e_mobile = useTelaMobile();
    const [busca, definir_busca] = useState(filtros.busca);
    const [filtros_abertos, definir_filtros_abertos] = useState(false);
    const [bilhete, definir_bilhete] = useState(null);
    const [ocupado, definir_ocupado] = useState(false);
    const [premiados_abertos, definir_premiados_abertos] = useState(false);
    const [exportando, definir_exportando] = useState(false);
    const espera = useRef(null);

    const e_vendedor = logado?.funcao === 'Vendedor';
    const autorizacao = { headers: { Authorization: `Bearer ${token}` } };

    // sem acesso à lista, volta para a Dashboard
    useEffect(() => {
        if (logado && !pode('apostas.listar')) router.visit('/painel');
    }, [logado]);

    useEffect(() => () => window.clearTimeout(espera.current), []);

    if (carregando || !logado || !pode('apostas.listar')) {
        return (
            <>
                <Head title={nome_sistema} />
                <Loading><Spinner tamanho={40} cor={tema.temas} /></Loading>
            </>
        );
    }

    const buscar = (texto) => {
        definir_busca(texto);
        window.clearTimeout(espera.current);
        espera.current = window.setTimeout(() => mudar({ busca: texto }), ATRASO_BUSCA);
    };

    // bilhete com as ações permitidas (GET /apostas/{codigo}); mantém o aberto enquanto recarrega
    const abrir_bilhete = async (codigo) => {
        try {
            const { data } = await api.get(`/apostas/${encodeURIComponent(codigo)}`, autorizacao);
            definir_bilhete({ comprovante: data.data, painel: data.painel });
        } catch (erro) {
            alerta_erro(erro);
        }
    };

    const compartilhar = (codigo) => compartilhar_texto(`*ACOMPANHE SEU BILHETE:* \n\n ${window.location.origin}/bilhete/${codigo}`, e_mobile);

    // ação no servidor; depois, aviso, bilhete (se aberto), lista e cartões atualizados sem perder a página
    const executar = async (requisicao, mensagem, codigo) => {
        definir_ocupado(true);

        try {
            await requisicao();
            avisar(mensagem);
            recarregar();

            if (bilhete) await abrir_bilhete(codigo);
        } catch (erro) {
            alerta_erro(erro);
        } finally {
            definir_ocupado(false);
        }
    };

    const cancelar_aposta = async (codigo, do_site) => {
        const destino = do_site ? 'o saldo do cliente' : 'o limite do vendedor';

        if (!(await confirmar(`Cancelar a aposta ${codigo.toUpperCase()}? O valor volta para ${destino}.`))) return;

        await executar(() => api.post(`/apostas/${encodeURIComponent(codigo)}/cancelar`, {}, autorizacao), 'Aposta cancelada.', codigo);
    };

    const editar_palpite = async (palpite, acao) => {
        const codigo = bilhete.comprovante.codigo;
        const texto = acao === 'cancelar' ? 'Cancelar este palpite? O prêmio e a comissão serão recalculados.' : 'Restaurar este palpite? O prêmio e a comissão serão recalculados.';

        if (!(await confirmar(texto))) return;

        await executar(
            () => api.post(`/apostas/${encodeURIComponent(codigo)}/palpites/${palpite.id}/${acao}`, {}, autorizacao),
            acao === 'cancelar' ? 'Palpite cancelado.' : 'Palpite restaurado.',
            codigo,
        );
    };

    // 2ª via: o servidor confere permissão, alcance e situação antes de liberar o comprovante
    const imprimir_segunda_via = async () => {
        try {
            const { data } = await api.get(`/apostas/${encodeURIComponent(bilhete.comprovante.codigo)}/segunda-via`, autorizacao);
            await imprimir_bilhete(data.data, { segunda_via: true });
        } catch (erro) {
            alerta_erro(erro);
        }
    };

    const exportar = async () => {
        const totais = resumo.dados?.totais;

        if (totais && totais.apostas > resumo.dados.limite_planilha) {
            alerta_atencao(`O filtro tem ${totais.apostas.toLocaleString('pt-BR')} apostas; o máximo por planilha é ${resumo.dados.limite_planilha.toLocaleString('pt-BR')}. Reduza o período ou use filtros.`);
            return;
        }

        definir_exportando(true);

        try {
            const resposta = await api.get('/apostas/exportacao', { params: parametros_api(filtros), responseType: 'blob', ...autorizacao });
            const endereco = URL.createObjectURL(resposta.data);
            const link = document.createElement('a');

            link.href = endereco;
            link.download = nome_arquivo(resposta.headers['content-disposition'], 'apostas.xlsx');
            link.click();
            URL.revokeObjectURL(endereco);
        } catch (erro) {
            alerta_erro(await ler_erro_blob(erro));
        } finally {
            definir_exportando(false);
        }
    };

    const acoes_da_linha = (linha) => [
        'ver',
        'compartilhar',
        ...(linha.acoes.includes('cancelar') ? ['cancelar'] : []),
    ].map((chave) => ({
        chave,
        ...acoes_linha[chave],
        ao_clicar: () => {
            if (chave === 'ver') abrir_bilhete(linha.codigo);
            if (chave === 'compartilhar') compartilhar(linha.codigo);
            if (chave === 'cancelar') cancelar_aposta(linha.codigo, linha.origem === 'site');
        },
    }));

    // etiquetas dos filtros ativos (contracts/paginas §3)
    const etiquetas = [
        filtros.resultado && filtros.aba !== 'canceladas' && { chave: 'resultado', texto: opcoes_resultado.find((opcao) => opcao.valor === filtros.resultado)?.rotulo },
        filtros.vendedor && { chave: 'vendedor', texto: `Vendedor: ${filtros.vendedor.nome}` },
        filtros.forma_pagamento && { chave: 'forma_pagamento', texto: filtros.forma_pagamento },
        filtros.premio_minimo && { chave: 'premio_minimo', texto: `Prêmio ≥ ${formatar_valor(filtros.premio_minimo)}` },
    ].filter(Boolean);

    const quantidade_filtros = etiquetas.length;
    const totais = resumo.dados?.totais;
    const canceladas = filtros.aba === 'canceladas';

    const cartoes = () => {
        if (resumo.carregando && !totais) {
            return <Skeleton>{Array.from({ length: 4 }, (_, indice) => <span key={indice} />)}</Skeleton>;
        }

        if (resumo.erro && !totais) {
            return (
                <Error>
                    {resumo.erro.response?.data?.message ?? 'Não foi possível carregar os totais.'}
                    <Retry type="button" onClick={recarregar}>Tentar novamente</Retry>
                </Error>
            );
        }

        if (canceladas) {
            return (
                <Cards>
                    <StatCard icone="delete_outline" rotulo="Canceladas" valor={totais.apostas} dinheiro={false} />
                    <StatCard icone="money_off" rotulo="Valor cancelado" valor={totais.valor_cancelado} />
                </Cards>
            );
        }

        return (
            <Cards>
                <StatCard icone="receipt" rotulo="Apostas" valor={totais.apostas} dinheiro={false} titulo={`${totais.abertas} abertas`} />
                <StatCard icone="bar_chart" rotulo="Entradas" valor={totais.entradas} titulo="Valor apostado nas apostas do filtro." />
                <StatCard icone="monetization_on" rotulo="Comissão" valor={totais.comissao} titulo="Comissão gravada nas apostas do filtro." />
                <StatCard icone="emoji_events" rotulo="Saídas" valor={totais.saidas} titulo="Mesma regra do Caixa: prêmio sem o valor acrescido + devoluções." />
                <StatCard icone="access_time" rotulo={`Em aberto (${totais.abertas})`} valor={totais.em_aberto} titulo="Valor apostado nas apostas ainda sem resultado." />
                {totais.encerramentos !== null && (
                    <StatCard icone="price_check" rotulo="Encerramentos" valor={totais.encerramentos} titulo="Encerramentos das apostas feitas no período." />
                )}
            </Cards>
        );
    };

    return (
        <>
            <Head title={nome_sistema} />
            <PanelShell usuario={logado} titulo={`Apostas · total ${lista.total.toLocaleString('pt-BR')}`}>
                <Section>
                    <Header>
                        <PeriodFilter periodo={filtros.periodo} ao_mudar={(periodo) => mudar({ periodo })} />
                        <HeaderActions>
                            <Tool type="button" aria-expanded={filtros_abertos} onClick={() => definir_filtros_abertos((aberto) => !aberto)}>
                                <i className="material-icons" aria-hidden="true">filter_list</i>
                                {quantidade_filtros ? `Filtros (${quantidade_filtros})` : 'Filtros'}
                            </Tool>
                            {pode('apostas.exportar') && !e_vendedor && (
                                <Tool type="button" onClick={() => definir_premiados_abertos(true)}>
                                    <i className="material-icons" aria-hidden="true">emoji_events</i>
                                    Premiados
                                </Tool>
                            )}
                            {pode('apostas.exportar') && (
                                <Tool type="button" disabled={exportando} onClick={exportar}>
                                    {exportando ? <Spinner tamanho={18} cor="currentColor" /> : <i className="material-icons" aria-hidden="true">table_view</i>}
                                    Baixar planilha
                                </Tool>
                            )}
                            <Tool type="button" title="Atualizar" aria-label="Atualizar" $compacto onClick={recarregar}>
                                <i className="material-icons" aria-hidden="true">refresh</i>
                            </Tool>
                        </HeaderActions>
                    </Header>

                    <SearchRow>
                        <SearchBox>
                            <i className="material-icons" aria-hidden="true">search</i>
                            <Search
                                type="search"
                                value={busca}
                                placeholder="Código ou nome do cliente"
                                aria-label="Buscar por código ou nome do cliente"
                                onChange={(evento) => buscar(evento.target.value)}
                                onKeyDown={(evento) => {
                                    if (evento.key !== 'Enter') return;

                                    window.clearTimeout(espera.current);
                                    mudar({ busca });
                                }}
                            />
                        </SearchBox>
                        {busca.trim() && (
                            <Check>
                                <input type="checkbox" checked={filtros.todo_periodo} onChange={(evento) => mudar({ todo_periodo: evento.target.checked })} />
                                {parece_codigo(busca) ? 'Nome em todo o período (o código vale em qualquer data)' : 'Buscar em todo o período'}
                            </Check>
                        )}
                    </SearchRow>

                    <BetsFilters
                        aberto={filtros_abertos}
                        filtros={filtros}
                        aba={filtros.aba}
                        e_vendedor={e_vendedor}
                        token={token}
                        ao_aplicar={(mudanca) => mudar(mudanca)}
                        ao_fechar={() => definir_filtros_abertos(false)}
                    />

                    <ActiveFilters
                        etiquetas={etiquetas}
                        ao_remover={(chave) => mudar({ [chave]: null })}
                    />

                    <FullTabs><PanelTabs abas={abas} ativa={filtros.aba} ao_trocar={(aba) => mudar({ aba })} /></FullTabs>

                    {cartoes()}

                    <BetsTable
                        linhas={lista.linhas}
                        aba={filtros.aba}
                        carregando={lista.carregando}
                        erro={lista.erro}
                        ao_tentar_novamente={recarregar}
                        ao_abrir={(linha) => abrir_bilhete(linha.codigo)}
                        acoes={acoes_da_linha}
                    />
                    <Pagination pagina={filtros.pagina} ultima={lista.ultima} ao_trocar={(pagina) => mudar({ pagina })} />
                </Section>
            </PanelShell>

            <TicketModal
                comprovante={bilhete?.comprovante ?? null}
                painel={bilhete?.painel ?? null}
                ocupado={ocupado}
                ao_fechar={() => definir_bilhete(null)}
                ao_imprimir={imprimir_segunda_via}
                ao_cancelar_aposta={() => cancelar_aposta(bilhete.comprovante.codigo, bilhete.comprovante.vendedor === 'Cliente')}
                ao_cancelar_palpite={(palpite) => editar_palpite(palpite, 'cancelar')}
                ao_restaurar_palpite={(palpite) => editar_palpite(palpite, 'restaurar')}
            />
            <WinnersModal
                aberto={premiados_abertos}
                parametros={{ de: filtros.periodo.de, ate: filtros.periodo.ate, aba: filtros.aba, ...(filtros.vendedor ? { usuarios_id: filtros.vendedor.id } : {}) }}
                token={token}
                ao_fechar={() => definir_premiados_abertos(false)}
            />
        </>
    );
}
