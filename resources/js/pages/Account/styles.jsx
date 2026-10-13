import styled from 'styled-components';
import { mobile } from '../../theme/tokens';

// Container e Content de screens/credits/styles.js: conteúdo à esquerda (80%) e menu à direita
// (20%) no desktop; no mobile, o conteúdo ocupa tudo e o menu vira gaveta
export const Container = styled.div`
    height: 100vh;
    height: 100dvh;
    background-color: ${({ theme }) => theme.fundo_pagina};
    text-align: center;
    overflow: hidden;
    user-select: none;
    display: flex;
    justify-content: space-between;
    position: relative;
`;

export const Content = styled.main`
    padding: 15px 10px;
    width: 80%;
    overflow-x: hidden;
    overflow-y: auto;
    height: 100%;
    -webkit-overflow-scrolling: touch;

    &::-webkit-scrollbar-track {
        background-color: #999;
    }

    &::-webkit-scrollbar {
        width: 7px;
    }

    &::-webkit-scrollbar-thumb {
        background-color: #666;
    }

    ${mobile} {
        width: 100%;
    }
`;

export const Loading = styled.p`
    color: ${({ theme }) => theme.texto_principal};
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
`;
