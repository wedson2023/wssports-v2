import styled from 'styled-components';
import { mobile } from '../../theme/tokens';

export const Loading = styled.div`
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 100vh;
    background-color: ${({ theme }) => theme.fundo_pagina};
`;

// área da lista, na largura máxima do Group do antigo
export const Section = styled.section`
    display: flex;
    flex-direction: column;
    gap: 10px;
    max-width: 1250px;
    margin: 14px auto 0;
    padding: 0 14px;

    ${mobile} {
        padding: 0 8px;
    }
`;

// Bar do antigo: título com o total, busca e o botão de novo; no celular tudo numa linha só, com a
// busca na lupa e o botão de novo só com o ícone
export const Bar = styled.div`
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;

    ${mobile} {
        flex-wrap: nowrap;
        gap: 8px;
    }
`;

export const Title = styled.h2`
    flex: 1;
    margin: 0;
    color: ${({ theme }) => theme.texto_principal};
    font-size: 1em;
    font-weight: 500;
    min-width: 200px;

    ${mobile} {
        display: ${({ $escondido }) => ($escondido ? 'none' : 'block')};
        min-width: 0;
        overflow: hidden;
        font-size: 0.92em;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
`;

export const SearchBox = styled.div`
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 0 10px;
    border: solid 1px ${({ theme }) => theme.superficie_titulo};
    border-radius: 4px;
    background-color: ${({ theme }) => theme.fundo_pagina};
    color: ${({ theme }) => theme.texto_secundario};

    ${mobile} {
        flex: ${({ $expandida }) => ($expandida ? 1 : '0 0 auto')};
        gap: ${({ $expandida }) => ($expandida ? '4px' : '0')};
        padding: 0 ${({ $expandida }) => ($expandida ? '8px' : '0')};
        border-color: ${({ theme, $expandida }) => ($expandida ? theme.principal : theme.superficie_titulo)};

        input {
            width: ${({ $expandida }) => ($expandida ? '100%' : '0')};
        }
    }
`;

// lupa: no computador é só o ícone do campo; no celular é o botão que abre a busca
export const SearchToggle = styled.button`
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0;
    border: none;
    background: transparent;
    color: inherit;
    pointer-events: none;

    ${mobile} {
        width: 36px;
        height: 36px;
        pointer-events: auto;
        cursor: pointer;
    }
`;

export const Search = styled.input`
    width: 220px;
    padding: 9px 0;
    border: none;
    background: transparent;
    color: ${({ theme }) => theme.texto_principal};
    font: inherit;

    &:focus {
        outline: none;
    }

    ${mobile} {
        min-width: 0;
        transition: width 0.2s;
    }
`;

export const NewButton = styled.button`
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    flex-shrink: 0;
    height: 38px;
    padding: 0 14px;
    border: none;
    border-radius: 4px;
    background-color: ${({ theme }) => theme.principal};
    color: #fff;
    font: inherit;
    font-weight: 500;
    cursor: pointer;

    i {
        font-size: 20px;
    }

    ${mobile} {
        width: 38px;
        padding: 0;

        span {
            display: none;
        }
    }
`;
