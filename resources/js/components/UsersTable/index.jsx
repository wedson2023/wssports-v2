import ActionsMenu from '../ActionsMenu';
import { formatar_data_hora } from '../../utils/dates';
import {
    Action, Actions, Cell, Dot, Empty, HeadCell, Login, MenuCell, Name, Retry, Row, Scroll, Skeleton, Status, StatusText, Table,
} from './styles';

// "10.00" → "10"; "7.50" → "7.5" (como a coluna Comissão do antigo)
const percentual = (valor) => String(Number(valor ?? 0));

// data da coluna; sem data (ex.: usuário sem exclusão) mostra traço em vez de quebrar a tela
const data_hora = (data) => (data ? formatar_data_hora(data) : '—');

// colunas variáveis por função listada (FR-006); as fixas vêm em seguida
const colunas_por_funcao = (funcao, mostrar_superior) => ({
    comissao: funcao === 'Vendedor',
    superior: mostrar_superior,
    vendedores: funcao === 'Gerente' || funcao === 'Supervisor',
    gerentes: funcao === 'Supervisor',
});

// rótulo do superior de cada função
const rotulo_superior = { Vendedor: 'Gerente', Gerente: 'Supervisor', Supervisor: 'Admin' };

// ações da linha: só aparecem as que vierem (cada uma já filtrada pela permissão na página); cada
// uma com sua cor, para diferenciar à primeira vista
const acoes_da_linha = (usuario, acoes) => [
    acoes.ao_editar && { chave: 'editar', icone: 'edit', titulo: 'Editar', tom: '#1e88e5', ao_clicar: () => acoes.ao_editar(usuario) },
    acoes.ao_permissoes && {
        chave: 'permissoes', icone: 'vpn_key', titulo: 'Permissões', tom: '#f9a825', ao_clicar: () => acoes.ao_permissoes(usuario),
    },
    acoes.ao_alternar_situacao && {
        chave: 'situacao',
        icone: usuario.ativo ? 'toggle_on' : 'toggle_off',
        titulo: usuario.ativo ? 'Desativar' : 'Ativar',
        tom: usuario.ativo ? '#43a047' : '#9e9e9e',
        ao_clicar: () => acoes.ao_alternar_situacao(usuario),
    },
    acoes.ao_excluir && { chave: 'excluir', icone: 'delete', titulo: 'Excluir', tom: '#e53935', ao_clicar: () => acoes.ao_excluir(usuario) },
    acoes.ao_restaurar && {
        chave: 'restaurar', icone: 'restore_from_trash', titulo: 'Restaurar', tom: '#43a047', ao_clicar: () => acoes.ao_restaurar(usuario),
    },
].filter(Boolean);

// lista de usuários do painel (Table de screens/adm/register do antigo). No celular ficam só as
// colunas principais (nome com o login embaixo, situação e o menu de ações nos três pontinhos), com
// os textos longos cortados para caber
export default function UsersTable({ funcao, usuarios, carregando, erro, ao_tentar_novamente, mostrar_superior = false, lixeira = false, acoes = {} }) {
    const colunas = colunas_por_funcao(funcao, mostrar_superior);

    if (carregando && !usuarios.length) {
        return <Skeleton>{Array.from({ length: 5 }, (_, indice) => <span key={indice} />)}</Skeleton>;
    }

    if (erro && !usuarios.length) {
        return (
            <Empty>
                {erro.response?.data?.message ?? 'Não foi possível carregar a lista.'}
                <Retry type="button" onClick={ao_tentar_novamente}>Tentar novamente</Retry>
            </Empty>
        );
    }

    if (!usuarios.length) {
        return <Empty>{lixeira ? 'A lixeira está vazia.' : 'Nenhum usuário encontrado.'}</Empty>;
    }

    const comissao = (usuario) => (usuario.comissoes ? usuario.comissoes.map(percentual).join('/') : '—');

    return (
        <Scroll $atualizando={carregando}>
            <Table>
                <thead>
                    <Row>
                        <HeadCell $secundaria>ID</HeadCell>
                        <HeadCell>Nome</HeadCell>
                        <HeadCell $secundaria>Login</HeadCell>
                        {colunas.comissao && <HeadCell $secundaria>Comissão</HeadCell>}
                        {colunas.superior && <HeadCell $secundaria>{rotulo_superior[funcao]}</HeadCell>}
                        {colunas.gerentes && <HeadCell $secundaria>Gerentes</HeadCell>}
                        {colunas.vendedores && <HeadCell $secundaria>Vendedores</HeadCell>}
                        <HeadCell $secundaria>Telefone</HeadCell>
                        <HeadCell $estreita>Ativo</HeadCell>
                        <HeadCell $secundaria>{lixeira ? 'Excluído em' : 'Data criação'}</HeadCell>
                        <HeadCell $secundaria>Alterado em</HeadCell>
                        <HeadCell $estreita>Ação</HeadCell>
                    </Row>
                </thead>
                <tbody>
                    {usuarios.map((usuario) => {
                        const acoes_usuario = acoes_da_linha(usuario, acoes);

                        return (
                            <Row key={usuario.id}>
                                <Cell $secundaria>{usuario.id}</Cell>
                                <Cell>
                                    <Name title={usuario.nome}>{usuario.nome}</Name>
                                    <Login title={usuario.login}>{usuario.login}</Login>
                                </Cell>
                                <Cell $secundaria>{usuario.login}</Cell>
                                {colunas.comissao && <Cell $secundaria>{comissao(usuario)}</Cell>}
                                {colunas.superior && <Cell $secundaria>{usuario.superior?.nome ?? '—'}</Cell>}
                                {colunas.gerentes && <Cell $secundaria>{usuario.quantidade_gerentes ?? 0}</Cell>}
                                {colunas.vendedores && <Cell $secundaria>{usuario.quantidade_vendedores ?? 0}</Cell>}
                                <Cell $secundaria>{usuario.telefone || '—'}</Cell>
                                <Cell>
                                    <Status title={usuario.ativo ? 'Ativo' : 'Inativo'}>
                                        <Dot $ativo={usuario.ativo} />
                                        <StatusText>{usuario.ativo ? 'Sim' : 'Não'}</StatusText>
                                    </Status>
                                </Cell>
                                <Cell $secundaria>{data_hora(lixeira ? usuario.deleted_at : usuario.created_at)}</Cell>
                                <Cell $secundaria>{data_hora(usuario.updated_at)}</Cell>
                                <Cell>
                                    <Actions>
                                        {acoes_usuario.map((acao) => (
                                            <Action key={acao.chave} type="button" title={acao.titulo} aria-label={acao.titulo} onClick={acao.ao_clicar} $tom={acao.tom}>
                                                <i className="material-icons" aria-hidden="true">{acao.icone}</i>
                                            </Action>
                                        ))}
                                    </Actions>
                                    <MenuCell>
                                        <ActionsMenu acoes={acoes_usuario} rotulo={`Ações de ${usuario.nome}`} />
                                    </MenuCell>
                                </Cell>
                            </Row>
                        );
                    })}
                </tbody>
            </Table>
        </Scroll>
    );
}
