import styled from 'styled-components';

import { mobile } from '../../theme/tokens';

export const Container = styled.div`
    width: 90%;
    margin: 15px auto 0 auto;
    overflow-y: hidden;
    overflow-x: auto;
    user-select: none;
    padding-bottom: 10px;

    @media screen and (min-width: 900px) {
        -webkit-overflow-scrolling: touch;

        &::-webkit-scrollbar-track {
            background-color: #cccccc;
        }

        &::-webkit-scrollbar {
            height: 5px;
        }

        &::-webkit-scrollbar-thumb {
            background-color: #666;
        }
    }
`;

// no computador os tipos dividem a largura; no celular, 4 por tela com rolagem (como o antigo)
export const List = styled.div`
    display: flex;

    ${mobile} {
        width: ${({ $quantidade }) => `calc(${$quantidade} * 25%)`};
    }
`;

export const Item = styled.span`
    width: ${({ $quantidade }) => `calc(100% / ${$quantidade || 1})`};
    border-radius: 1em;
    border: solid 2px ${({ theme }) => theme.principal};
    background-color: ${({ $selecionado, theme }) => ($selecionado ? theme.principal : '#222')};
    color: #fff;
    font-weight: 300;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.9em;
    height: 32px;
    margin: auto 5px;
    white-space: nowrap;

    &:hover {
        background-color: ${({ theme }) => theme.principal};
        color: #fff;
        cursor: pointer;
    }
`;
