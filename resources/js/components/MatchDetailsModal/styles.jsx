import styled from 'styled-components';
import { mobile } from '../../theme/tokens';

// modals/odd do sistema antigo
export const Container = styled.div`
    width: 40%;
    height: 450px;
    background: #fff;
    display: flex;
    flex-direction: column;
    position: fixed;
    z-index: 50;
    top: ${({ $visivel }) => ($visivel ? '15%' : '25%')};
    left: 50%;
    margin-left: -20%;
    transition-duration: 0.5s;
    opacity: ${({ $visivel }) => ($visivel ? 1 : 0)};
    visibility: ${({ $visivel }) => ($visivel ? 'visible' : 'hidden')};

    ${mobile} {
        width: 100%;
        height: 100vh;
        height: 100dvh;
        top: ${({ $visivel }) => ($visivel ? '0' : '20%')};
        left: 0;
        margin-left: 0;
    }
`;

export const Loading = styled.div`
    width: 45px;
    height: 45px;
    position: absolute;
    top: 50%;
    left: 50%;
    margin: -22px 0 0 -22px;
`;

export const Header = styled.header`
    display: flex;
    justify-content: space-between;
    align-items: center;
    height: 45px;
    min-height: 45px;
    background: #222;
    padding-left: 10px;
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

export const TextMatch = styled.span`
    color: #fff;
`;

export const Tabs = styled.div`
    display: flex;
    flex-direction: row;
    margin-bottom: 5px;
`;

export const Tab = styled.a`
    display: flex;
    flex: 1;
    color: #fff;
    padding: 10px;
    justify-content: center;
    align-items: center;
    background-color: ${({ $ativa, theme }) => ($ativa ? theme.principal : '#fff')};
    border: 2px solid ${({ theme }) => theme.principal};
    cursor: pointer;
    user-select: none;
`;

export const TabText = styled.span`
    font-size: 12px;
    color: ${({ $ativa, theme }) => ($ativa ? '#fff' : theme.principal)};
    font-weight: 500;
`;

export const List = styled.section`
    overflow-x: hidden;
    overflow-y: auto;
    -webkit-overflow-scrolling: touch;
    padding-bottom: 70px;
    flex: 1;

    &::-webkit-scrollbar-track {
        background-color: #999;
    }

    &::-webkit-scrollbar {
        width: 7px;
    }

    &::-webkit-scrollbar-thumb {
        background-color: #666;
    }
`;

export const Category = styled.div`
    padding: 10px;
    color: #fff;
    background: ${({ theme }) => theme.principal};
`;

export const Item = styled.div`
    display: flex;
    padding: 10px 15px;
    justify-content: space-between;
    align-items: center;
    border-bottom: solid thin #ccc;
`;

export const TextOdd = styled.span`
    flex: 1;
    display: block;
    overflow: hidden;
    white-space: nowrap;
    text-overflow: ellipsis;
    user-select: none;
    color: #333;
`;

export const Message = styled.p`
    display: block;
    text-align: center;
    margin: 35px auto;
    color: #999;
    font-size: 0.9em;
    user-select: none;
`;
