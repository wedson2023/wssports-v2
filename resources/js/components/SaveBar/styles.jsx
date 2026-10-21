import styled from 'styled-components';
import { mobile } from '../../theme/tokens';

// barra fixa no rodapé da área do editor
export const Bar = styled.div`
    position: sticky;
    bottom: 0;
    z-index: 2;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 16px;
    border-top: solid 1px ${({ theme }) => theme.principal};
    background-color: ${({ theme }) => theme.fundo_pagina};
    box-shadow: 0 -6px 16px rgba(0, 0, 0, 0.2);

    ${mobile} {
        padding: 10px 12px;
    }
`;

export const Info = styled.span`
    display: flex;
    flex: 1;
    align-items: center;
    gap: 6px;
    min-width: 0;
    color: ${({ theme }) => theme.texto_principal};
    font-size: 0.88em;
    font-weight: 500;

    i {
        color: ${({ theme }) => theme.principal};
    }
`;

export const Undo = styled.button`
    min-height: 40px;
    padding: 0 14px;
    border: solid 1px ${({ theme }) => (theme.modo === 'escuro' ? '#3a3a3a' : '#a8a8a8')};
    border-radius: 6px;
    background: transparent;
    color: ${({ theme }) => theme.texto_principal};
    font: inherit;
    font-size: 0.85em;
    cursor: pointer;
`;

export const Save = styled.button`
    display: flex;
    align-items: center;
    justify-content: center;
    min-width: 110px;
    min-height: 40px;
    padding: 0 18px;
    border: none;
    border-radius: 6px;
    background-color: ${({ theme }) => theme.principal};
    color: #fff;
    font: inherit;
    font-weight: 500;
    text-transform: uppercase;
    cursor: pointer;

    &:disabled {
        opacity: 0.7;
        cursor: default;
    }
`;
