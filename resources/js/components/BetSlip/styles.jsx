import styled from 'styled-components';
import { mobile } from '../../theme/tokens';

// Ticket e List de main/styles.js: coluna no desktop (20%, mínimo 240px) e painel no mobile
export const Container = styled.div`
    width: 20%;
    min-width: 240px;
    display: flex;
    flex-direction: column;
    background-color: ${({ theme }) => theme.superficie_cupom};
    transition-duration: 0.5s;

    ${mobile} {
        position: fixed;
        z-index: 250;
        left: 0;
        top: ${({ $aberto }) => ($aberto ? '0' : '10%')};
        width: 100%;
        min-width: 0;
        height: 100vh !important;
        height: 100dvh !important;
        opacity: ${({ $aberto }) => ($aberto ? 1 : 0)};
        visibility: ${({ $aberto }) => ($aberto ? 'visible' : 'hidden')};
    }
`;

export const List = styled.div`
    background: ${({ theme }) => theme.superficie_campeonato};
    overflow-x: hidden;
    overflow-y: auto;
    -webkit-overflow-scrolling: touch;
    flex: 1;

    &::-webkit-scrollbar-track {
        background-color: #cccccc;
    }

    &::-webkit-scrollbar {
        width: 6px;
    }

    &::-webkit-scrollbar-thumb {
        background-color: #666;
    }

    ${mobile} {
        flex: none;
        height: calc(100vh - 337px);
        height: calc(100dvh - 337px);
    }
`;
