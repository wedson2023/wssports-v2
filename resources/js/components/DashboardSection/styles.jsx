import styled, { css, keyframes } from 'styled-components';
import { mobile } from '../../theme/tokens';

const girar = keyframes`
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
`;

const pulsar = keyframes`
    0% { opacity: 0.4; }
    50% { opacity: 0.8; }
    100% { opacity: 0.4; }
`;

// Group de screens/adm/dashboard/styles.js
export const Container = styled.section`
    grid-column: ${({ $largura_total }) => ($largura_total ? '1 / -1' : 'auto')};
    display: flex;
    flex-direction: column;
    min-width: 0;
    border-radius: 4px;
    overflow: hidden;
    background-color: ${({ theme }) => theme.fundo_pagina};
    border: solid 1px ${({ theme }) => theme.superficie_titulo};

    ${mobile} {
        grid-column: auto;
    }
`;

// Header escuro de screens/adm/dashboard/styles.js
export const Header = styled.header`
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 12px;
    background-color: #333;
    color: #f1f1f1;
    font-weight: 500;
`;

export const Title = styled.h2`
    margin: 0;
    font-size: 0.9em;
    font-weight: 500;
    text-transform: uppercase;
`;

export const Icons = styled.div`
    display: flex;
    align-items: center;
    gap: 10px;
`;

export const RefreshIcon = styled.i.attrs(() => ({ className: 'material-icons', role: 'button' }))`
    color: #fff;
    font-size: 22px;
    cursor: pointer;
    user-select: none;

    ${({ $girando }) => $girando && css`
        animation: ${girar} 0.9s linear infinite;
        cursor: default;
    `}
`;

export const Body = styled.div`
    display: flex;
    flex-direction: column;
    gap: 10px;
    padding: 12px;
    min-width: 0;
`;

export const SkeletonGrid = styled.div`
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: 10px;

    ${mobile} {
        grid-template-columns: 1fr 1fr;
    }
`;

export const SkeletonBlock = styled.div`
    height: ${({ $altura }) => `${$altura}px`};
    border-radius: 4px;
    background-color: ${({ theme }) => theme.superficie_titulo};
    animation: ${pulsar} 1.2s ease-in-out infinite;
`;

export const ErrorBox = styled.div`
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 10px;
    padding: 20px 10px;
    text-align: center;
    color: ${({ theme }) => theme.texto_secundario};
    font-size: 0.9em;
`;

export const RetryButton = styled.button`
    padding: 8px 14px;
    border: solid 2px ${({ theme }) => theme.principal};
    border-radius: 4px;
    background: transparent;
    color: ${({ theme }) => theme.principal};
    font-weight: bold;
    cursor: pointer;
`;
