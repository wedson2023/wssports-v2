import styled from 'styled-components';

import { mobile } from '../../theme/tokens';

// no computador os provedores dividem a largura; no celular, 4 por tela com rolagem (como o antigo)
export const Container = styled.div`
    width: 90%;
    margin: 25px auto 0 auto;
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

    ${mobile} {
        overflow-y: hidden;
        overflow-x: auto;
    }
`;

export const List = styled.div`
    display: flex;

    ${mobile} {
        width: ${({ $quantidade }) => `calc(${$quantidade} * 25%)`};
    }
`;

export const Item = styled.div`
    width: ${({ $quantidade }) => `calc(100% / ${$quantidade || 1})`};
    height: 80px;
    padding: 5px;
    border-radius: 0.3em;
    background-color: ${({ $selecionado, theme }) => ($selecionado ? theme.principal : '#333')};
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 5px;
    cursor: pointer;
    transition-duration: 0.5s;

    &:hover {
        transform: scale(1.2);
    }
`;

export const Image = styled.img`
    width: 80%;
`;

export const Name = styled.span`
    color: #fff;
    font-size: 0.85em;
    text-align: center;
`;
