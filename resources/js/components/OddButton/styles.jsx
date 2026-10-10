import styled, { css, keyframes } from 'styled-components';

// animações de variação da cotação (matches/styles.js): sobe = verde, desce = vermelho
const green = keyframes`
    0% { background-color: none; }
    50% { background-color: green; color: #fff; }
    100% { background-color: none; }
`;

const red = keyframes`
    0% { background-color: none; }
    50% { background-color: red; color: #fff; }
    100% { background-color: none; }
`;

const animacoes = { subiu: green, desceu: red };

// BtnOption de matches/styles.js; a variação larga é o ValueOdd de modals/odd
export const Button = styled.a`
    display: flex;
    align-items: center;
    justify-content: space-around;
    width: ${({ $largo }) => ($largo ? '80px' : '19.5%')};
    border: solid 2px ${({ theme }) => theme.principal};
    border-radius: ${({ $largo }) => ($largo ? '0' : '0.5em')};
    height: 40px;
    font-size: 14px;
    font-weight: bold;
    text-decoration: none;
    user-select: none;
    cursor: ${({ $bloqueado }) => ($bloqueado ? 'default' : 'pointer')};
    padding: ${({ $largo }) => ($largo ? '0 7px' : '0')};
    background-color: ${({ $selecionado, theme }) => ($selecionado ? 'transparent' : theme.principal)};
    color: ${({ $selecionado, theme }) => ($selecionado ? theme.principal : '#fff')};

    ${({ $variacao }) => $variacao && css`
        animation: ${animacoes[$variacao]} 0.5s;
        animation-iteration-count: 4;
    `}
`;

export const Letter = styled.div`
    text-align: center;
    padding: 12px 0;
    width: 20px;
    height: 40px;
    background-color: ${({ theme }) => theme.derivada};
    border-top-left-radius: 0.5em;
    border-bottom-left-radius: 0.5em;
    color: #fff !important;
`;

export const Odd = styled.div`
    flex: 1;
    text-align: center;
`;

export const Lock = styled.i.attrs(() => ({ className: 'material-icons' }))`
    color: ${({ $selecionado, theme }) => ($selecionado ? theme.principal : '#fff')};
`;
