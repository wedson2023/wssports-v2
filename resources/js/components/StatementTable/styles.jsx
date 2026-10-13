import styled, { css } from 'styled-components';
import { mobile } from '../../theme/tokens';

// ContainerList, List, Thead, Tr, Td, Ico e Status de screens/credits/styles.js
export const Container = styled.div`
    margin-top: 25px;
    padding-bottom: 100px;
    width: 100%;
    overflow-x: auto;
    overflow-y: hidden;
    -webkit-overflow-scrolling: touch;

    &::-webkit-scrollbar-track {
        background-color: #999;
    }

    &::-webkit-scrollbar {
        height: 7px;
    }

    &::-webkit-scrollbar-thumb {
        background-color: #666;
    }
`;

export const List = styled.div`
    width: 100%;

    ${mobile} {
        width: 800px;
    }
`;

const linha = css`
    display: flex;
    flex-direction: row;
    justify-content: space-between;
    align-items: center;
    padding: 5px 0;

    > div {
        width: 20%;
        padding: 5px 0;
        font-size: 12px;
        color: ${({ theme }) => theme.texto_principal};
        text-align: center;
        display: flex;
        justify-content: center;
        align-items: center;
    }
`;

export const Head = styled.div`
    ${linha}
    background-color: ${({ theme }) => theme.principal};

    > div {
        color: #fff;
        font-weight: 500;
    }
`;

export const Row = styled.div`
    ${linha}
    cursor: ${({ $clicavel }) => ($clicavel ? 'pointer' : 'default')};

    &:nth-child(odd) {
        background-color: ${({ theme }) => theme.superficie_titulo};
    }
`;

export const Status = styled.span`
    color: ${({ $cor }) => $cor};
    font-size: 22px;
`;

// botões de ação do bônus (Ico do antigo)
export const Icon = styled.button.attrs(() => ({ type: 'button', className: 'material-icons' }))`
    padding: 3px;
    color: ${({ theme }) => theme.texto_principal};
    background: transparent;
    border-radius: 0.3em;
    cursor: pointer;
    font-size: 20px;
    border: solid 2px ${({ theme }) => theme.texto_principal};
    margin: auto 5px;

    &:hover,
    &:focus-visible {
        background-color: ${({ theme }) => theme.principal};
        border-color: ${({ theme }) => theme.principal};
        color: #fff;
        outline: none;
    }
`;

export const Empty = styled.p`
    color: ${({ theme }) => theme.texto_apagado};
    font-size: 12px;
    text-align: center;
    margin: 25px auto;
    display: flex;
    justify-content: center;
`;

export const Sentinel = styled.div`
    height: 1px;
`;
