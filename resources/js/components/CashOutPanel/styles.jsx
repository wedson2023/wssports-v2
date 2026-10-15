import styled, { css } from 'styled-components';

import { cores_estado } from '../../utils/live_states';

export const Card = styled.section`
    background-color: ${({ theme }) => theme.superficie_titulo};
    border-radius: 10px;
    padding: 14px;
    display: flex;
    flex-direction: column;
    gap: 12px;
    color: ${({ theme }) => theme.texto_principal};
    scroll-margin-top: 12px;
`;

export const Header = styled.div`
    display: flex;
    flex-direction: column;
    gap: 4px;
`;

export const OfferLabel = styled.h2`
    font-size: 12px;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: ${({ theme }) => theme.texto_secundario};
`;

export const Offer = styled.strong`
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 26px;
    font-weight: 700;
    font-variant-numeric: tabular-nums;
`;

export const Trend = styled.span`
    display: inline-flex;
    align-items: center;
    font-size: 12px;
    font-weight: 500;
    color: ${({ $cor }) => $cor};

    .material-icons {
        font-size: 16px;
    }
`;

export const Reason = styled.p`
    font-size: 13px;
    color: ${({ theme }) => theme.texto_secundario};
`;

export const Message = styled.p`
    display: flex;
    flex-direction: column;
    gap: 2px;
    font-size: 12px;
    color: ${({ theme }) => theme.texto_secundario};
`;

export const Done = styled.p`
    padding: 10px;
    border-radius: 6px;
    font-size: 13px;
    color: #fff;
    background-color: ${({ $recusado }) => ($recusado ? cores_estado.perdendo : cores_estado.ganhando)};
`;

export const Tabs = styled.div`
    display: flex;
    gap: 4px;
    padding: 3px;
    border-radius: 8px;
    background-color: ${({ theme }) => theme.superficie_barra};
`;

export const Tab = styled.button`
    flex: 1;
    height: 34px;
    border: 0;
    border-radius: 6px;
    cursor: pointer;
    font-size: 13px;
    color: ${({ theme }) => theme.texto_secundario};
    background-color: transparent;

    ${({ $ativa, theme }) => $ativa && css`
        color: #fff;
        background-color: ${theme.principal};
    `}
`;

export const Primary = styled.button`
    height: 46px;
    border: 0;
    border-radius: 6px;
    cursor: pointer;
    font-size: 15px;
    font-weight: 500;
    color: #fff;
    background-color: ${({ theme }) => theme.principal};

    &:disabled {
        opacity: 0.5;
        cursor: default;
    }
`;

export const Secondary = styled.button`
    border: 1px solid ${({ theme }) => theme.texto_apagado};
    border-radius: 6px;
    padding: 6px 10px;
    cursor: pointer;
    background-color: transparent;
    color: ${({ theme }) => theme.texto_principal};
`;

export const Field = styled.div`
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    font-size: 13px;
    color: ${({ $apagado, theme }) => ($apagado ? theme.texto_apagado : theme.texto_secundario)};

    strong {
        font-size: 18px;
        color: ${({ theme }) => theme.texto_principal};
        font-variant-numeric: tabular-nums;
    }

    input {
        width: 120px;
        padding: 8px;
        border-radius: 6px;
        border: 1px solid ${({ theme }) => theme.texto_apagado};
        background-color: ${({ theme }) => theme.fundo_pagina};
        color: ${({ theme }) => theme.texto_principal};
        text-align: right;
        font-variant-numeric: tabular-nums;
    }
`;

export const Slider = styled.input`
    width: 100%;
    accent-color: ${({ theme }) => theme.principal};
`;

export const Shortcuts = styled.div`
    display: flex;
    flex-wrap: wrap;
    gap: 6px;

    button {
        padding: 6px 10px;
        border-radius: 999px;
        border: 1px solid ${({ theme }) => theme.principal};
        background-color: transparent;
        color: ${({ theme }) => theme.texto_principal};
        font-size: 12px;
        cursor: pointer;
    }
`;
