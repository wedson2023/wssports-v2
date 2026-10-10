import styled from 'styled-components';
import { mobile } from '../../theme/tokens';

// Filter e BtnFilter de main/styles.js
export const Container = styled.div`
    display: flex;
    flex-direction: row;
    background: ${({ theme }) => theme.fundo_pagina};
    align-items: center;
    margin-bottom: 5px;
    flex-shrink: 0;
`;

export const Tab = styled.a`
    flex: 1;
    padding: 7px;
    color: ${({ theme }) => theme.texto_secundario} !important;
    font-size: 13px;
    text-decoration: none;
    text-align: center;
    font-weight: 500;
    border-left: thin solid ${({ theme }) => theme.superficie_cupom};
    transition-duration: 0.3s;
    cursor: pointer;

    &:hover {
        background: ${({ theme }) => theme.superficie_titulo};
    }

    ${mobile} {
        flex: 1;
        padding: 15px 7px;
        font-size: 11px;
    }
`;
