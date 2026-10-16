import styled from 'styled-components';

import { mobile } from '../../theme/tokens';

export const Container = styled.div`
    width: 50%;
    background: #fff;
    position: fixed;
    z-index: 50;
    top: ${({ $aberto }) => ($aberto ? '15%' : '25%')};
    left: 50%;
    margin-left: -25%;
    transition-duration: 0.3s;
    opacity: ${({ $aberto }) => ($aberto ? 1 : 0)};
    visibility: ${({ $aberto }) => ($aberto ? 'visible' : 'hidden')};
    height: 450px;

    ${mobile} {
        width: 100%;
        margin-left: -50%;
        height: 100vh;
        top: ${({ $aberto }) => ($aberto ? '0' : '25%')};
    }
`;

export const Screen = styled.div`
    position: fixed;
    width: 100%;
    height: 100vh;
    z-index: 25;
    background: rgba(0, 0, 0, 0.8);
    top: 0;
    left: 0;
    transition-duration: 0.5s;
    opacity: ${({ $aberto }) => ($aberto ? 1 : 0)};
    visibility: ${({ $aberto }) => ($aberto ? 'visible' : 'hidden')};
`;

export const Header = styled.header`
    display: flex;
    justify-content: space-between;
    align-items: center;
    height: 45px;
    background: #222;
    padding-left: 10px;
`;

export const Title = styled.span`
    color: #fff;
`;

export const Icon = styled.i.attrs(() => ({ className: 'material-icons' }))`
    user-select: none;
    display: block;
    color: ${({ theme }) => theme.principal};
    padding: 0 10px;
    font-size: 25px;
    cursor: pointer;

    &:hover {
        opacity: 0.8;
    }
`;

export const List = styled.div`
    overflow-x: hidden;
    overflow-y: auto;
    -webkit-overflow-scrolling: touch;
    height: 405px;

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
        padding-bottom: 100px;
        height: 100vh;
    }
`;

export const Table = styled.table`
    width: 100%;
`;

export const Tr = styled.tr`
    text-decoration: ${({ $cancelada }) => ($cancelada ? 'line-through' : 'none')};

    &:nth-child(even) {
        background-color: #666;
        color: #fff;
    }
`;

export const Td = styled.td`
    padding: 5px;
    text-align: center;
    word-break: break-all;

    ${mobile} {
        font-size: 0.9em;
    }

    @media screen and (max-width: 500px) {
        font-size: 0.85em;
    }
`;

export const Wallet = styled.small`
    display: block;
    font-size: 0.75em;
    opacity: 0.7;
`;

export const More = styled.button`
    display: block;
    margin: 10px auto;
    padding: 8px 15px;
    border: solid thin #999;
    border-radius: 0.3em;
    background: transparent;
    color: #666;
    cursor: pointer;
`;
