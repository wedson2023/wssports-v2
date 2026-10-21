import styled from 'styled-components';
import { mobile } from '../../theme/tokens';

// borda dos campos: no tema claro um cinza mais forte, para os campos aparecerem no fundo branco
const borda_campo = ({ theme }) => (theme.modo === 'escuro' ? '#3a3a3a' : '#a8a8a8');

export const Container = styled.div`
    display: flex;
    flex-direction: column;
    gap: 8px;
    min-width: 0;
`;

// atalhos em balões do mesmo tamanho, numa linha só; no celular, rolável na horizontal sem rolar a página
export const Shortcuts = styled.div`
    display: flex;
    gap: 6px;

    ${mobile} {
        overflow-x: auto;
        scrollbar-width: none;

        &::-webkit-scrollbar {
            display: none;
        }
    }
`;

export const Shortcut = styled.button`
    flex: 0 0 104px;
    min-height: 40px;
    padding: 0 8px;
    border: solid 1px ${borda_campo};
    border-radius: 20px;
    background-color: transparent;
    color: ${({ theme }) => theme.texto_secundario};
    font: inherit;
    font-size: 0.85em;
    font-weight: 500;
    cursor: pointer;
    transition: background-color 0.15s, color 0.15s;

    &[aria-pressed='true'] {
        border-color: ${({ theme }) => theme.principal};
        background-color: ${({ theme }) => theme.principal};
        color: #fff;
    }

    &:focus-visible {
        outline: solid 2px ${({ theme }) => theme.principal};
        outline-offset: 2px;
    }
`;

export const Custom = styled.div`
    display: flex;
    align-items: flex-end;
    flex-wrap: wrap;
    gap: 10px;

    ${mobile} {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
`;

export const Field = styled.div`
    display: flex;
    flex-direction: column;
    gap: 6px;
    min-width: 0;
`;

export const Label = styled.label`
    color: ${({ theme }) => theme.texto_secundario};
    font-size: 0.8em;
`;

export const Input = styled.input`
    min-height: 40px;
    padding: 0 10px;
    border: solid 1px ${({ theme, $erro }) => ($erro ? '#e53935' : borda_campo({ theme }))};
    border-radius: 4px;
    background-color: ${({ theme }) => theme.fundo_pagina};
    color: ${({ theme }) => theme.texto_principal};
    font: inherit;
    color-scheme: ${({ theme }) => (theme.modo === 'escuro' ? 'dark' : 'light')};

    &:focus {
        outline: none;
        border-color: ${({ theme }) => theme.principal};
    }
`;

export const Apply = styled.button`
    min-height: 40px;
    padding: 0 18px;
    border: none;
    border-radius: 4px;
    background-color: ${({ theme }) => theme.principal};
    color: #fff;
    font: inherit;
    font-weight: 500;
    cursor: pointer;

    ${mobile} {
        grid-column: 1 / -1;
    }
`;

export const Error = styled.span`
    width: 100%;
    color: #e53935;
    font-size: 0.75em;

    ${mobile} {
        grid-column: 1 / -1;
    }
`;

export const Period = styled.span`
    display: inline-flex;
    align-items: center;
    gap: 6px;
    color: ${({ theme }) => theme.texto_secundario};
    font-size: 0.8em;

    i {
        font-size: 16px;
    }
`;
