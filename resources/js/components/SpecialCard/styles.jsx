import styled from 'styled-components';
import { mobile } from '../../theme/tokens';

// EventChampionships, Item, Name e Odd de screens/main/components/specials/styles.js
export const Header = styled.header`
    background-color: ${({ theme }) => theme.superficie_titulo};
    padding: 10px;
    color: ${({ theme }) => theme.texto_principal};
    font-weight: 500;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 10px;

    ${mobile} {
        font-size: 12px;
    }
`;

export const Item = styled.div`
    display: flex;
    padding: 10px 15px;
    justify-content: space-between;
    align-items: center;
    border-bottom: solid thin #ccc;
    color: ${({ theme }) => theme.texto_jogo};
`;

export const Name = styled.span`
    flex: 1;
    display: block;
    overflow: hidden;
    white-space: nowrap;
    text-overflow: ellipsis;
    user-select: none;
`;

export const Odd = styled.a`
    display: flex;
    align-items: center;
    justify-content: space-around;
    color: ${({ theme, $selecionado }) => ($selecionado ? theme.principal : '#fff')};
    width: 80px;
    border: solid 2px ${({ theme }) => theme.principal};
    height: 40px;
    font-size: 14px;
    font-weight: bold;
    text-decoration: none;
    padding: 0 7px;
    cursor: pointer;
    user-select: none;
    background-color: ${({ theme, $selecionado }) => ($selecionado ? 'transparent' : theme.principal)};
`;
