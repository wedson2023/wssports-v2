import { useState } from 'react';
import { router, usePage } from '@inertiajs/react';

import Backdrop from '../Backdrop';
import useSessao from '../../hooks/useSessao';
import useVoltarFecha from '../../hooks/useVoltarFecha';
import { confirmar } from '../../utils/alerts';
import {
    Body, Buttons, Content, Drawer, DrawerHeader, GroupButton, Greeting, MenuIcon, MenuItem, Logo, Main, SubItem, TextButton, TitleBar, TitleText, Top,
} from './styles';

// permissões que dão acesso à tela "Cotações" (spec 016): qualquer uma das camadas
const permissoes_cotacoes = [
    'porcentagens_vendedores.editar',
    'porcentagens_clientes.editar',
    'porcentagens_campeonatos.editar',
    'porcentagens_confrontos.editar',
    'confrontos_teto_cotacoes.editar',
];

// permissões que dão acesso à tela "Caixa" (spec 017): a da rede ou a dos clientes
const permissoes_caixa = ['caixa.ver', 'caixa.ver_clientes'];

// itens do menu do painel: Dashboard (spec 014), o grupo Usuários (spec 015) com os subitens por
// função, como no menu "Usuários" do antigo, Confrontos (spec 023), Cotações (spec 016), Caixa (spec 017), Clientes
// (spec 019), Bônus (spec 020), Apostas (spec 018) e Configurações (spec 021); cada um diz quem pode
// vê-lo
const itens_menu = [
    { chave: 'dashboard', rotulo: 'Dashboard', icone: 'bar_chart', href: '/painel', visivel: (usuario) => usuario.secoes.length > 0 },
    {
        chave: 'usuarios',
        rotulo: 'Usuários',
        icone: 'people',
        visivel: (usuario) => usuario.permissoes.includes('usuarios.listar'),
        subitens: [
            { chave: 'supervisores', rotulo: 'Supervisores', href: '/painel/supervisores', funcoes: ['Admin'] },
            { chave: 'gerentes', rotulo: 'Gerentes', href: '/painel/gerentes', funcoes: ['Admin', 'Supervisor'] },
            { chave: 'vendedores', rotulo: 'Vendedores', href: '/painel/vendedores', funcoes: ['Admin', 'Supervisor', 'Gerente'] },
        ],
    },
    {
        chave: 'confrontos',
        rotulo: 'Confrontos',
        icone: 'sports_soccer',
        href: '/painel/confrontos',
        visivel: (usuario) => usuario.permissoes.includes('confrontos.listar'),
    },
    {
        chave: 'cotacoes',
        rotulo: 'Cotações',
        icone: 'price_change',
        href: '/painel/cotacoes',
        visivel: (usuario) => permissoes_cotacoes.some((permissao) => usuario.permissoes.includes(permissao)),
    },
    {
        chave: 'caixa',
        rotulo: 'Caixa',
        icone: 'point_of_sale',
        href: '/painel/caixa',
        visivel: (usuario) => permissoes_caixa.some((permissao) => usuario.permissoes.includes(permissao)),
    },
    {
        chave: 'clientes',
        rotulo: 'Clientes',
        icone: 'credit_card',
        href: '/painel/clientes',
        visivel: (usuario) => usuario.permissoes.includes('clientes.listar'),
    },
    {
        chave: 'bonus',
        rotulo: 'Bônus',
        icone: 'redeem',
        href: '/painel/bonus',
        visivel: (usuario) => usuario.permissoes.includes('clientes_promocoes.gerenciar'),
    },
    {
        chave: 'afiliados',
        rotulo: 'Afiliados',
        icone: 'handshake',
        href: '/painel/afiliados',
        visivel: (usuario) => usuario.permissoes.includes('afiliados.listar'),
    },
    {
        chave: 'apostas',
        rotulo: 'Apostas',
        icone: 'receipt',
        href: '/painel/apostas',
        visivel: (usuario) => usuario.permissoes.includes('apostas.listar'),
    },
    {
        chave: 'configuracoes',
        rotulo: 'Configurações',
        icone: 'settings',
        href: '/painel/configuracoes',
        visivel: (usuario) => usuario.permissoes.includes('configuracoes.editar'),
    },
];

