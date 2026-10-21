import styled from 'styled-components';
import { mobile } from '../../theme/tokens';

// borda dos campos: no tema claro um cinza mais forte, para os campos aparecerem no fundo branco
const borda_campo = ({ theme }) => (theme.modo === 'escuro' ? '#3a3a3a' : '#a8a8a8');
const fundo_campo = ({ theme }) => (theme.modo === 'escuro' ? '#1b1b1b' : '#ffffff');

export const Loading = styled.div`
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 100vh;
    background-color: ${({ theme }) => theme.fundo_pagina};
`;

// área da tela, na largura das listas do painel
export const Section = styled.section`
    display: flex;
    flex-direction: column;
    gap: 14px;
    max-width: 1250px;
    margin: 14px auto 0;
    padding: 0 14px 24px;

    ${mobile} {
        padding: 0 8px 96px;
    }
`;

// público, tipo e histórico da aba Público
export const Bar = styled.div`
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
`;

// alternador segmentado (pré-jogo / ao vivo; dia dos jogos)
export const Segmented = styled.div`
    display: flex;
    flex-shrink: 0;
    border: solid 1px ${borda_campo};
    border-radius: 6px;
    overflow: hidden;

    button {
        min-height: 44px;
        padding: 0 16px;
        border: none;
        background-color: ${fundo_campo};
        color: ${({ theme }) => theme.texto_secundario};
        font: inherit;
        font-size: 0.85em;
        font-weight: 500;
        cursor: pointer;
    }

    button + button {
        border-left: solid 1px ${borda_campo};
    }

    button[aria-pressed='true'] {
        background-color: ${({ theme }) => theme.principal};
        color: #fff;
    }

    ${mobile} {
        flex: 1;

        button {
            flex: 1;
        }
    }
`;

export const Tools = styled.button`
    display: flex;
    align-items: center;
    gap: 6px;
    min-height: 44px;
    padding: 0 14px;
    border: solid 1px ${borda_campo};
    border-radius: 6px;
    background: transparent;
    color: ${({ theme }) => theme.texto_principal};
    font: inherit;
    font-size: 0.85em;
    cursor: pointer;

    i {
        font-size: 19px;
    }
`;

// filtros das listas (busca, país, dia, só com ajuste)
export const Filters = styled.div`
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
`;

export const SearchBox = styled.label`
    display: flex;
    flex: 1;
    align-items: center;
    gap: 6px;
    min-width: 220px;
    padding: 0 10px;
    border: solid 1px ${borda_campo};
    border-radius: 6px;
    background-color: ${fundo_campo};
    color: ${({ theme }) => theme.texto_secundario};

    ${mobile} {
        flex: 1 1 100%;
    }
`;

export const Search = styled.input`
    flex: 1;
    min-width: 0;
    padding: 11px 0;
    border: none;
    background: transparent;
    color: ${({ theme }) => theme.texto_principal};
    font: inherit;

    &:focus {
        outline: none;
    }
`;

export const Select = styled.select`
    min-height: 44px;
    padding: 0 10px;
    border: solid 1px ${borda_campo};
    border-radius: 6px;
    background-color: ${fundo_campo};
    color: ${({ theme }) => theme.texto_principal};
    font: inherit;

    ${mobile} {
        flex: 1;
    }
`;

export const Switch = styled.label`
    display: flex;
    align-items: center;
    gap: 8px;
    min-height: 44px;
    color: ${({ theme }) => theme.texto_principal};
    font-size: 0.88em;
    cursor: pointer;

    input {
        width: 18px;
        height: 18px;
        accent-color: ${({ theme }) => theme.principal};
    }
`;

export const Notice = styled.div`
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
    padding: 10px 12px;
    border-left: solid 3px ${({ theme }) => theme.principal};
    border-radius: 6px;
    background-color: ${({ theme }) => (theme.modo === 'escuro' ? '#1b1b1b' : '#f5f5f5')};
    color: ${({ theme }) => theme.texto_secundario};
    font-size: 0.85em;

    > i {
        color: ${({ theme }) => theme.principal};
    }

    button {
        margin-left: auto;
    }
`;

export const Info = styled.p`
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 0;
    color: ${({ theme }) => theme.texto_secundario};
    font-size: 0.85em;

    i {
        color: ${({ theme }) => theme.principal};
        font-size: 20px;
    }
`;

// grade à esquerda e prévia à direita (320 px); no celular a prévia vira faixa fixa embaixo
export const Columns = styled.div`
    display: grid;
    grid-template-columns: minmax(0, 1fr) 320px;
    align-items: start;
    gap: 16px;

    > div {
        display: flex;
        flex-direction: column;
        gap: 12px;
        min-width: 0;
    }

    ${mobile} {
        grid-template-columns: minmax(0, 1fr);
    }
`;

export const EmptyState = styled.div`
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 10px;
    padding: 48px 10px;
    color: ${({ theme }) => theme.texto_apagado};
    text-align: center;

    i {
        color: ${({ theme }) => theme.principal};
        font-size: 40px;
    }
`;

export const Flag = styled.img`
    width: 18px;
    height: 12px;
    margin-right: 8px;
    border-radius: 2px;
    object-fit: cover;
    vertical-align: middle;
`;
