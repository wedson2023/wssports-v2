import { useEffect, useRef, useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import Swal from 'sweetalert2';

import ActiveFilters from '../../components/ActiveFilters';
import BonusDrawer from '../../components/BonusDrawer';
import BonusReversalModal from '../../components/BonusReversalModal';
import ClientDrawer from '../../components/ClientDrawer';
import Pagination from '../../components/Pagination';
import PanelShell from '../../components/PanelShell';
import PanelTabs from '../../components/PanelTabs';
import PromotionDrawer from '../../components/PromotionDrawer';
import PromotionFormModal from '../../components/PromotionFormModal';
import PromotionReversalModal from '../../components/PromotionReversalModal';
import PromotionsFilters, { etiquetas_promocoes } from '../../components/PromotionsFilters';
import PromotionsTable from '../../components/PromotionsTable';
import ReceivedBonusFilters, { etiquetas_recebidos, remover_filtro_recebidos } from '../../components/ReceivedBonusFilters';
import ReceivedBonusTable from '../../components/ReceivedBonusTable';
import Spinner from '../../components/Spinner';
import StatCard from '../../components/StatCard';
import useBonusPainel from '../../hooks/useBonusPainel';
import usePainel from '../../hooks/usePainel';
import useTelaMobile from '../../hooks/useTelaMobile';
import api from '../../utils/api';
import { alerta_erro, confirmar } from '../../utils/alerts';
import {
    Area, Cards, Chip, Chips, Choices, Error, HeaderActions, Loading, Retry, Search, SearchBox, Section, Skeleton, Tabs, Tool, Top,
} from './styles';

// espera depois da última tecla antes de buscar
const ATRASO_BUSCA = 400;

const abas = [
    { chave: 'promocoes', rotulo: 'Promoções', icone: 'card_giftcard' },
    { chave: 'recebidos', rotulo: 'Bônus recebidos', icone: 'redeem' },
];

const situacoes_bonus = [
    { chave: 'Em andamento', rotulo: 'Em andamento' },
    { chave: 'Cumprido', rotulo: 'Cumpridos' },
    { chave: 'Convertido', rotulo: 'Convertidos' },
    { chave: 'Expirado', rotulo: 'Expirados' },
    { chave: 'Excluído pelo cliente', rotulo: 'Excluídos pelo cliente' },
    { chave: 'Estornado', rotulo: 'Estornados' },
    { chave: 'Todos', rotulo: 'Todos' },
];

// itens do menu de três pontinhos de cada promoção e de cada bônus
const acoes_promocao = {
    ver: { icone: 'visibility', titulo: 'Ver detalhe', tom: '#1e88e5' },
    editar: { icone: 'edit', titulo: 'Editar', tom: '#1e88e5' },
    desativar: { icone: 'toggle_off', titulo: 'Desativar', tom: '#757575' },
    ativar: { icone: 'toggle_on', titulo: 'Ativar', tom: '#43a047' },
    recebidos: { icone: 'redeem', titulo: 'Ver bônus recebidos', tom: '#1e88e5' },
    estornar: { icone: 'undo', titulo: 'Estornar promoção', tom: '#e53935' },
    excluir: { icone: 'delete_outline', titulo: 'Excluir', tom: '#e53935' },
};

const acoes_bonus = {
    ver: { icone: 'visibility', titulo: 'Ver detalhe', tom: '#1e88e5' },
    cliente: { icone: 'person', titulo: 'Ver cliente', tom: '#1e88e5' },
    estornar: { icone: 'undo', titulo: 'Estornar bônus', tom: '#e53935' },
};

// o detalhe do cliente aberto daqui é só de consulta: sem as permissões de alterar
const permissoes_de_alterar = ['clientes.editar', 'clientes.editar_configuracoes'];

// aviso curto no canto, sem bloquear a tela
const avisar = (texto) => Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: texto, showConfirmButton: false, timer: 2500 });

