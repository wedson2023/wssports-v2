import styled from 'styled-components';
import { mobile } from '../../theme/tokens';

// fundo do cartão e da linha em cada tema, com contraste sobre o fundo do modal
const fundo_grupo = ({ theme }) => (theme.modo === 'escuro' ? '#1b1b1b' : '#ffffff');
const fundo_cabecalho = ({ theme }) => (theme.modo === 'escuro' ? '#262626' : '#f3f3f3');
const borda = ({ theme }) => (theme.modo === 'escuro' ? '#3a3a3a' : '#e2e2e2');
const borda_campo = ({ theme }) => (theme.modo === 'escuro' ? '#3a3a3a' : '#a8a8a8');

export const Toolbar = styled.div`
    position: sticky;
    top: -16px;
    z-index: 1;
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
    margin: -16px -16px 0;
    padding: 12px 16px;
    background-color: ${({ theme }) => theme.fundo_pagina};
    border-bottom: solid 1px ${borda};
`;

export const SearchBox = styled.label`
    display: flex;
    flex: 1;
    align-items: center;
    gap: 6px;
    min-width: 200px;
    padding: 0 10px;
    border: solid 1px ${borda_campo};
    border-radius: 6px;
    background-color: ${fundo_grupo};
    color: ${({ theme }) => theme.texto_secundario};
`;

export const Search = styled.input`
    flex: 1;
    padding: 9px 0;
    border: none;
    background: transparent;
    color: ${({ theme }) => theme.texto_principal};
    font: inherit;

    &:focus {
        outline: none;
    }
`;

export const Summary = styled.span`
    color: ${({ theme }) => theme.texto_secundario};
    font-size: 0.85em;
    white-space: nowrap;

    strong {
        color: ${({ theme }) => theme.principal};
        font-size: 1.1em;
    }
`;

// cartão do grupo; não encolhe dentro da área que rola (antes os itens ficavam cortados)
export const Group = styled.section`
    flex-shrink: 0;
    border: solid 1px ${({ theme, $aberto }) => ($aberto ? theme.principal : borda({ theme }))};
    border-radius: 8px;
    background-color: ${fundo_grupo};
    overflow: hidden;
`;

export const GroupHeader = styled.div`
    display: flex;
    align-items: center;
    gap: 8px;
    padding-right: 12px;
    background-color: ${fundo_cabecalho};

    > button {
        display: flex;
        flex: 1;
        align-items: center;
        gap: 10px;
        min-width: 0;
        padding: 12px;
        border: none;
        background: transparent;
        color: ${({ theme }) => theme.texto_principal};
        font: inherit;
        text-align: left;
        cursor: pointer;
    }
`;

export const GroupIcon = styled.i.attrs(() => ({ className: 'material-icons', 'aria-hidden': true }))`
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    width: 32px;
    height: 32px;
    border-radius: 8px;
    background-color: ${({ theme }) => theme.principal};
    color: #fff;
    font-size: 18px;
`;

export const GroupTitle = styled.span`
    flex: 1;
    min-width: 0;
    font-size: 0.92em;
    font-weight: 500;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
`;

// contagem de ativas do grupo: verde quando todas, cinza quando nenhuma
export const Badge = styled.span`
    flex-shrink: 0;
    padding: 2px 8px;
    border-radius: 10px;
    background-color: ${({ $completo, $vazio, theme }) => {
        if ($completo) return '#2e7d32';
        if ($vazio) return theme.modo === 'escuro' ? '#3a3a3a' : '#dcdcdc';
        return '#f9a825';
    }};
    color: ${({ $completo, $vazio, theme }) => ($completo ? '#fff' : ($vazio ? theme.texto_secundario : '#000'))};
    font-size: 0.75em;
    font-weight: 500;
`;

export const Chevron = styled.i.attrs(() => ({ className: 'material-icons', 'aria-hidden': true }))`
    flex-shrink: 0;
    color: ${({ theme }) => theme.texto_secundario};
`;

export const AllToggle = styled.label`
    display: flex;
    align-items: center;
    gap: 6px;
    flex-shrink: 0;
    color: ${({ theme }) => theme.texto_secundario};
    font-size: 0.78em;

    ${mobile} {
        span {
            display: none;
        }
    }
`;

export const Items = styled.div`
    display: flex;
    flex-direction: column;
`;

// linha inteira clicável; a ligada ganha uma faixa na cor do tema
export const Item = styled.div`
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    padding: 12px 14px;
    border-top: solid 1px ${borda};
    border-left: solid 3px ${({ theme, $ligada }) => ($ligada ? theme.principal : 'transparent')};
    cursor: pointer;
    transition: background-color 0.15s;

    &:hover {
        background-color: ${fundo_cabecalho};
    }
`;

export const Texts = styled.div`
    display: flex;
    flex-direction: column;
    gap: 3px;
    min-width: 0;
`;

export const Name = styled.span`
    color: ${({ theme }) => theme.texto_principal};
    font-size: 0.9em;
    font-weight: 500;
`;

export const Description = styled.span`
    color: ${({ theme }) => (theme.modo === 'escuro' ? '#b5b5b5' : '#5f5f5f')};
    font-size: 0.78em;
    line-height: 1.35;
`;

export const Empty = styled.div`
    display: flex;
    justify-content: center;
    padding: 30px 10px;
    color: ${({ theme }) => theme.texto_apagado};
    text-align: center;
`;
