import styled, { keyframes } from 'styled-components';

const pulsar = keyframes`
    0% { opacity: 0.4; }
    50% { opacity: 0.8; }
    100% { opacity: 0.4; }
`;

export const Container = styled.div`
    display: flex;
    flex-direction: column;
    gap: 12px;
    width: 100%;
    min-width: 0;
`;

export const Area = styled.div`
    position: relative;
    width: 100%;
    height: ${({ $altura }) => `${$altura}px`};
`;

export const Canvas = styled.canvas`
    visibility: ${({ $visivel }) => ($visivel ? 'visible' : 'hidden')};
`;

// esqueleto do tamanho do gráfico enquanto a biblioteca carrega
export const Skeleton = styled.div`
    position: absolute;
    inset: 0;
    border-radius: 4px;
    background-color: ${({ theme }) => theme.superficie_titulo};
    animation: ${pulsar} 1.2s ease-in-out infinite;
`;

// total no centro da rosca (não bloqueia o tooltip das fatias)
export const Center = styled.div`
    position: absolute;
    inset: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    pointer-events: none;
`;

export const CenterValue = styled.strong`
    color: ${({ theme }) => theme.texto_principal};
    font-size: 1.8em;
    font-weight: 500;
    line-height: 1;
`;

export const CenterLabel = styled.span`
    margin-top: 4px;
    color: ${({ theme }) => theme.texto_secundario};
    font-size: 0.75em;
    text-transform: uppercase;
    letter-spacing: 0.4px;
`;

// legenda em grade de duas colunas, centralizada embaixo da rosca
export const Legend = styled.div`
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 8px 16px;
    width: 100%;
    max-width: 360px;
    margin: 0 auto;
`;

export const LegendItem = styled.div`
    display: grid;
    grid-template-columns: 10px 1fr auto;
    align-items: center;
    gap: 8px;
    min-width: 0;
    font-size: 0.82em;
`;

export const Dot = styled.span`
    width: 10px;
    height: 10px;
    border-radius: 50%;
`;

export const LegendLabel = styled.span`
    color: ${({ theme }) => theme.texto_principal};
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
`;

export const LegendValue = styled.span`
    color: ${({ theme }) => theme.texto_secundario};
    white-space: nowrap;
`;
