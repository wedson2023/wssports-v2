import styled from 'styled-components';

import { mobile } from '../../theme/tokens';

// abre pela esquerda, como o menu antigo (left: 0 aberto; -20% fechado, -60% no celular)
export const Menu = styled.div`
    transition-duration: 0.3s;
    width: 20%;
    background-color: #333;
    display: flex;
    flex-direction: column;
    position: fixed;
    z-index: 50;
    left: ${({ $aberto }) => ($aberto ? '0' : '-20%')};
    top: 0;
    overflow-x: hidden;
    overflow-y: auto;
    height: 100%;
    -webkit-overflow-scrolling: touch;

    &::-webkit-scrollbar-track {
        background-color: #cccccc;
    }

    &::-webkit-scrollbar {
        width: 5px;
    }

    &::-webkit-scrollbar-thumb {
        background-color: #666;
    }

    ${mobile} {
        width: 60%;
        left: ${({ $aberto }) => ($aberto ? '0' : '-60%')};
        padding-bottom: 70px;
    }
`;

export const Header = styled.div`
    display: flex;
    justify-content: space-between;
    align-items: center;
    min-height: 45px;
    background-color: #222;
    color: #fff;
    padding: 5px;
`;

export const Title = styled.span`
    font-size: 13px;
    margin-left: 15px;
`;

export const Icon = styled.i.attrs(() => ({ className: 'material-icons' }))`
    user-select: none;
    outline: none;
    color: ${({ $colorido, theme }) => ($colorido ? theme.principal : '#fff')};
    font-size: 25px;
    cursor: pointer;
`;

export const Divider = styled.div`
    background-color: ${({ theme }) => theme.principal};
    display: flex;
    align-items: center;
    padding: 10px;
`;

export const DividerText = styled.span`
    margin-left: 15px;
    color: #fff;
    font-weight: 500;
`;

export const Item = styled.div`
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 15px;
    border-bottom: solid thin #666;
    cursor: pointer;

    &:hover {
        background-color: #666;
    }
`;

export const Text = styled.span`
    color: #fff;
    text-transform: capitalize;
`;

export const Count = styled.div`
    min-width: 15px;
    height: 15px;
    font-size: 0.9em;
    color: #fff;
    background-color: ${({ theme }) => theme.principal};
`;

export const Screen = styled.div`
    position: fixed;
    width: 100%;
    z-index: 25;
    background: rgba(0, 0, 0, 0.8);
    top: 0;
    left: 0;
    transition-duration: 0.5s;
    opacity: ${({ $aberto }) => ($aberto ? 1 : 0)};
    visibility: ${({ $aberto }) => ($aberto ? 'visible' : 'hidden')};
    height: 100%;
`;