// caminho atual sem a query string, para marcar o item ativo
const caminho_de = (url) => url.split('?')[0];

// estrutura do painel no visual de screens/adm/components/header do antigo: barra preta com logo,
// menu, APOSTAR e SAIR; menu lateral no desktop e gaveta no celular (spec 014, FR-001, FR-002, FR-005)
export default function PanelShell({ usuario, titulo, children }) {
    const { props: { tema }, url } = usePage();
    const { sair } = useSessao();
    const caminho = caminho_de(url);
    const [menu_aberto, definir_menu_aberto] = useState(false);
    const fechar_menu = () => definir_menu_aberto(false);
    useVoltarFecha(menu_aberto, fechar_menu);

    // itens que o usuário pode ver; grupos só com os subitens da função dele
    const itens = itens_menu
        .filter((item) => item.visivel(usuario))
        .map((item) => (item.subitens ? { ...item, subitens: item.subitens.filter((sub) => sub.funcoes.includes(usuario.funcao)) } : item))
        .filter((item) => !item.subitens || item.subitens.length);

    // grupo aberto: começa no que contém a página atual
    const [grupo_aberto, definir_grupo_aberto] = useState(
        () => itens.find((item) => item.subitens?.some((sub) => sub.href === caminho))?.chave ?? null,
    );

    const apostar = () => router.visit('/');

    const sair_do_painel = async () => {
        if (!(await confirmar('Deseja sair da sua conta e voltar para a tela de apostas?', tema.temas))) return;

        await sair();
        router.visit('/');
    };

    return (
        <Main>
            <Top>
                <Logo src={tema.logo} alt="" />
                <MenuIcon title="Menu" onClick={() => definir_menu_aberto(true)}>menu</MenuIcon>
                <Buttons>
                    <TextButton type="button" title="Clique para voltar para a tela de apostas!" onClick={apostar}>Apostar</TextButton>
                    <TextButton type="button" title="Clique para sair da sua conta" onClick={sair_do_painel}>Sair</TextButton>
                </Buttons>
            </Top>
            <Body>
                <Backdrop visivel={menu_aberto} ao_fechar={fechar_menu} camada={40} />
                <Drawer $aberto={menu_aberto}>
                    <DrawerHeader>
                        <span>Menu</span>
                        <MenuIcon title="Fechar" onClick={fechar_menu}>close</MenuIcon>
                    </DrawerHeader>
                    {itens.map((item) => (item.subitens ? (
                        <div key={item.chave}>
                            <GroupButton
                                type="button"
                                aria-expanded={grupo_aberto === item.chave}
                                $ativo={item.subitens.some((sub) => sub.href === caminho)}
                                onClick={() => definir_grupo_aberto((atual) => (atual === item.chave ? null : item.chave))}
                            >
                                <i className="material-icons" aria-hidden="true">{item.icone}</i>
                                <span>{item.rotulo}</span>
                                <i className="material-icons" aria-hidden="true">{grupo_aberto === item.chave ? 'expand_less' : 'expand_more'}</i>
                            </GroupButton>
                            {grupo_aberto === item.chave && item.subitens.map((sub) => (
                                <SubItem key={sub.chave} href={sub.href} $ativo={sub.href === caminho} onClick={fechar_menu}>
                                    {sub.rotulo}
                                </SubItem>
                            ))}
                        </div>
                    ) : (
                        <MenuItem key={item.chave} href={item.href} $ativo={item.href === caminho} onClick={fechar_menu}>
                            <i className="material-icons" aria-hidden="true">{item.icone}</i>
                            {item.rotulo}
                        </MenuItem>
                    )))}
                </Drawer>
                <Content>
                    <TitleBar>
                        <TitleText>{titulo}</TitleText>
                        <Greeting>Olá, {usuario.nome} · {usuario.funcao}</Greeting>
                    </TitleBar>
                    {children}
                </Content>
            </Body>
        </Main>
    );
}
