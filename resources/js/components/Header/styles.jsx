import styled from 'styled-components';
import { mobile } from '../../theme/tokens';

// Nav de main/styles.js (visitante: logo, Criar Conta e Entrar)
export const Nav = styled.div`
    padding: 5px 7px;
    background-color: ${({ theme }) => theme.fundo_pagina};
    display: grid;
    grid-template-columns: auto ${({ $colunas }) => $colunas.desktop};
    column-gap: 10px;
    align-items: center;

    ${mobile} {
        grid-template-columns: auto ${({ $colunas }) => $colunas.mobile};
    }
`;

export const Logo = styled.img`
    width: 50px;
    display: block;

    ${mobile} {
        display: none;
    }
`;

export const MenuIcon = styled.i.attrs(() => ({ className: 'material-icons' }))`
    color: ${({ theme }) => theme.principal};
    font-size: 40px;
    cursor: pointer;
    user-select: none;
    outline: none;
    display: none;

    &:hover {
        opacity: 0.8;
    }

    ${mobile} {
        display: block;
        width: 48px;
    }
`;

const Button = styled.a`
    padding: 7px;
    outline: none;
    border-radius: 0.3em;
    border: solid thin ${({ theme }) => theme.principal};
    color: #fff;
    user-select: none;
    text-decoration: none;
    font-size: 13px;
    text-align: center;
    transition-duration: 0.3s;
    cursor: pointer;
`;

// BtnRegister: preenchido na cor do tema; aparece também no mobile (pedido do responsável)
export const RegisterButton = styled(Button)`
    background-color: ${({ theme }) => theme.principal};

    &:hover {
        background-color: transparent;
        color: ${({ theme }) => theme.texto_principal};
    }
`;

// saudação de quem entrou, no lugar de "Criar Conta"; o cliente vê o saldo embaixo
export const Greeting = styled.span`
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    color: ${({ theme }) => theme.texto_principal};
    font-size: 13px;
    text-align: right;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
`;

export const Balance = styled.strong`
    color: #28b351;
    font-size: 12px;
    font-weight: 500;
`;

// BtnEnter: contornado na cor do tema
export const EnterButton = styled(Button)`
    background-color: transparent;
    color: ${({ theme }) => theme.texto_principal};

    &:hover {
        background-color: ${({ theme }) => theme.principal};
        color: #fff;
    }
`;
