import { useEffect, useMemo, useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import Swal from 'sweetalert2';

import BetsByPicksModal from '../../components/BetsByPicksModal';
import CashBreadcrumb from '../../components/CashBreadcrumb';
import CashEntryModal from '../../components/CashEntryModal';
import CashTable from '../../components/CashTable';
import CashTransactionsModal from '../../components/CashTransactionsModal';
import ManagerCommissionModal from '../../components/ManagerCommissionModal';
import Pagination from '../../components/Pagination';
import PanelShell from '../../components/PanelShell';
import PanelTabs from '../../components/PanelTabs';
import PeriodFilter from '../../components/PeriodFilter';
import SellerLimitsModal from '../../components/SellerLimitsModal';
import SettlementModal from '../../components/SettlementModal';
import Spinner from '../../components/Spinner';
import StatCard from '../../components/StatCard';
import useCaixa from '../../hooks/useCaixa';
import usePainel from '../../hooks/usePainel';
import useSessao from '../../hooks/useSessao';
import api from '../../utils/api';
import { alerta_erro } from '../../utils/alerts';
import { periodo_do_atalho, validar_periodo } from '../../utils/periods';
import {
    Block, BlockTitle, Cards, Error, FullTabs, Header, HeaderActions, Loading, Notice, Retry, Section, Skeleton, Tool,
} from './styles';

const abas_tela = [
    { chave: 'rede', rotulo: 'Rede', icone: 'account_tree', permissao: 'caixa.ver' },
    { chave: 'clientes', rotulo: 'Clientes', icone: 'groups', permissao: 'caixa.ver_clientes', funcoes: ['Admin', 'Supervisor'] },
];

const abas_tipo = [
    { chave: 'todas', rotulo: 'Todas', icone: 'receipt_long' },
    { chave: 'pre_jogo', rotulo: 'Pré-jogo', icone: 'schedule' },
    { chave: 'ao_vivo', rotulo: 'Ao vivo', icone: 'play_circle_outline' },
];

// cartões da aba Rede na ordem de contracts/paginas §3.3
const cartoes_rede = [
    { chave: 'entradas', rotulo: 'Entradas', icone: 'bar_chart', titulo: 'Valor apostado nas apostas do balcão feitas no período.' },
    { chave: 'em_aberto', rotulo: 'Em aberto', icone: 'access_time', titulo: 'Valor apostado nas apostas ainda sem resultado.' },
    { chave: 'valor_premiadas', rotulo: 'Premiadas', icone: 'attach_money', titulo: 'Valor apostado nas apostas premiadas.' },
    { chave: 'valor_perdidas', rotulo: 'Perdidas', icone: 'clear', titulo: 'Valor apostado nas apostas perdidas.' },
    { chave: 'saidas', rotulo: 'Saídas', icone: 'emoji_events', titulo: 'Prêmios pagos e valores devolvidos.' },
    { chave: 'comissao_vendedor', rotulo: 'Comissão vendedor', icone: 'monetization_on', titulo: 'Comissão dos vendedores sobre as apostas.' },
    { chave: 'comissao_gerente', rotulo: 'Comissão gerente', icone: 'pie_chart', titulo: 'Calculada sobre o período inteiro; a soma de dias separados pode ser diferente.' },
    { chave: 'lancamentos', rotulo: 'Lançamentos', icone: 'redo', titulo: 'Suprimentos entregues aos vendedores no período.', caixa: true },
    { chave: 'retiradas', rotulo: 'Retiradas', icone: 'undo', titulo: 'Dinheiro recolhido dos vendedores no período.', caixa: true },
    { chave: 'liquido', rotulo: 'Líquido', icone: 'account_balance_wallet', titulo: 'Entradas − saídas − comissões.', destaque: true },
    { chave: 'saldo_caixa', rotulo: 'Saldo de caixa', icone: 'savings', titulo: 'O que os vendedores devem desde a última prestação de contas.', destaque: true, caixa: true },
];

const cartoes_limites = [
    { chave: 'geral', rotulo: 'Limite geral' },
    { chave: 'duplo', rotulo: 'Limite duplo' },
    { chave: 'simples', rotulo: 'Limite simples' },
];

// quantidades e valores por resultado: só nos detalhes da linha (botão de expandir)
const detalhes_apostas = [
    { chave: 'abertas', rotulo: 'Apostas abertas', tipo: 'numero', nivel: 'detalhe' },
    { chave: 'premiadas', rotulo: 'Apostas premiadas', tipo: 'numero', nivel: 'detalhe' },
    { chave: 'perdidas', rotulo: 'Apostas perdidas', tipo: 'numero', nivel: 'detalhe' },
    { chave: 'em_aberto', rotulo: 'Em aberto', tipo: 'dinheiro', nivel: 'detalhe' },
    { chave: 'valor_premiadas', rotulo: 'Premiadas', tipo: 'dinheiro', nivel: 'detalhe' },
    { chave: 'valor_perdidas', rotulo: 'Perdidas', tipo: 'dinheiro', nivel: 'detalhe' },
];

// colunas das listas da aba Rede: principal = sempre na linha; secundaria = na linha só no
// computador; detalhe = só ao expandir a linha (no celular, as secundárias também vão para lá)
const colunas_vendedor = [
    { chave: 'apostas', rotulo: 'Apostas', tipo: 'numero', nivel: 'secundaria' },
    { chave: 'entradas', rotulo: 'Entradas', tipo: 'dinheiro', nivel: 'secundaria' },
    { chave: 'lancamentos', rotulo: 'Lançamentos', tipo: 'dinheiro', nivel: 'secundaria', caixa: true },
    { chave: 'retiradas', rotulo: 'Retiradas', tipo: 'dinheiro', nivel: 'secundaria', caixa: true },
    { chave: 'limites', rotulo: 'Limites', tipo: 'limites', nivel: 'secundaria', dica: 'Geral / duplo / simples' },
    { chave: 'comissao_vendedor', rotulo: 'Comissão', tipo: 'dinheiro', nivel: 'secundaria' },
    { chave: 'saidas', rotulo: 'Saídas', tipo: 'dinheiro', nivel: 'secundaria' },
    { chave: 'liquido', rotulo: 'Líquido', tipo: 'dinheiro', cor: true, nivel: 'principal' },
    { chave: 'saldo_caixa', rotulo: 'Saldo de caixa', tipo: 'dinheiro', cor: true, nivel: 'principal', caixa: true },
    ...detalhes_apostas,
    { chave: 'recebido_periodo', rotulo: 'Recebido no período', tipo: 'dinheiro', nivel: 'detalhe', caixa: true },
];

const colunas_gestor = [
    { chave: 'apostas', rotulo: 'Apostas', tipo: 'numero', nivel: 'secundaria' },
    { chave: 'entradas', rotulo: 'Entradas', tipo: 'dinheiro', nivel: 'secundaria' },
    { chave: 'comissao_vendedor', rotulo: 'Com. vendedor', tipo: 'dinheiro', nivel: 'secundaria' },
    { chave: 'comissao_gerente', rotulo: 'Com. gerente', tipo: 'dinheiro', nivel: 'secundaria' },
    { chave: 'saidas', rotulo: 'Saídas', tipo: 'dinheiro', nivel: 'secundaria' },
    { chave: 'lancamentos', rotulo: 'Lançamentos', tipo: 'dinheiro', nivel: 'secundaria', caixa: true },
    { chave: 'retiradas', rotulo: 'Retiradas', tipo: 'dinheiro', nivel: 'secundaria', caixa: true },
    { chave: 'recebido_periodo', rotulo: 'Recebido', tipo: 'dinheiro', nivel: 'secundaria', caixa: true, dica: 'Recebido nas prestações de contas do período' },
    { chave: 'liquido', rotulo: 'Líquido', tipo: 'dinheiro', cor: true, nivel: 'principal' },
    { chave: 'saldo_caixa', rotulo: 'Saldo de caixa', tipo: 'dinheiro', cor: true, nivel: 'principal', caixa: true },
    ...detalhes_apostas,
    { chave: 'percentual_comissao_gerente', rotulo: '% comissão do gerente', tipo: 'percentual', nivel: 'detalhe', so_gerente: true },
];

const colunas_clientes = [
    { chave: 'apostas', rotulo: 'Apostas', tipo: 'numero', nivel: 'secundaria' },
    { chave: 'entradas', rotulo: 'Entradas', tipo: 'dinheiro', nivel: 'secundaria' },
    { chave: 'saidas', rotulo: 'Saídas', tipo: 'dinheiro', nivel: 'secundaria' },
    { chave: 'encerramentos', rotulo: 'Encerramentos', tipo: 'dinheiro', nivel: 'secundaria' },
    { chave: 'resultado_cassino', rotulo: 'Resultado cassino', tipo: 'dinheiro', cor: true, nivel: 'secundaria' },
    { chave: 'bonus_convertidos', rotulo: 'Bônus convertidos', tipo: 'dinheiro', nivel: 'secundaria' },
    { chave: 'depositos', rotulo: 'Depósitos', tipo: 'dinheiro', nivel: 'secundaria' },
    { chave: 'saques', rotulo: 'Saques', tipo: 'dinheiro', nivel: 'secundaria' },
    { chave: 'liquido', rotulo: 'Líquido', tipo: 'dinheiro', cor: true, nivel: 'principal' },
    { chave: 'telefone', rotulo: 'Telefone', tipo: 'texto', nivel: 'detalhe' },
    { chave: 'email', rotulo: 'E-mail', tipo: 'texto', nivel: 'detalhe' },
];

// blocos de cartões da aba Clientes (contracts/paginas §5)
const blocos_clientes = [
    {
        titulo: 'Esportes',
        cartoes: [
            { caminho: ['esportes', 'entradas'], rotulo: 'Entradas', icone: 'bar_chart', titulo: 'Apostas online pagas com saldo real (sem saldo de promoção).' },
            { caminho: ['esportes', 'em_aberto'], rotulo: 'Em aberto', icone: 'access_time' },
            { caminho: ['esportes', 'premiadas'], rotulo: 'Premiadas', icone: 'attach_money' },
            { caminho: ['esportes', 'perdidas'], rotulo: 'Perdidas', icone: 'clear' },
            { caminho: ['esportes', 'saidas'], rotulo: 'Saídas', icone: 'emoji_events' },
            { caminho: ['esportes', 'encerramentos'], rotulo: 'Encerramentos', icone: 'price_check', titulo: 'Valor pago em encerramentos (cash out).' },
            { caminho: ['esportes', 'saldo_liquido'], rotulo: 'Saldo líquido', icone: 'account_balance_wallet', titulo: 'Entradas − saídas − encerramentos.', destaque: true },
        ],
    },
    {
        titulo: 'Cassino',
        cartoes: [
            { caminho: ['cassino', 'entradas'], rotulo: 'Entradas', icone: 'casino', titulo: 'Valor apostado nas rodadas com saldo real.' },
            { caminho: ['cassino', 'saidas'], rotulo: 'Saídas', icone: 'emoji_events', titulo: 'Ganhos pagos nas rodadas com saldo real.' },
            { caminho: ['cassino', 'resultado'], rotulo: 'Resultado', icone: 'trending_up', destaque: true },
            { caminho: ['cassino', 'comissao_provedores'], rotulo: 'Comissão dos provedores', icone: 'handshake' },
        ],
    },
    {
        titulo: 'Bônus',
        cartoes: [
            { caminho: ['bonus', 'concedidos'], rotulo: 'Concedidos', icone: 'card_giftcard', titulo: 'Bônus creditados nas carteiras de promoção: não é dinheiro de verdade.' },
            { caminho: ['bonus', 'convertidos'], rotulo: 'Convertidos', icone: 'currency_exchange', titulo: 'Bônus que passaram para o saldo real.' },
        ],
    },
    {
        titulo: 'Movimento',
        cartoes: [
            { caminho: ['depositos'], rotulo: 'Depósitos', icone: 'credit_card' },
            { caminho: ['saques'], rotulo: 'Saques', icone: 'payments' },
        ],
    },
];

// cor e ícone de cada ação das linhas
const acoes_linha = {
    lancar: { icone: 'redo', titulo: 'Lançamento', tom: '#1e88e5' },
    prestar_contas: { icone: 'get_app', titulo: 'Prestar contas', tom: '#43a047' },
    transacoes: { icone: 'compare_arrows', titulo: 'Transações', tom: '#757575' },
    ajustar_limite: { icone: 'attach_money', titulo: 'Ajustar limite', tom: '#f9a825' },
    grafico: { icone: 'insert_chart_outlined', titulo: 'Gráfico', tom: '#8e24aa' },
    comissao_gerente: { icone: 'pie_chart', titulo: 'Comissão do gerente', tom: '#f9a825' },
    exportar: { icone: 'table_view', titulo: 'Baixar planilha', tom: '#00897b' },
    // atalho para a lista de apostas do vendedor (spec 018, FR-025)
    ver_apostas: { icone: 'receipt_long', titulo: 'Ver apostas', tom: '#1e88e5' },
};

// aviso curto no canto, sem bloquear a tela
const avisar = (texto) => Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: texto, showConfirmButton: false, timer: 2500 });

