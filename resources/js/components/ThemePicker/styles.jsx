import styled from 'styled-components';
import { mobile } from '../../theme/tokens';

export const Group = styled.div`
    display: flex;
    flex-direction: column;
    gap: 10px;
`;

export const Label = styled.span`
    color: ${({ theme }) => theme.texto_secundario};
    font-size: 0.8em;
`;

export const Swatches = styled.div`
    display: flex;
    flex-wrap: wrap;
    gap: 14px;

    ${mobile} {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
`;

// amostra redonda de 44 px com o nome embaixo; a escolhida ganha o check e o anel
export const Swatch = styled.button`
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
    min-width: 64px;
    padding: 0;
    border: none;
    background: transparent;
    color: ${({ theme }) => theme.texto_principal};
    font: inherit;
    font-size: 0.8em;
    cursor: pointer;

    span {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 44px;
        height: 44px;
        border-radius: 50%;
        background-color: ${({ $cor }) => $cor};
        box-shadow: ${({ $marcada, theme }) => ($marcada ? `0 0 0 3px ${theme.fundo_pagina}, 0 0 0 5px ${theme.texto_principal}` : 'none')};
        color: #fff;
    }
`;

export const Backgrounds = styled.div`
    display: flex;
    gap: 12px;
`;

// cartão de amostra do fundo: um retângulo da cor com o nome ao lado
export const Background = styled.button`
    display: flex;
    align-items: center;
    gap: 10px;
    min-height: 48px;
    padding: 6px 16px 6px 6px;
    border: solid 2px ${({ $marcada, theme }) => ($marcada ? theme.principal : theme.superficie_titulo)};
    border-radius: 6px;
    background: transparent;
    color: ${({ theme }) => theme.texto_principal};
    font: inherit;
    font-size: 0.9em;
    cursor: pointer;

    span {
        width: 48px;
        height: 34px;
        border: solid 1px #888;
        border-radius: 4px;
        background-color: ${({ $fundo }) => $fundo};
    }
`;

// cor principal e fundo lado a lado quando há espaço; um embaixo do outro no celular
export const Layout = styled.div`
    display: flex;
    flex-wrap: wrap;
    align-items: flex-start;
    gap: 20px 48px;
`;
