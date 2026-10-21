import styled from 'styled-components';
import { mobile } from '../../theme/tokens';

const borda = ({ theme }) => (theme.modo === 'escuro' ? '#3a3a3a' : '#e2e2e2');

// moldura dos modais do painel, altura fixa desde a abertura (lista que chega depois)
export const Container = styled.div`
    position: fixed;
    top: 50%;
    left: 50%;
    z-index: 48;
    display: flex;
    flex-direction: column;
    width: min(620px, calc(100vw - 32px));
    height: min(640px, 85vh);
    height: min(640px, 85dvh);
    border: solid 1px transparent;
    border-radius: 8px;
    overflow: hidden;
    background:
        linear-gradient(${({ theme }) => theme.fundo_pagina}, ${({ theme }) => theme.fundo_pagina}) padding-box,
        linear-gradient(160deg, ${({ theme }) => (theme.modo === 'escuro' ? '#ffffffb3, #5a5a5a' : '#8a8a8a, #e6e6e6')}) border-box;
    color: ${({ theme }) => theme.texto_principal};
    box-shadow: 0 12px 40px rgba(0, 0, 0, 0.4);
    transform: translate(-50%, ${({ $visivel }) => ($visivel ? '-50%' : '-45%')});
    transition-duration: 0.25s;
    opacity: ${({ $visivel }) => ($visivel ? 1 : 0)};
    visibility: ${({ $visivel }) => ($visivel ? 'visible' : 'hidden')};

    ${mobile} {
        width: 100%;
        height: 100vh;
        height: 100dvh;
        border: none;
        border-radius: 0;
    }
`;

export const Body = styled.div`
    display: flex;
    flex: 1;
    flex-direction: column;
    gap: 10px;
    min-height: 0;
    padding: 16px;
    overflow-y: auto;

    > * {
        flex-shrink: 0;
    }
`;

export const Empty = styled.div`
    display: flex;
    justify-content: center;
    padding: 30px 10px;
    color: ${({ theme }) => theme.texto_apagado};
    text-align: center;
`;

export const Entry = styled.div`
    border: solid 1px ${borda};
    border-radius: 8px;
    overflow: hidden;
`;

export const EntryHeader = styled.button`
    display: flex;
    align-items: center;
    gap: 10px;
    width: 100%;
    min-height: 52px;
    padding: 10px 12px;
    border: none;
    background: transparent;
    color: ${({ theme }) => theme.texto_principal};
    font: inherit;
    font-size: 0.88em;
    text-align: left;
    cursor: pointer;

    > i:first-child {
        color: ${({ theme }) => theme.principal};
    }

    > span {
        display: flex;
        flex: 1;
        flex-direction: column;
        gap: 2px;
        min-width: 0;
    }
`;

export const Meta = styled.small`
    color: ${({ theme }) => theme.texto_apagado};
    font-size: 0.85em;
`;

export const Changes = styled.div`
    display: flex;
    flex-direction: column;
    border-top: solid 1px ${borda};
`;

export const Change = styled.div`
    display: flex;
    justify-content: space-between;
    gap: 10px;
    padding: 8px 12px;
    border-bottom: solid 1px ${borda};
    font-size: 0.82em;

    span {
        color: ${({ theme }) => theme.texto_secundario};
    }

    strong {
        font-weight: 500;
        white-space: nowrap;
    }
`;
