import styled from 'styled-components';

export const Box = styled.div`
    display: flex;
    flex-direction: column;
    gap: 10px;
    padding: 14px;
    border-radius: 10px;
    border: 2px solid ${({ theme }) => theme.principal};
    background-color: ${({ theme }) => theme.superficie_barra};
    color: ${({ theme }) => theme.texto_principal};
`;

export const Title = styled.h2`
    font-size: 14px;
    font-weight: 500;
`;

export const Amount = styled.strong`
    font-size: 30px;
    font-weight: 700;
    font-variant-numeric: tabular-nums;
`;

export const Details = styled.div`
    display: flex;
    flex-direction: column;
    gap: 4px;
`;

export const Line = styled.p`
    display: flex;
    justify-content: space-between;
    gap: 8px;
    font-size: 13px;
    color: ${({ theme }) => theme.texto_secundario};

    strong {
        color: ${({ theme }) => theme.texto_principal};
        font-weight: 500;
        font-variant-numeric: tabular-nums;
    }
`;

export const Preference = styled.label`
    display: flex;
    flex-direction: column;
    gap: 4px;
    font-size: 12px;
    color: ${({ theme }) => theme.texto_secundario};

    select {
        padding: 8px;
        border-radius: 6px;
        border: 1px solid ${({ theme }) => theme.texto_apagado};
        background-color: ${({ theme }) => theme.fundo_pagina};
        color: ${({ theme }) => theme.texto_principal};
    }
`;

export const Countdown = styled.span`
    font-size: 12px;
    font-variant-numeric: tabular-nums;
    color: ${({ theme }) => theme.texto_secundario};
`;

export const Actions = styled.div`
    display: grid;
    grid-template-columns: 1fr 2fr;
    gap: 8px;

    button {
        height: 44px;
        border: 0;
        border-radius: 6px;
        font-size: 15px;
        font-weight: 500;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    button:disabled {
        opacity: 0.6;
        cursor: default;
    }
`;

export const Cancel = styled.button`
    background-color: transparent;
    color: ${({ theme }) => theme.texto_principal};
    border: 1px solid ${({ theme }) => theme.texto_apagado} !important;
`;

export const Confirm = styled.button`
    background-color: ${({ theme }) => theme.principal};
    color: #fff;
`;