// filtros da URL (?aba=&tipo=&de=&ate=&usuarios_id=); período inválido vale hoje
const filtros_da_url = () => {
    const parametros = new URLSearchParams(window.location.search);
    const periodo = { de: parametros.get('de') ?? '', ate: parametros.get('ate') ?? '' };

    return {
        aba: parametros.get('aba') === 'clientes' ? 'clientes' : 'rede',
        tipo: abas_tipo.some((aba) => aba.chave === parametros.get('tipo')) ? parametros.get('tipo') : 'todas',
        periodo: validar_periodo(periodo) ? periodo_do_atalho('hoje') : periodo,
        foco: Number(parametros.get('usuarios_id')) || null,
    };
};

// nome do arquivo no Content-Disposition
const nome_arquivo = (cabecalho, padrao) => /filename="?([^"]+)"?/.exec(cabecalho ?? '')?.[1] ?? padrao;

// tela "Caixa" do painel (spec 017): aba Rede (cartões, trilha de níveis, tipo de aposta, lista com
// lançamento, prestação de contas, transações, limite, gráfico, comissão e planilha) e aba Clientes
// (esportes, cassino, bônus, movimento e resultado do site); o vendedor vê só o próprio caixa
export default function PanelCash() {
    const { nome_sistema, tema } = usePage().props;
    const { sessao } = useSessao();
    const token = sessao?.token;
    const { usuario: logado, carregando } = usePainel();
    const [filtros, definir_filtros] = useState(filtros_da_url);
    const [modal, definir_modal] = useState(null);
    const [exportando, definir_exportando] = useState(null);

    const pode = (permissao) => Boolean(logado?.permissoes.includes(permissao));
    const e_vendedor = logado?.funcao === 'Vendedor';
    const abas = useMemo(
        () => (logado ? abas_tela.filter((aba) => pode(aba.permissao) && (!aba.funcoes || aba.funcoes.includes(logado.funcao))) : []),
        [logado],
    );
    const aba = abas.some((item) => item.chave === filtros.aba) ? filtros.aba : abas[0]?.chave;

    const caixa = useCaixa({
        aba,
        periodo: filtros.periodo,
        tipo: filtros.tipo,
        foco: aba === 'rede' ? filtros.foco : null,
        com_lista: aba === 'clientes' || !e_vendedor,
        habilitado: Boolean(logado && aba),
    });

    // sem acesso ao caixa, volta para a Dashboard
    useEffect(() => {
        if (logado && !abas.length) router.visit('/painel');
    }, [logado]);

    // voltar do navegador refaz os filtros
    useEffect(() => {
        const ao_voltar = () => definir_filtros(filtros_da_url());
        window.addEventListener('popstate', ao_voltar);

        return () => window.removeEventListener('popstate', ao_voltar);
    }, []);

    // filtros na URL: trocar de nível empilha (para o voltar), o resto substitui
    const mudar = (novos, empilhar = false) => {
        const proximos = { ...filtros, ...novos };
        const parametros = new URLSearchParams({ aba: proximos.aba, tipo: proximos.tipo, de: proximos.periodo.de, ate: proximos.periodo.ate });

        if (proximos.foco) parametros.set('usuarios_id', proximos.foco);

        window.history[empilhar ? 'pushState' : 'replaceState'](window.history.state, '', `?${parametros}`);
        definir_filtros(proximos);
    };

    if (carregando || !aba) {
        return (
            <>
                <Head title={nome_sistema} />
                <Loading><Spinner tamanho={40} cor={tema.temas} /></Loading>
            </>
        );
    }

    const resumo = caixa.resumo.dados;
    const foco = resumo?.foco;
    const totais = resumo?.totais;
    const com_caixa = filtros.tipo === 'todas';
    const funcao_lista = foco?.funcao === 'Banca' ? 'Supervisor' : ({ Supervisor: 'Gerente', Gerente: 'Vendedor' })[foco?.funcao];

    const concluir = (mensagem) => {
        definir_modal(null);
        caixa.recarregar();
        if (mensagem) avisar(mensagem);
    };

    const exportar = async (usuarios_id, chave) => {
        definir_exportando(chave);

        try {
            const params = aba === 'clientes'
                ? { escopo: 'clientes', ...filtros.periodo }
                : { escopo: 'rede', tipo: filtros.tipo, ...filtros.periodo, ...(usuarios_id ? { usuarios_id } : {}) };
            const resposta = await api.get('/caixa/exportar', { params, responseType: 'blob', headers: { Authorization: `Bearer ${token}` } });
            const endereco = URL.createObjectURL(resposta.data);
            const link = document.createElement('a');

            link.href = endereco;
            link.download = nome_arquivo(resposta.headers['content-disposition'], 'caixa.xlsx');
            link.click();
            URL.revokeObjectURL(endereco);
        } catch (erro) {
            // a resposta do erro vem como blob: lê o JSON para mostrar a mensagem do servidor
            if (erro.response?.data instanceof Blob) {
                try {
                    erro.response.data = JSON.parse(await erro.response.data.text());
                } catch {
                    // sem JSON: mensagem padrão
                }
            }

            alerta_erro(erro);
        } finally {
            definir_exportando(null);
        }
    };

    // apostas do vendedor com o período e o tipo do caixa (spec 018); só no frontend: as ações da API não mudam
    const ver_apostas = (linha) => router.visit(`/painel/apostas?${new URLSearchParams({
        usuarios_id: linha.id, vendedor: linha.nome, de: filtros.periodo.de, ate: filtros.periodo.ate, aba: filtros.tipo,
    })}`);

    // itens do menu de três pontinhos de uma linha da Rede
    const acoes_da_linha = (linha) => [
        ...linha.acoes,
        ...(funcao_lista === 'Vendedor' && pode('apostas.listar') ? ['ver_apostas'] : []),
    ].map((chave) => ({
        chave,
        ...acoes_linha[chave],
        titulo: chave === 'prestar_contas' && funcao_lista === 'Gerente' ? 'Prestar contas de todos' : acoes_linha[chave].titulo,
        ao_clicar: () => {
            if (chave === 'exportar') {
                exportar(linha.id, `linha-${linha.id}`);
                return;
            }

            if (chave === 'ver_apostas') {
                ver_apostas(linha);
                return;
            }

            const tipo = funcao_lista === 'Gerente' ? 'gerente' : 'vendedor';
            definir_modal({ chave, alvo: { ...linha, tipo } });
        },
    }));

    const valor_de = (caminho) => caminho.reduce((atual, parte) => atual?.[parte], resumo);

    const cartoes = () => {
        if (caixa.resumo.carregando && !resumo) {
            return <Skeleton>{Array.from({ length: 8 }, (_, indice) => <span key={indice} />)}</Skeleton>;
        }

        if (caixa.resumo.erro && !resumo) {
            return (
                <Error>
                    {caixa.resumo.erro.response?.data?.message ?? 'Não foi possível carregar o caixa.'}
                    <Retry type="button" onClick={caixa.recarregar}>Tentar novamente</Retry>
                </Error>
            );
        }

        if (aba === 'clientes') {
            return (
                <>
                    {blocos_clientes.map((bloco) => (
                        <Block key={bloco.titulo}>
                            <BlockTitle>{bloco.titulo}</BlockTitle>
                            <Cards>
                                {bloco.cartoes.map((cartao) => (
                                    <StatCard key={cartao.caminho.join('.')} icone={cartao.icone} rotulo={cartao.rotulo} valor={valor_de(cartao.caminho)} titulo={cartao.titulo} destaque={cartao.destaque} />
                                ))}
                            </Cards>
                        </Block>
                    ))}
                    <Block>
                        <BlockTitle>Resultado do site</BlockTitle>
                        <Cards>
                            <StatCard
                                icone="insights"
                                rotulo="Resultado do site"
                                valor={resumo.resultado_site}
                                titulo="Saldo líquido de esportes + resultado do cassino − comissão dos provedores − bônus convertidos."
                                destaque
                            />
                        </Cards>
                    </Block>
                </>
            );
        }

        return (
            <Cards>
                <StatCard
                    icone="receipt"
                    rotulo="Apostas"
                    valor={totais.apostas}
                    dinheiro={false}
                    titulo={`${totais.abertas} abertas · ${totais.premiadas} premiadas · ${totais.perdidas} perdidas`}
                />
                {cartoes_rede
                    .filter((cartao) => (cartao.caixa ? com_caixa : true) && (cartao.chave !== 'comissao_gerente' || totais.comissao_gerente !== null))
                    .map((cartao) => (
                        <StatCard
                            key={cartao.chave}
                            icone={cartao.icone}
                            rotulo={cartao.chave === 'comissao_gerente' && totais.percentual_comissao_gerente !== null ? `Comissão gerente (${String(Number(totais.percentual_comissao_gerente)).replace('.', ',')}%)` : cartao.rotulo}
                            valor={totais[cartao.chave]}
                            titulo={cartao.titulo}
                            destaque={cartao.destaque}
                        />
                    ))}
                {totais.limites && cartoes_limites.map((cartao) => (
                    <StatCard
                        key={cartao.chave}
                        icone="lock"
                        rotulo={cartao.rotulo}
                        valor={totais.limites[cartao.chave]}
                        cadeado={Number(totais.limites[cartao.chave]) > 0 ? 'aberto' : 'fechado'}
                    />
                ))}
            </Cards>
        );
    };

    const colunas = (() => {
        if (aba === 'clientes') return colunas_clientes;

        const base = funcao_lista === 'Vendedor' ? colunas_vendedor : colunas_gestor;

        // no celular cabe um valor por linha: o saldo de caixa em Todas; nas outras abas, o líquido
        return base
            .filter((coluna) => (coluna.caixa ? com_caixa : true) && (!coluna.so_gerente || funcao_lista === 'Gerente'))
            .map((coluna) => (coluna.chave === 'liquido' && com_caixa ? { ...coluna, nivel: 'secundaria' } : coluna));
    })();

    const opcoes_ordenacao = aba === 'clientes'
        ? [{ chave: 'nome', rotulo: 'Nome' }, { chave: 'entradas', rotulo: 'Entradas' }, { chave: 'resultado', rotulo: 'Resultado' }]
        : [
            { chave: 'nome', rotulo: 'Nome' },
            { chave: 'entradas', rotulo: 'Entradas' },
            { chave: 'liquido', rotulo: 'Líquido' },
            ...(com_caixa ? [{ chave: 'saldo_caixa', rotulo: 'Saldo de caixa' }] : []),
        ];

    const vendedor_logado = e_vendedor && foco ? { id: foco.id, nome: foco.nome, situacao: 'ativo' } : null;
    const pode_lote = foco?.funcao === 'Gerente' && pode('caixa.prestar_contas');

    return (
        <>
            <Head title={nome_sistema} />
            <PanelShell usuario={logado} titulo="Caixa">
                <Section>
                    {abas.length > 1 && <FullTabs><PanelTabs abas={abas} ativa={aba} ao_trocar={(chave) => mudar({ aba: chave, foco: null })} /></FullTabs>}

                    <Header>
                        <PeriodFilter periodo={filtros.periodo} ao_mudar={(periodo) => mudar({ periodo })} />
                        <HeaderActions>
                            {aba === 'rede' && pode_lote && (
                                <Tool type="button" onClick={() => definir_modal({ chave: 'prestar_contas', alvo: { id: foco.id, nome: foco.nome, tipo: 'gerente' } })}>
                                    <i className="material-icons" aria-hidden="true">get_app</i>
                                    Prestar contas de todos
                                </Tool>
                            )}
                            {pode('caixa.exportar') && (
                                <Tool type="button" disabled={exportando === 'cabecalho'} onClick={() => exportar(aba === 'rede' ? filtros.foco : null, 'cabecalho')}>
                                    {exportando === 'cabecalho' ? <Spinner tamanho={18} cor="currentColor" /> : <i className="material-icons" aria-hidden="true">table_view</i>}
                                    Baixar planilha
                                </Tool>
                            )}
                            <Tool type="button" title="Atualizar" aria-label="Atualizar" $compacto onClick={caixa.recarregar}>
                                <i className="material-icons" aria-hidden="true">refresh</i>
                            </Tool>
                        </HeaderActions>
                    </Header>

                    {aba === 'rede' && (
                        <>
                            <CashBreadcrumb trilha={resumo?.trilha} ao_abrir={(id) => mudar({ foco: id }, true)} />
                            <FullTabs><PanelTabs abas={abas_tipo} ativa={filtros.tipo} ao_trocar={(tipo) => mudar({ tipo })} /></FullTabs>
                            {!com_caixa && <Notice>Lançamentos, retiradas e saldo de caixa aparecem só em Todas.</Notice>}
                        </>
                    )}

                    {cartoes()}

                    {vendedor_logado && (
                        <HeaderActions>
                            <Tool type="button" onClick={() => definir_modal({ chave: 'transacoes', alvo: vendedor_logado })}>
                                <i className="material-icons" aria-hidden="true">compare_arrows</i>
                                Transações
                            </Tool>
                            <Tool type="button" onClick={() => definir_modal({ chave: 'grafico', alvo: vendedor_logado })}>
                                <i className="material-icons" aria-hidden="true">insert_chart_outlined</i>
                                Gráfico
                            </Tool>
                        </HeaderActions>
                    )}

                    {(aba === 'clientes' || !e_vendedor) && (
                        <>
                            <CashTable
                                linhas={caixa.lista.linhas}
                                colunas={colunas}
                                carregando={caixa.lista.carregando}
                                erro={caixa.lista.erro}
                                ao_tentar_novamente={caixa.recarregar}
                                ao_abrir={aba === 'rede' && funcao_lista !== 'Vendedor' ? (linha) => mudar({ foco: linha.id }, true) : null}
                                acoes={aba === 'rede' ? acoes_da_linha : null}
                                subtitulo={aba === 'clientes' ? (linha) => linha.email || linha.telefone : (linha) => linha.login}
                                placeholder_busca={aba === 'clientes' ? 'Buscar por nome, telefone ou e-mail' : 'Buscar por nome ou login'}
                                busca={caixa.busca}
                                ao_buscar={caixa.buscar}
                                ordenacao={caixa.ordenacao}
                                opcoes_ordenacao={opcoes_ordenacao}
                                ao_ordenar={caixa.ordenar}
                            />
                            <Pagination pagina={caixa.pagina} ultima={caixa.lista.ultima} ao_trocar={caixa.definir_pagina} />
                        </>
                    )}
                </Section>
            </PanelShell>

            <CashEntryModal vendedor={modal?.chave === 'lancar' ? modal.alvo : null} token={token} ao_fechar={() => definir_modal(null)} ao_concluir={concluir} />
            <SettlementModal alvo={modal?.chave === 'prestar_contas' ? modal.alvo : null} token={token} ao_fechar={() => definir_modal(null)} ao_concluir={concluir} />
            <CashTransactionsModal
                vendedor={modal?.chave === 'transacoes' ? modal.alvo : null}
                token={token}
                ao_fechar={() => {
                    definir_modal(null);
                    caixa.recarregar();
                }}
                ao_alterar={avisar}
            />
            <SellerLimitsModal vendedor={modal?.chave === 'ajustar_limite' ? modal.alvo : null} token={token} ao_fechar={() => definir_modal(null)} ao_concluir={concluir} />
            <ManagerCommissionModal gerente={modal?.chave === 'comissao_gerente' ? modal.alvo : null} token={token} ao_fechar={() => definir_modal(null)} ao_concluir={concluir} />
            <BetsByPicksModal vendedor={modal?.chave === 'grafico' ? modal.alvo : null} periodo={filtros.periodo} token={token} ao_fechar={() => definir_modal(null)} />
        </>
    );
}
