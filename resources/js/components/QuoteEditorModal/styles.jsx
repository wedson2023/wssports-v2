import styled from 'styled-components';
import { mobile } from '../../theme/tokens';

// moldura dos modais do painel com altura fixa desde a abertura (conteúdo longo e que carrega depois,
// constituição v3.6.0) e borda em degradê; 100% no celular
export const Container = styled.div`
    position: fixed;
    top: 50%;
    left: 50%;
    z-index: 46;
    display: flex;
    flex-direction: column;
    width: min(1100px, calc(100vw - 32px));
    height: min(760px, 92vh);
    height: min(760px, 92dvh);
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

export const Top = styled.div`
    flex-shrink: 0;
    display: flex;
    align-items: flex-end;
    flex-wrap: wrap;
    gap: 10px;
    padding: 14px 16px;
    border-bottom: solid 1px ${({ theme }) => theme.superficie_titulo};
`;

export const Note = styled.span`
    color: ${({ theme }) => theme.texto_apagado};
    font-size: 0.8em;
`;

// só o corpo rola
export const Body = styled.div`
    flex: 1;
    min-height: 0;
    padding: 16px;
    overflow-y: auto;

    ${mobile} {
        padding: 12px 12px 72px;
    }
`;

// grade à esquerda e prévia à direita (320 px); no celular a prévia vira faixa fixa embaixo
export const Columns = styled.div`
    display: grid;
    grid-template-columns: minmax(0, 1fr) 320px;
    align-items: start;
    gap: 16px;

    > div {
        display: flex;
        flex-direction: column;
        gap: 12px;
        min-width: 0;
    }

    ${mobile} {
        grid-template-columns: minmax(0, 1fr);
    }
`;

export const Loading = styled.div`
    display: flex;
    justify-content: center;
    padding: 40px 0;
    color: ${({ theme }) => theme.texto_apagado};
`;
