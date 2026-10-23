import { useEffect, useRef, useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import Swal from 'sweetalert2';

import ActiveFilters from '../../components/ActiveFilters';
import AnticipateModal from '../../components/AnticipateModal';
import BalanceAdjustModal from '../../components/BalanceAdjustModal';
import ClientDrawer from '../../components/ClientDrawer';
import ClientFormModal from '../../components/ClientFormModal';
import ClientsFilters, { etiquetas_filtros, remover_filtro } from '../../components/ClientsFilters';
import ClientsTable from '../../components/ClientsTable';
import Pagination from '../../components/Pagination';
import PanelShell from '../../components/PanelShell';
import PanelTabs from '../../components/PanelTabs';
import PushNotificationModal from '../../components/PushNotificationModal';
import RankingTable from '../../components/RankingTable';
import RestoreConflictModal from '../../components/RestoreConflictModal';
import Spinner from '../../components/Spinner';
import StatCard from '../../components/StatCard';
import WithdrawalDecisionModal from '../../components/WithdrawalDecisionModal';
import WithdrawalsTable from '../../components/WithdrawalsTable';
import useClientesPainel, { parametros_api } from '../../hooks/useClientesPainel';
import usePainel from '../../hooks/usePainel';
import useTelaMobile from '../../hooks/useTelaMobile';
import api from '../../utils/api';
import { alerta_atencao, alerta_erro, confirmar } from '../../utils/alerts';
import {
    Area, Cards, Chip, Chips, Choices, DateField, DateInput, DateRow, Error, FullTabs, HeaderActions, Loading, Retry, Search, SearchBox, Section, Skeleton, Tool, Top,
} from './styles';

// espera depois da última tecla antes de buscar
const ATRASO_BUSCA = 400;

// máximo de linhas por planilha (FR-030)
const LIMITE_PLANILHA = 50000;

const abas = [
    { chave: 'cadastros', rotulo: 'Cadastros', icone: 'receipt', permissao: 'clientes.listar' },
    { chave: 'saques', rotulo: 'Saques', icone: 'download', permissao: 'saques.gerenciar' },
    { chave: 'ranking', rotulo: 'Ranking', icone: 'filter_list', permissao: 'clientes.ver_ranking' },
    { chave: 'lixeira', rotulo: 'Lixeira', icone: 'delete_outline', permissao: 'clientes.restaurar' },
];

const situacoes_saque = [
    { chave: 'Pendente', rotulo: 'Pendentes' },
    { chave: 'Processando', rotulo: 'Processando' },
    { chave: 'Pago', rotulo: 'Pagos' },
    { chave: 'Recusado', rotulo: 'Recusados' },
];

const periodos_ranking = [
    { chave: '24h', rotulo: '24 horas' },
    { chave: '7d', rotulo: '7 dias' },
    { chave: '30d', rotulo: '30 dias' },
    { chave: '90d', rotulo: '90 dias' },
    { chave: 'personalizado', rotulo: 'Personalizado' },
];

const criterios_ranking = [
    { chave: 'depositos', rotulo: 'Depósitos' },
    { chave: 'valor_apostado', rotulo: 'Valor apostado' },
    { chave: 'resultado', rotulo: 'Resultado para a banca' },
    { chave: 'saques', rotulo: 'Saques pagos' },
];

// itens do menu de três pontinhos de cada cliente
const acoes_cliente = {
    ver: { icone: 'visibility', titulo: 'Ver detalhe', tom: '#1e88e5' },
    editar: { icone: 'edit', titulo: 'Editar', tom: '#1e88e5' },
    desativar: { icone: 'toggle_off', titulo: 'Desativar', tom: '#757575' },
    ativar: { icone: 'toggle_on', titulo: 'Ativar', tom: '#43a047' },
    ajustar: { icone: 'attach_money', titulo: 'Ajustar saldo', tom: '#43a047' },
    antecipar: { icone: 'arrow_circle_up', titulo: 'Antecipar pendentes', tom: '#fb8c00' },
    acessar: { icone: 'launch', titulo: 'Acessar conta', tom: '#1e88e5' },
    apostas: { icone: 'receipt_long', titulo: 'Ver apostas', tom: '#1e88e5' },
    excluir: { icone: 'delete', titulo: 'Excluir', tom: '#e53935' },
    restaurar: { icone: 'restore', titulo: 'Restaurar', tom: '#43a047' },
};

const acoes_saque = {
    baixa: { icone: 'check_circle', titulo: 'Dar baixa (manual)', tom: '#43a047' },
    mercado_pago: { icone: 'account_balance', titulo: 'Pagar pelo Mercado Pago', tom: '#1e88e5' },
    recusar: { icone: 'block', titulo: 'Recusar', tom: '#e53935' },
};

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

// primeiro dia do mês atual e hoje (AAAA-MM-DD), para o atalho "Ver apostas"
const este_mes = () => {
    const hoje = new Intl.DateTimeFormat('en-CA', { timeZone: 'America/Sao_Paulo', year: 'numeric', month: '2-digit', day: '2-digit' }).format(new Date());

    return { de: `${hoje.slice(0, 8)}01`, ate: hoje };
};

// tela "Clientes" do painel (spec 019; a antiga "Créditos"): abas Cadastros, Saques, Ranking e
// Lixeira, cada uma com a sua permissão; busca, filtros, detalhe do cliente em gaveta, ações sobre o
// cliente e o saque, planilha da aba e notificação push manual
export default function PanelClients() {
    const { nome_sistema, tema } = usePage().props;
    const { usuario: logado, carregando } = usePainel();
    const pode = (permissao) => Boolean(logado?.permissoes.includes(permissao));
    const abas_visiveis = abas.filter((aba) => pode(aba.permissao));
    const clientes = useClientesPainel({ habilitado: pode('clientes.listar') });
    const { filtros, mudar, trocar_aba, lista, resumo, recarregar, token } = clientes;
    const e_mobile = useTelaMobile();
    const [busca, definir_busca] = useState(filtros.busca);
    const [filtros_abertos, definir_filtros_abertos] = useState(false);
    const [detalhe, definir_detalhe] = useState(null);
    const [versao_detalhe, definir_versao_detalhe] = useState(0);
    const [edicao, definir_edicao] = useState(null);
    const [ajuste, definir_ajuste] = useState(null);
    const [antecipacao, definir_antecipacao] = useState(null);
    const [recusa, definir_recusa] = useState(null);
    const [conflito, definir_conflito] = useState(null);
    const [notificacao_aberta, definir_notificacao_aberta] = useState(false);
    const [exportando, definir_exportando] = useState(false);
    const espera = useRef(null);

    const autorizacao = { headers: { Authorization: `Bearer ${token}` } };
    const e_admin = logado?.funcao === 'Admin';
    const ve_apostas_de_clientes = ['Admin', 'Supervisor'].includes(logado?.funcao) && pode('apostas.listar');

    // sem acesso à lista, volta para a Dashboard; aba sem permissão vai para a primeira permitida
    useEffect(() => {
        if (!logado) return;

        if (!pode('clientes.listar')) {
            router.visit('/painel');
            return;
        }

        if (!abas_visiveis.some((aba) => aba.chave === filtros.aba)) trocar_aba(abas_visiveis[0].chave);
    }, [logado]);

    useEffect(() => () => window.clearTimeout(espera.current), []);

    if (carregando || !logado || !pode('clientes.listar')) {
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

    // depois de cada ação: aviso, lista, cartões e detalhe atualizados sem perder a página (FR-040)
    const concluir = (mensagem) => {
        if (mensagem) avisar(mensagem);

        recarregar();
        definir_versao_detalhe((atual) => atual + 1);
    };

    const executar = async (requisicao, mensagem) => {
        try {
            await requisicao();
            concluir(mensagem);
        } catch (erro) {
            alerta_erro(erro);
            recarregar();
        }
    };

    const alterar_situacao = async (cliente) => {
        const texto = cliente.ativo
            ? `Desativar ${cliente.nome}? As sessões do cliente deixam de valer na hora.`
            : `Ativar ${cliente.nome}? O cliente volta a entrar e apostar.`;

        if (!(await confirmar(texto, cliente.ativo ? '#c40808' : '#43a047'))) return;

        await executar(() => api.patch(`/clientes/${cliente.id}/situacao`, { ativo: !cliente.ativo }, autorizacao), cliente.ativo ? 'Cliente desativado.' : 'Cliente ativado.');
    };

    const acessar_conta = async (cliente) => {
        try {
            const { data } = await api.post(`/clientes/${cliente.id}/acesso-assistido`, {}, autorizacao);
            window.open(data.url, '_blank', 'noopener');
        } catch (erro) {
            alerta_erro(erro);
        }
    };

    const ver_apostas = (cliente) => {
        const { de, ate } = este_mes();
        router.visit(`/painel/apostas?${new URLSearchParams({ de, ate, clientes_id: cliente.id, cliente: cliente.nome })}`);
    };

    const excluir = async (cliente) => {
        if (!(await confirmar(`Excluir ${cliente.nome}? As sessões do cliente deixam de valer e ele vai para a Lixeira.`))) return;

        definir_detalhe(null);
        await executar(() => api.delete(`/clientes/${cliente.id}`, autorizacao), 'Cliente excluído.');
    };

    const restaurar = async (cliente) => {
        if (!(await confirmar(`Restaurar ${cliente.nome}?`, '#43a047'))) return;

        try {
            await api.post(`/clientes/${cliente.id}/restaurar`, {}, autorizacao);
            concluir('Cliente restaurado.');
        } catch (erro) {
            const campos = Object.keys(erro.response?.data?.errors ?? {});

            if (erro.response?.status === 422 && campos.length) definir_conflito({ cliente, campos });
            else alerta_erro(erro);
        }
    };

    // ações do cliente pela permissão (FR-010); as mesmas no menu da linha e no detalhe
    const acoes_do_cliente = (cliente, com_ver = true) => [
        com_ver && 'ver',
        pode('clientes.editar') && 'editar',
        pode('clientes.editar') && (cliente.ativo ? 'desativar' : 'ativar'),
        pode('clientes.movimentar_saldo') && 'ajustar',
        pode('clientes.antecipar_pendentes') && 'antecipar',
        pode('clientes.acessar_conta') && cliente.ativo && 'acessar',
        ve_apostas_de_clientes && 'apostas',
        pode('clientes.excluir') && 'excluir',
    ].filter(Boolean).map((chave) => ({
        chave,
        ...acoes_cliente[chave],
        ao_clicar: () => {
            if (chave === 'ver') definir_detalhe(cliente);
            if (chave === 'editar') definir_edicao(cliente);
            if (chave === 'desativar' || chave === 'ativar') alterar_situacao(cliente);
            if (chave === 'ajustar') definir_ajuste(cliente);
            if (chave === 'antecipar') definir_antecipacao(cliente);
            if (chave === 'acessar') acessar_conta(cliente);
            if (chave === 'apostas') ver_apostas(cliente);
            if (chave === 'excluir') excluir(cliente);
        },
    }));

    const acoes_da_lixeira = (cliente) => [{ chave: 'restaurar', ...acoes_cliente.restaurar, ao_clicar: () => restaurar(cliente) }];

    const pagar_saque = async (saque, forma) => {
        const texto = forma === 'manual'
            ? `Dar baixa no saque de ${saque.cliente?.nome ?? 'cliente'}? Use quando o Pix já foi feito por fora.`
            : `Pagar o saque de ${saque.cliente?.nome ?? 'cliente'} pelo Mercado Pago?`;

        if (!(await confirmar(texto, '#43a047'))) return;

        await executar(() => api.post(`/saques/${saque.id}/pagar`, { forma }, autorizacao), forma === 'manual' ? 'Baixa registrada.' : 'Saque enviado ao Mercado Pago.');
    };

    // só os saques pendentes têm ações (FR-026)
    const acoes_do_saque = (saque) => (saque.situacao !== 'Pendente' ? [] : ['baixa', 'mercado_pago', 'recusar'].map((chave) => ({
        chave,
        ...acoes_saque[chave],
        ao_clicar: () => {
            if (chave === 'baixa') pagar_saque(saque, 'manual');
            if (chave === 'mercado_pago') pagar_saque(saque, 'mercado_pago');
            if (chave === 'recusar') definir_recusa(saque);
        },
    })));

    const exportar = async () => {
        if (lista.total > LIMITE_PLANILHA) {
            alerta_atencao(`O filtro tem ${lista.total.toLocaleString('pt-BR')} registros; o máximo por planilha é ${LIMITE_PLANILHA.toLocaleString('pt-BR')}. Use filtros para reduzir.`);
            return;
        }

        definir_exportando(true);

        try {
            const resposta = await api.get('/clientes/exportacao', { params: { aba: filtros.aba, ...parametros_api(filtros) }, responseType: 'blob', ...autorizacao });
            const endereco = URL.createObjectURL(resposta.data);
            const link = document.createElement('a');

            link.href = endereco;
            link.download = nome_arquivo(resposta.headers['content-disposition'], `clientes-${filtros.aba}.xlsx`);
            link.click();
            URL.revokeObjectURL(endereco);
        } catch (erro) {
            alerta_erro(await ler_erro_blob(erro));
        } finally {
            definir_exportando(false);
        }
    };

    const de_clientes = filtros.aba === 'cadastros' || filtros.aba === 'lixeira';
    const etiquetas = de_clientes ? etiquetas_filtros(filtros) : [];
    const painel_filtros = de_clientes && filtros_abertos && !e_mobile;
    const titulo_aba = abas.find((aba) => aba.chave === filtros.aba)?.rotulo ?? 'Clientes';

    const filtros_tela = (
        <ClientsFilters
            aberto={filtros_abertos && de_clientes}
            filtros={filtros}
            token={token}
            ao_aplicar={(mudanca) => mudar(mudanca)}
            ao_fechar={() => definir_filtros_abertos(false)}
        />
    );

    // cartões da aba Saques (FR-024)
    const cartoes_saques = () => {
        const dados = resumo.dados;

        if (resumo.carregando && !dados) return <Skeleton>{Array.from({ length: 3 }, (_, indice) => <span key={indice} />)}</Skeleton>;

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
                <StatCard icone="hourglass_empty" rotulo={`Pendentes (${dados.pendentes.quantidade})`} valor={dados.pendentes.valor} titulo="Saques esperando a baixa." />
                <StatCard icone="sync" rotulo={`Processando (${dados.processando.quantidade})`} valor={dados.processando.valor} titulo="Saques enviados ao Mercado Pago, esperando o retorno." />
                {dados.pagos && <StatCard icone="check_circle" rotulo={`Pagos no período (${dados.pagos.quantidade})`} valor={dados.pagos.valor} titulo="Saques pagos no período, pela data do pagamento." />}
            </Cards>
        );
    };

    const conteudo = () => {
        if (filtros.aba === 'saques') {
            return (
                <>
                    {cartoes_saques()}
                    <WithdrawalsTable linhas={lista.linhas} carregando={lista.carregando} erro={lista.erro} ao_tentar_novamente={recarregar} acoes={acoes_do_saque} />
                </>
            );
        }

        if (filtros.aba === 'ranking') {
            return (
                <RankingTable
                    linhas={lista.linhas}
                    criterio={filtros.criterio}
                    carregando={lista.carregando}
                    erro={lista.erro}
                    ao_tentar_novamente={recarregar}
                    ao_abrir={(linha) => definir_detalhe({ id: linha.id, nome: linha.nome })}
                />
            );
        }

        return (
            <ClientsTable
                linhas={lista.linhas}
                lixeira={filtros.aba === 'lixeira'}
                carregando={lista.carregando}
                erro={lista.erro}
                ao_tentar_novamente={recarregar}
                ao_abrir={(cliente) => (filtros.aba === 'lixeira' ? restaurar(cliente) : definir_detalhe(cliente))}
                acoes={filtros.aba === 'lixeira' ? acoes_da_lixeira : acoes_do_cliente}
                ordenacao={filtros.aba === 'cadastros' ? { ordenar_por: filtros.ordenar_por, direcao: filtros.direcao } : null}
                ao_ordenar={filtros.aba === 'cadastros' ? mudar : null}
            />
        );
    };

    // linha de escolhas da aba: situação e período dos saques; período e critério do ranking
    const escolhas_da_aba = () => {
        if (filtros.aba === 'saques') {
            return (
                <>
                    <Chips role="group" aria-label="Situação do saque">
                        {situacoes_saque.map((situacao) => (
                            <Chip key={situacao.chave} type="button" aria-pressed={filtros.situacao === situacao.chave} onClick={() => mudar({ situacao: situacao.chave })}>{situacao.rotulo}</Chip>
                        ))}
                    </Chips>
                    <DateRow>
                        <DateField>
                            <span>Pedido de</span>
                            <DateInput type="date" value={filtros.saques_de ?? ''} onChange={(evento) => mudar({ saques_de: evento.target.value || null })} />
                        </DateField>
                        <DateField>
                            <span>até</span>
                            <DateInput type="date" value={filtros.saques_ate ?? ''} onChange={(evento) => mudar({ saques_ate: evento.target.value || null })} />
                        </DateField>
                        {(filtros.saques_de || filtros.saques_ate) && (
                            <Chip type="button" onClick={() => mudar({ saques_de: null, saques_ate: null })}>Todo o período</Chip>
                        )}
                    </DateRow>
                </>
            );
        }

        if (filtros.aba === 'ranking') {
            return (
                <>
                    <Chips role="group" aria-label="Período do ranking">
                        {periodos_ranking.map((periodo) => (
                            <Chip key={periodo.chave} type="button" aria-pressed={filtros.ranking_periodo === periodo.chave} onClick={() => mudar({ ranking_periodo: periodo.chave })}>{periodo.rotulo}</Chip>
                        ))}
                    </Chips>
                    {filtros.ranking_periodo === 'personalizado' && (
                        <DateRow>
                            <DateField>
                                <span>De</span>
                                <DateInput type="date" value={filtros.ranking_de ?? ''} onChange={(evento) => mudar({ ranking_de: evento.target.value || null })} />
                            </DateField>
                            <DateField>
                                <span>até</span>
                                <DateInput type="date" value={filtros.ranking_ate ?? ''} onChange={(evento) => mudar({ ranking_ate: evento.target.value || null })} />
                            </DateField>
                        </DateRow>
                    )}
                    <Chips role="group" aria-label="Critério do ranking">
                        {criterios_ranking.map((criterio) => (
                            <Chip key={criterio.chave} type="button" aria-pressed={filtros.criterio === criterio.chave} onClick={() => mudar({ criterio: criterio.chave })}>{criterio.rotulo}</Chip>
                        ))}
                    </Chips>
                </>
            );
        }

        return null;
    };

    return (
        <>
            <Head title={nome_sistema} />
            <PanelShell usuario={logado} titulo={`Clientes · ${titulo_aba} · total ${lista.total.toLocaleString('pt-BR')}`}>
                <Section>
                    <Top $com_filtros={painel_filtros} $com_etiquetas={etiquetas.length > 0}>
                        <Area $area="abas">
                            <FullTabs>
                                <PanelTabs
                                    abas={abas_visiveis}
                                    ativa={filtros.aba}
                                    ao_trocar={(aba) => {
                                        definir_busca('');
                                        trocar_aba(aba);
                                    }}
                                />
                            </FullTabs>
                        </Area>
                        <Area $area="ferramentas">
                            <HeaderActions>
                                {de_clientes && (
                                    <Tool type="button" aria-expanded={filtros_abertos} onClick={() => definir_filtros_abertos((aberto) => !aberto)}>
                                        <i className="material-icons" aria-hidden="true">filter_alt</i>
                                        {etiquetas.length ? `Filtros (${etiquetas.length})` : 'Filtros'}
                                    </Tool>
                                )}
                                {pode('clientes.exportar') && (
                                    <Tool type="button" disabled={exportando} onClick={exportar}>
                                        {exportando ? <Spinner tamanho={18} cor="currentColor" /> : <i className="material-icons" aria-hidden="true">file_download</i>}
                                        Baixar planilha
                                    </Tool>
                                )}
                                {pode('clientes.enviar_notificacao') && (
                                    <Tool type="button" onClick={() => definir_notificacao_aberta(true)}>
                                        <i className="material-icons" aria-hidden="true">notifications</i>
                                        Notificação
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
                                <ActiveFilters etiquetas={etiquetas} ao_remover={(chave) => mudar(remover_filtro(chave))} />
                            </Area>
                        )}
                        <Area $area="escolhas"><Choices>{escolhas_da_aba()}</Choices></Area>
                        {filtros.aba !== 'ranking' && (
                            <Area $area="busca">
                                <SearchBox>
                                    <i className="material-icons" aria-hidden="true">search</i>
                                    <Search
                                        type="search"
                                        value={busca}
                                        placeholder={filtros.aba === 'saques' ? 'Nome ou ID do cliente' : 'Nome, telefone, e-mail ou CPF'}
                                        aria-label="Buscar cliente"
                                        onChange={(evento) => buscar(evento.target.value)}
                                        onKeyDown={(evento) => {
                                            if (evento.key !== 'Enter') return;

                                            window.clearTimeout(espera.current);
                                            mudar({ busca });
                                        }}
                                    />
                                </SearchBox>
                            </Area>
                        )}
                    </Top>

                    {/* no celular os filtros abrem num modal, fora da grade */}
                    {e_mobile && filtros_tela}

                    {conteudo()}
                    <Pagination pagina={filtros.pagina} ultima={lista.ultima} ao_trocar={(pagina) => mudar({ pagina })} />
                </Section>
            </PanelShell>

            <ClientDrawer
                cliente={detalhe}
                versao={versao_detalhe}
                token={token}
                pode={pode}
                acoes={(cliente) => acoes_do_cliente(cliente, false)}
                ao_fechar={() => definir_detalhe(null)}
                ao_alterar={concluir}
            />
            <ClientFormModal cliente={edicao} token={token} e_admin={e_admin} ao_fechar={() => definir_edicao(null)} ao_salvar={(mensagem) => { definir_edicao(null); concluir(mensagem); }} />
            <BalanceAdjustModal cliente={ajuste} token={token} ao_fechar={() => definir_ajuste(null)} ao_concluir={(mensagem) => { definir_ajuste(null); concluir(mensagem); }} />
            <AnticipateModal cliente={antecipacao} token={token} ao_fechar={() => definir_antecipacao(null)} ao_concluir={(mensagem) => { definir_antecipacao(null); concluir(mensagem); }} />
            <WithdrawalDecisionModal saque={recusa} token={token} ao_fechar={() => definir_recusa(null)} ao_concluir={(mensagem) => { definir_recusa(null); concluir(mensagem); }} />
            <RestoreConflictModal conflito={conflito} token={token} ao_fechar={() => definir_conflito(null)} ao_concluir={(mensagem) => { definir_conflito(null); concluir(mensagem); }} />
            <PushNotificationModal aberto={notificacao_aberta} token={token} ao_fechar={() => definir_notificacao_aberta(false)} ao_concluir={(mensagem) => { definir_notificacao_aberta(false); concluir(mensagem); }} />
        </>
    );
}
