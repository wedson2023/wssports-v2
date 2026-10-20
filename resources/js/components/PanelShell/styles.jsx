import { Link } from '@inertiajs/react';
import styled from 'styled-components';
import { mobile } from '../../theme/tokens';

// o estilo global trava html/body na altura da tela (overflow: hidden, como no antigo); o painel
// ocupa essa altura e a rolagem fica no conteúdo, com a barra superior sempre visível
export const Main = styled.div`
    display: flex;
    flex-direction: column;
    height: 100%;
    background-color: ${({ theme }) => theme.fundo_pagina};
    color: ${({ theme }) => theme.texto_principal};
`;

// Top de screens/adm/components/header/styles.js: barra preta com logo, menu e botões
export const Top = styled.header`
    flex-shrink: 0;
    z-index: 30;
    display: flex;
    justify-content: space-between;
    align-items: center;
    width: 100%;
    padding: 5px 7px;
    background: #000;
    user-select: none;
`;

export const Logo = styled.img`
    width: 50px;
    height: 50px;
    object-fit: contain;

    ${mobile} {
        display: none;
    }
`;

export const MenuIcon = styled.i.attrs(() => ({ className: 'material-icons', role: 'button' }))`
    display: none;
    color: ${({ theme }) => theme.principal};
    font-size: 35px;
    padding: 5px;
    cursor: pointer;

    &:hover {
        opacity: 0.8;
    }

    ${mobile} {
        display: block;
    }
`;

export const Buttons = styled.div`
    display: flex;
    align-items: center;
`;

// Back e Logout do antigo (APOSTAR e SAIR)
export const TextButton = styled.button`
    width: 100px;
    height: 30px;
    margin-left: 10px;
    padding: 0 13px;
    border: solid 2px ${({ theme }) => theme.principal};
    background: transparent;
    color: ${({ theme }) => theme.principal};
    font-size: 0.8em;
    font-weight: bold;
    text-transform: uppercase;
    cursor: pointer;
    transition-duration: 0.3s;

    &:hover {
        opacity: 0.7;
    }

    ${mobile} {
        width: auto;
    }
`;

export const Body = styled.div`
    display: flex;
    flex: 1;
    min-width: 0;
    min-height: 0;
`;

// menu lateral no desktop; gaveta de 70% no celular, como o Nav do antigo
export const Drawer = styled.nav`
    flex-shrink: 0;
    width: 200px;
    overflow-y: auto;
    background-color: ${({ theme }) => theme.superficie_pais};

    ${mobile} {
        position: fixed;
        top: 0;
        left: ${({ $aberto }) => ($aberto ? '0' : '-70%')};
        z-index: 50;
        width: 70%;
        height: 100vh;
        overflow-y: auto;
        transition-duration: 0.3s;
    }
`;

// NavHeader do antigo (só no celular)
export const DrawerHeader = styled.div`
    display: none;

    ${mobile} {
        display: flex;
        justify-content: space-between;
        align-items: center;
        height: 45px;
        padding: 0 10px;
        background: #222;
        color: #fff;
    }
`;

export const MenuItem = styled(Link)`
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 14px 16px;
    border-left: solid 4px ${({ theme, $ativo }) => ($ativo ? theme.principal : 'transparent')};
    color: ${({ theme }) => theme.texto_principal};
    font-size: 0.9em;
    font-weight: 500;
    text-decoration: none;

    i {
        color: ${({ theme }) => theme.principal};
    }

    &:hover {
        background-color: ${({ theme }) => theme.superficie_titulo};
    }
`;

// área que rola (as seções da Dashboard)
export const Content = styled.main`
    flex: 1;
    min-width: 0;
    overflow-y: auto;
    overflow-x: hidden;
    padding: 0 0 20px;
    -webkit-overflow-scrolling: touch;
`;

// barra de título (components/bar do antigo)
export const TitleBar = styled.div`
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 6px;
    padding: 12px 16px;
    background-color: ${({ theme }) => theme.superficie_titulo};
`;

export const TitleText = styled.h1`
    margin: 0;
    font-size: 1.1em;
    font-weight: 500;
`;

export const Greeting = styled.span`
    color: ${({ theme }) => theme.texto_secundario};
    font-size: 0.85em;
`;