// tela "Bônus" do painel (spec 020; a antiga "Lista de bônus"): abas Promoções (cadastro, edição,
// ativar/desativar, excluir e estorno geral) e Bônus recebidos (o bônus de cada cliente, com cartões,
// detalhe e estorno individual); busca, filtros e alcance pela rede
export default function PanelBonus() {
    const { nome_sistema, tema } = usePage().props;
    const { usuario: logado, carregando } = usePainel();
    const pode = (permissao) => Boolean(logado?.permissoes.includes(permissao));
    const bonus = useBonusPainel({ habilitado: pode('clientes_promocoes.gerenciar') });
    const { filtros, mudar, trocar_aba, lista, resumo, recarregar, token } = bonus;
    const e_mobile = useTelaMobile();
    const [busca, definir_busca] = useState(filtros.busca);
    const [filtros_abertos, definir_filtros_abertos] = useState(false);
    const [promocao_aberta, definir_promocao_aberta] = useState(null);
    const [bonus_aberto, definir_bonus_aberto] = useState(null);
    const [cliente_aberto, definir_cliente_aberto] = useState(null);
    const [versao_detalhe, definir_versao_detalhe] = useState(0);
    const [edicao, definir_edicao] = useState(null);
    const [estorno_promocao, definir_estorno_promocao] = useState(null);
    const [estorno_bonus, definir_estorno_bonus] = useState(null);
    const espera = useRef(null);

    const autorizacao = { headers: { Authorization: `Bearer ${token}` } };
    const e_admin = logado?.funcao === 'Admin';
    const recebidos = filtros.aba === 'recebidos';

    // sem acesso, volta para a Dashboard
    useEffect(() => {
        if (logado && !pode('clientes_promocoes.gerenciar')) router.visit('/painel');
    }, [logado]);

    useEffect(() => () => window.clearTimeout(espera.current), []);

    if (carregando || !logado || !pode('clientes_promocoes.gerenciar')) {
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

    // depois de cada ação: aviso, lista, cartões e detalhe atualizados sem perder a página (FR-034)
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

    const alterar_ativa = async (promocao) => {
        const texto = promocao.ativa
            ? `Desativar ${promocao.nome}? Ela deixa de ser dada a novos clientes; quem já recebeu continua com o bônus.`
            : `Ativar ${promocao.nome}? Ela volta a ser dada aos clientes dentro do período.`;

        if (!(await confirmar(texto, promocao.ativa ? '#c40808' : '#43a047'))) return;

        await executar(() => api.patch(`/clientes-promocoes/${promocao.id}`, { ativa: !promocao.ativa }, autorizacao), promocao.ativa ? 'Promoção desativada.' : 'Promoção ativada.');
    };

    const excluir = async (promocao) => {
        if (!(await confirmar(`Excluir ${promocao.nome}? Ninguém recebeu esta promoção ainda.`))) return;

        definir_promocao_aberta(null);
        await executar(() => api.delete(`/clientes-promocoes/${promocao.id}`, autorizacao), 'Promoção excluída.');
    };

    // abre a aba Bônus recebidos só com os bônus da promoção, em todas as situações
    const ver_recebidos = (promocao) => {
        definir_promocao_aberta(null);
        definir_busca('');
        trocar_aba('recebidos', { promocao: { id: promocao.id, nome: promocao.nome }, situacao_bonus: 'Todos' });
    };

    // ações da promoção pelo que o servidor permite (FR-010); as mesmas no menu e no detalhe
    const acoes_da_promocao = (promocao, com_ver = true) => [
        com_ver && 'ver',
        promocao.pode?.editar && 'editar',
        promocao.pode?.ativar && (promocao.ativa ? 'desativar' : 'ativar'),
        'recebidos',
        promocao.pode?.estornar && 'estornar',
        promocao.pode?.excluir && 'excluir',
    ].filter(Boolean).map((chave) => ({
        chave,
        ...acoes_promocao[chave],
        ao_clicar: () => {
            if (chave === 'ver') definir_promocao_aberta(promocao);
            if (chave === 'editar') definir_edicao(promocao);
            if (chave === 'desativar' || chave === 'ativar') alterar_ativa(promocao);
            if (chave === 'recebidos') ver_recebidos(promocao);
            if (chave === 'estornar') definir_estorno_promocao(promocao);
            if (chave === 'excluir') excluir(promocao);
        },
    }));

    const acoes_do_bonus = (item, com_ver = true) => [
        com_ver && 'ver',
        pode('clientes.listar') && 'cliente',
        item.pode?.estornar && 'estornar',
    ].filter(Boolean).map((chave) => ({
        chave,
        ...acoes_bonus[chave],
        ao_clicar: () => {
            if (chave === 'ver') definir_bonus_aberto(item);
            if (chave === 'cliente') definir_cliente_aberto({ id: item.cliente.id, nome: item.cliente.nome });
            if (chave === 'estornar') definir_estorno_bonus(item);
        },
    }));

    const etiquetas = recebidos ? etiquetas_recebidos(filtros) : etiquetas_promocoes(filtros);
    const remover = (chave) => mudar(recebidos ? remover_filtro_recebidos(chave) : { [chave]: null });
    const painel_filtros = filtros_abertos && !e_mobile;
    const titulo_aba = abas.find((aba) => aba.chave === filtros.aba)?.rotulo ?? 'Bônus';

    const filtros_tela = recebidos
        ? <ReceivedBonusFilters aberto={filtros_abertos} filtros={filtros} token={token} ao_aplicar={mudar} ao_fechar={() => definir_filtros_abertos(false)} />
        : <PromotionsFilters aberto={filtros_abertos} filtros={filtros} token={token} ao_aplicar={mudar} ao_fechar={() => definir_filtros_abertos(false)} />;

    // cartões da aba Bônus recebidos (FR-024)
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

        const periodo = filtros.data_inicial && filtros.data_final ? 'no período' : 'desde sempre';

        return (
            <Cards>
                <StatCard icone="hourglass_empty" rotulo={`Em andamento (${dados.em_andamento.quantidade})`} valor={dados.em_andamento.falta} titulo="Quanto falta apostar nos bônus em andamento." />
                <StatCard icone="redeem" rotulo={`Creditado ${periodo} (${dados.creditado.quantidade})`} valor={dados.creditado.valor} titulo="Bônus dados aos clientes." />
                <StatCard icone="currency_exchange" rotulo={`Convertido ${periodo} (${dados.convertido.quantidade})`} valor={dados.convertido.valor} titulo="O que passou do bônus para o saldo real." />
                <StatCard icone="remove_circle_outline" rotulo={`Retirado ${periodo} (${dados.retirado.quantidade})`} valor={dados.retirado.valor} titulo="Bônus expirados, excluídos e estornados." />
            </Cards>
        );
    };

    return (
        <>
            <Head title={nome_sistema} />
            <PanelShell usuario={logado} titulo={`Bônus · ${titulo_aba} · total ${lista.total.toLocaleString('pt-BR')}`}>
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
                                {/* nas duas abas, como o "Cadastrar" sempre visível da barra do antigo */}
                                <Tool type="button" onClick={() => definir_edicao({})}>
                                    <i className="material-icons" aria-hidden="true">add</i>
                                    Cadastrar
                                </Tool>
                                <Tool type="button" title="Atualizar" aria-label="Atualizar" $compacto onClick={recarregar}>
                                    <i className="material-icons" aria-hidden="true">refresh</i>
                                </Tool>
                            </HeaderActions>
                        </Area>
                        {painel_filtros && <Area $area="filtros">{filtros_tela}</Area>}
                        {etiquetas.length > 0 && (
                            <Area $area="etiquetas">
                                <ActiveFilters etiquetas={etiquetas} ao_remover={remover} />
                            </Area>
                        )}
                        <Area $area="escolhas">
                            <Choices>
                                {recebidos && (
                                    <Chips role="group" aria-label="Situação do bônus">
                                        {situacoes_bonus.map((situacao) => (
                                            <Chip key={situacao.chave} type="button" aria-pressed={filtros.situacao_bonus === situacao.chave} onClick={() => mudar({ situacao_bonus: situacao.chave })}>{situacao.rotulo}</Chip>
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
                                    placeholder={recebidos ? 'Nome ou ID do cliente' : 'Nome da promoção'}
                                    aria-label={recebidos ? 'Buscar cliente' : 'Buscar promoção'}
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

                    {recebidos && cartoes()}
                    {recebidos ? (
                        <ReceivedBonusTable
                            linhas={lista.linhas}
                            carregando={lista.carregando}
                            erro={lista.erro}
                            ao_tentar_novamente={recarregar}
                            ao_abrir={definir_bonus_aberto}
                            acoes={acoes_do_bonus}
                        />
                    ) : (
                        <PromotionsTable
                            linhas={lista.linhas}
                            carregando={lista.carregando}
                            erro={lista.erro}
                            ao_tentar_novamente={recarregar}
                            ao_abrir={definir_promocao_aberta}
                            acoes={acoes_da_promocao}
                        />
                    )}
                    <Pagination pagina={filtros.pagina} ultima={lista.ultima} ao_trocar={(pagina) => mudar({ pagina })} />
                </Section>
            </PanelShell>

            <PromotionDrawer
                promocao={promocao_aberta}
                versao={versao_detalhe}
                token={token}
                acoes={(promocao) => acoes_da_promocao(promocao, false)}
                ao_fechar={() => definir_promocao_aberta(null)}
            />
            <BonusDrawer
                bonus={bonus_aberto}
                versao={versao_detalhe}
                token={token}
                acoes={(item) => acoes_do_bonus(item, false)}
                ao_fechar={() => definir_bonus_aberto(null)}
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
            <PromotionFormModal promocao={edicao} token={token} e_admin={e_admin} ao_fechar={() => definir_edicao(null)} ao_salvar={(mensagem) => { definir_edicao(null); concluir(mensagem); }} />
            <PromotionReversalModal promocao={estorno_promocao} token={token} ao_fechar={() => definir_estorno_promocao(null)} ao_concluir={(mensagem) => { definir_estorno_promocao(null); concluir(mensagem); }} />
            <BonusReversalModal bonus={estorno_bonus} token={token} ao_fechar={() => definir_estorno_bonus(null)} ao_concluir={(mensagem) => { definir_estorno_bonus(null); concluir(mensagem); }} />
        </>
    );
}
