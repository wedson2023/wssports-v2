import styled from 'styled-components';
import { mobile } from '../../theme/tokens';

export { Money } from '../ClientsTable/styles';

// gaveta à direita, abaixo dos modais de ação (que abrem por cima dela); tela cheia no celular
export const Container = styled.div`
    position: fixed;
    top: 0;
    right: 0;
    z-index: 46;
    display: flex;
    flex-direction: column;
    width: min(560px, 100vw);
    height: 100vh;
    height: 100dvh;
    background-color: ${({ theme }) => theme.fundo_pagina};
    color: ${({ theme }) => theme.texto_principal};
    box-shadow: -8px 0 32px rgba(0, 0, 0, 0.35);
    transform: translateX(${({ $visivel }) => ($visivel ? '0' : '100%')});
    transition: transform 0.25s;
    visibility: ${({ $visivel }) => ($visivel ? 'visible' : 'hidden')};

    > header,
    > [role='tablist'] {
        flex-shrink: 0;
    }

    ${mobile} {
        width: 100%;
    }
`;

export const Body = styled.div`
    display: flex;
    flex: 1;
    flex-direction: column;
    gap: 14px;
    min-height: 0;
    padding: 16px;
    overflow-y: auto;

    > * {
        flex-shrink: 0;
    }
`;

export const Loading = styled.div`
    display: flex;
    justify-content: center;
    padding: 24px 0;
    color: ${({ theme }) => theme.principal};
`;

export const Data = styled.dl`
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px 16px;
    margin: 0;
`;

export const DataItem = styled.div`
    display: flex;
    flex-direction: column;
    gap: 2px;
    min-width: 0;

    > span:first-child {
        color: ${({ theme }) => theme.texto_secundario};
        font-size: 0.8em;
    }

    > span:last-child {
        overflow-wrap: anywhere;
    }
`;

export const Actions = styled.div`
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 8px;
`;

// atalho de ação do detalhe, com o ícone na cor da ação
export const Button = styled.button`
    display: flex;
    align-items: center;
    gap: 8px;
    min-height: 44px;
    padding: 0 12px;
    border: solid 1px ${({ theme }) => theme.superficie_titulo};
    border-radius: 6px;
    background: transparent;
    color: ${({ theme }) => theme.texto_principal};
    font: inherit;
    font-size: 0.9em;
    text-align: left;
    cursor: pointer;

    i {
        color: ${({ $tom, theme }) => $tom ?? theme.principal};
        font-size: 20px;
    }

    &:hover {
        border-color: ${({ theme }) => theme.principal};
    }
`;

