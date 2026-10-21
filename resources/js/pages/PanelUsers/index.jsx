import { useEffect, useRef, useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';

import Pagination from '../../components/Pagination';
import PanelShell from '../../components/PanelShell';
import PanelTabs from '../../components/PanelTabs';
import PermissionsModal from '../../components/PermissionsModal';
import Spinner from '../../components/Spinner';
import UserFormModal from '../../components/UserFormModal';
import UsersTable from '../../components/UsersTable';
import usePainel from '../../hooks/usePainel';
import useSessao from '../../hooks/useSessao';
import useUsuariosPainel from '../../hooks/useUsuariosPainel';
import api from '../../utils/api';
import { alerta_erro, alerta_sucesso, confirmar } from '../../utils/alerts';
import { Bar, Loading, NewButton, Search, SearchBox, SearchToggle, Section, Title } from './styles';

// quem vê cada lista (menu "Usuários" do antigo) e os nomes da tela
const listas = {
    Supervisor: { funcoes: ['Admin'], plural: 'supervisores', singular: 'Supervisor' },
    Gerente: { funcoes: ['Admin', 'Supervisor'], plural: 'gerentes', singular: 'Gerente' },
    Vendedor: { funcoes: ['Admin', 'Supervisor', 'Gerente'], plural: 'vendedores', singular: 'Vendedor' },
};

const niveis = { Admin: 1, Supervisor: 2, Gerente: 3, Vendedor: 4 };

const abas = [
    { chave: 'ativos', rotulo: 'Ativos', icone: 'receipt' },
    { chave: 'lixeira', rotulo: 'Lixeira', icone: 'delete_outline' },
];

// listas de usuários do painel (screens/adm/register do antigo): supervisores, gerentes ou
// vendedores da rede de quem vê, com busca, abas Ativos/Lixeira, cadastro, edição, permissões,
// situação, exclusão e restauração, cada ação só com a sua permissão (spec 015)
export default function PanelUsers() {
    const { nome_sistema, tema, funcao } = usePage().props;
    const lista = listas[funcao];
    const { sessao } = useSessao();
    const { usuario: logado, carregando } = usePainel();
    const [aba, definir_aba] = useState('ativos');
    const [formulario, definir_formulario] = useState({ aberto: false, usuario: null });
    const [permissoes_de, definir_permissoes_de] = useState(null);
    const listagem = useUsuariosPainel(funcao, aba);
    const [busca_aberta, definir_busca_aberta] = useState(false);
    const campo_busca = useRef(null);
    const cabecalhos = { Authorization: `Bearer ${sessao?.token}` };

    const pode = (permissao) => Boolean(logado?.permissoes.includes(permissao));
    const sufixo = funcao.toLowerCase();

    // sem a lista para a função ou sem permissão de listar, volta para a Dashboard
    useEffect(() => {
        if (logado && (!lista?.funcoes.includes(logado.funcao) || !pode('usuarios.listar'))) router.visit('/painel');
    }, [logado]);

    if (carregando || !lista?.funcoes.includes(logado.funcao)) {
        return (
            <>
                <Head title={nome_sistema} />
                <Loading><Spinner tamanho={40} cor={tema.temas} /></Loading>
            </>
        );
    }

    const fechar_formulario = () => definir_formulario({ aberto: false, usuario: null });

    const alternar_situacao = async (usuario) => {
        const abaixo = (usuario.quantidade_gerentes ?? 0) + (usuario.quantidade_vendedores ?? 0);
        const texto = usuario.ativo
            ? `Deseja desativar ${usuario.nome}?${abaixo > 0 ? ` Isso também vai desativar ${abaixo} usuários abaixo dele.` : ''}`
            : `Deseja ativar ${usuario.nome}?${abaixo > 0 ? ' Os usuários abaixo dele também serão ativados.' : ''}`;

        if (!(await confirmar(texto, tema.temas))) return;

        try {
            await api.patch(`/usuarios/${usuario.id}`, { ativo: !usuario.ativo }, { headers: cabecalhos });
            alerta_sucesso(usuario.ativo ? 'Usuário desativado com sucesso.' : 'Usuário ativado com sucesso.');
            listagem.recarregar();
        } catch (erro) {
            alerta_erro(erro);
        }
    };

    const excluir = async (usuario) => {
        if (!(await confirmar(`Tem certeza que deseja excluir o usuário ${usuario.nome.toUpperCase()}? Quem estiver abaixo dele também vai para a Lixeira.`, tema.temas))) return;

        try {
            await api.delete(`/usuarios/${usuario.id}`, { headers: cabecalhos });
            alerta_sucesso('Usuário removido com sucesso.');
            listagem.recarregar();
        } catch (erro) {
            alerta_erro(erro);
        }
    };

    const restaurar = async (usuario) => {
        if (!(await confirmar(`Tem certeza que deseja restaurar o usuário ${usuario.nome.toUpperCase()}?`, tema.temas))) return;

        try {
            await api.post(`/usuarios/${usuario.id}/restaurar`, {}, { headers: cabecalhos });
            alerta_sucesso('Usuário restaurado com sucesso.');
            listagem.recarregar();
        } catch (erro) {
            alerta_erro(erro);
        }
    };

    // no celular a busca é só a lupa; abre ao tocar e continua aberta enquanto houver texto
    const busca_expandida = busca_aberta || Boolean(listagem.busca);

    const lixeira = aba === 'lixeira';
    const acoes = lixeira
        ? { ao_restaurar: pode(`usuarios.excluir_${sufixo}`) ? restaurar : null }
        : {
            ao_editar: pode(`usuarios.editar_${sufixo}`) || pode('usuarios.consultar') ? (usuario) => definir_formulario({ aberto: true, usuario }) : null,
            ao_permissoes: pode('usuarios.gerenciar_permissoes') ? definir_permissoes_de : null,
            ao_alternar_situacao: pode('usuarios.alterar_situacao') ? alternar_situacao : null,
            ao_excluir: pode(`usuarios.excluir_${sufixo}`) ? excluir : null,
        };

    return (
        <>
            <Head title={nome_sistema} />
            <PanelShell usuario={logado} titulo={`Usuários · ${lista.plural}`}>
                <Section>
                    <Bar>
                        <Title $escondido={busca_expandida}>Lista de {lista.plural} – total {listagem.meta?.total ?? 0}</Title>
                        <SearchBox $expandida={busca_expandida}>
                            <SearchToggle
                                type="button"
                                title="Buscar"
                                aria-label="Buscar por nome ou login"
                                onClick={() => {
                                    definir_busca_aberta(true);
                                    campo_busca.current?.focus();
                                }}
                            >
                                <i className="material-icons" aria-hidden="true">search</i>
                            </SearchToggle>
                            <Search
                                ref={campo_busca}
                                type="search"
                                placeholder="Buscar por nome ou login"
                                value={listagem.busca}
                                onChange={(evento) => listagem.definir_busca(evento.target.value)}
                                onFocus={() => definir_busca_aberta(true)}
                                onBlur={() => definir_busca_aberta(false)}
                                aria-label="Buscar por nome ou login"
                            />
                        </SearchBox>
                        {pode(`usuarios.cadastrar_${sufixo}`) && (
                            <NewButton
                                type="button"
                                title={`Novo ${lista.singular}`}
                                aria-label={`Novo ${lista.singular}`}
                                onClick={() => definir_formulario({ aberto: true, usuario: null })}
                            >
                                <i className="material-icons" aria-hidden="true">person_add</i>
                                <span>Novo {lista.singular}</span>
                            </NewButton>
                        )}
                    </Bar>
                    <PanelTabs abas={abas} ativa={aba} ao_trocar={definir_aba} />
                    <UsersTable
                        funcao={funcao}
                        usuarios={listagem.usuarios}
                        carregando={listagem.carregando}
                        erro={listagem.erro}
                        ao_tentar_novamente={listagem.recarregar}
                        mostrar_superior={niveis[funcao] - niveis[logado.funcao] >= 2}
                        lixeira={lixeira}
                        acoes={acoes}
                    />
                    <Pagination pagina={listagem.pagina} ultima={listagem.meta?.last_page} ao_trocar={listagem.definir_pagina} />
                </Section>
            </PanelShell>
            <UserFormModal
                aberto={formulario.aberto}
                funcao={funcao}
                usuario={formulario.usuario}
                logado={logado}
                ao_fechar={fechar_formulario}
                ao_salvar={() => {
                    fechar_formulario();
                    listagem.recarregar();
                }}
            />
            <PermissionsModal usuario={permissoes_de} ao_fechar={() => definir_permissoes_de(null)} />
        </>
    );
}
