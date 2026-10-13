import styled from 'styled-components';
import { mobile } from '../../theme/tokens';

// Menu, HeaderMenu, Item e Title de screens/credits/styles.js
export const Container = styled.nav`
    width: 20%;
    flex-shrink: 0;
    background: ${({ theme }) => theme.superficie_campeonato};
    display: flex;
    flex-direction: column;

    ${mobile} {
        position: fixed;
        z-index: 50;
        right: ${({ $aberto }) => ($aberto ? 0 : '-60%')};
        top: 0;
        width: 60%;
        height: 100vh;
        height: 100dvh;
        overflow-y: auto;
        transition-duration: 0.3s;
    }
`;

export const Header = styled.div`
    display: flex;
    justify-content: center;
    align-items: center;
    flex-shrink: 0;
    height: 45px;
    background: ${({ theme }) => theme.superficie_titulo};
    color: ${({ theme }) => theme.texto_principal};

    ${mobile} {
        justify-content: space-between;
        padding: 0 10px;
    }
`;

export const Title = styled.span`
    font-size: 13px;
`;

export const Close = styled.span`
    display: none;
    color: ${({ theme }) => theme.principal};
    font-size: 24px;
    cursor: pointer;

    ${mobile} {
        display: flex;
    }

    &:hover {
        opacity: 0.8;
    }
`;

export const Item = styled.button`
    padding: 10px;
    border: none;
    border-bottom: solid #999 1px;
    background: ${({ theme }) => theme.superficie_campeonato};
    color: #fff;
    display: flex;
    justify-content: flex-start;
    align-items: center;
    gap: 15px;
    cursor: pointer;
    transition-duration: 0.3s;
    font-size: 13px;
    text-align: left;

    i {
        font-size: 22px;
    }

    &:hover,
    &:focus-visible {
        background: #333;
        outline: none;
    }
`;
