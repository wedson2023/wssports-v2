import styled from 'styled-components';

import { mobile } from '../../theme/tokens';

export const Container = styled.div`
    width: 860px;
    background: #fff;
    user-select: none;
    position: fixed;
    z-index: 50;
    top: ${({ $aberto }) => ($aberto ? '10%' : '25%')};
    left: 50%;
    margin-left: -430px;
    transition-duration: 0.3s;
    opacity: ${({ $aberto }) => ($aberto ? 1 : 0)};
    visibility: ${({ $aberto }) => ($aberto ? 'visible' : 'hidden')};
    height: 541px;
    overflow-y: auto;

    ${mobile} {
        width: 100%;
        margin-left: -50%;
        height: 100vh;
        top: ${({ $aberto }) => ($aberto ? '0' : '25%')};
    }
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
    color: ${({ $colorido, theme }) => ($colorido ? theme.principal : 'inherit')};
    padding: 0 10px;
    font-size: 25px;
    cursor: pointer;

    &:hover {
        opacity: 0.8;
    }
`;

export const Content = styled.div``;

export const Message = styled.p`
    display: block;
    text-align: center;
    margin: 35px auto;
    color: #999;
    font-size: 0.9em;
    user-select: none;
`;

export const Item = styled.div`
    padding: 10px;
    margin: 15px;
    display: flex;
    align-items: center;
    justify-content: flex-start;
    box-shadow: 0px 0px 10px #999;
`;

export const Text = styled.span`
    padding: 10px;
    text-indent: 40px;
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
