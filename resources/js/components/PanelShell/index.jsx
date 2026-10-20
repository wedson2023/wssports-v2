import { useState } from 'react';
import { router, usePage } from '@inertiajs/react';

import Backdrop from '../Backdrop';
import useSessao from '../../hooks/useSessao';
import useVoltarFecha from '../../hooks/useVoltarFecha';
import { confirmar } from '../../utils/alerts';
import {
    Body, Buttons, Content, Drawer, DrawerHeader, Greeting, MenuIcon, MenuItem, Logo, Main, TextButton, TitleBar, TitleText, Top,
} from './styles';

// itens do menu do painel; nesta spec só a Dashboard (os módulos entram em specs próprias)
const itens_menu = [{ chave: 'dashboard', rotulo: 'Dashboard', icone: 'bar_chart', href: '/painel' }];

// estrutura do painel no visual de screens/adm/components/header do antigo: barra preta com logo,
// menu, APOSTAR e SAIR; menu lateral no desktop e gaveta no celular (spec 014, FR-001, FR-002, FR-005)
export default function PanelShell({ usuario, titulo, children }) {
    const { tema } = usePage().props;
    const { sair } = useSessao();
    const [menu_aberto, definir_menu_aberto] = useState(false);
    const fechar_menu = () => definir_menu_aberto(false);
    useVoltarFecha(menu_aberto, fechar_menu);

    // o menu só aparece com alguma seção liberada
    const itens = usuario.secoes.length ? itens_menu : [];

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
                    {itens.map((item) => (
                        <MenuItem key={item.chave} href={item.href} $ativo={item.href === '/painel'} onClick={fechar_menu}>
                            <i className="material-icons" aria-hidden="true">{item.icone}</i>
                            {item.rotulo}
                        </MenuItem>
                    ))}
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
