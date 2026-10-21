import styled, { css } from 'styled-components';
import { mobile } from '../../theme/tokens';

// borda dos campos: no tema claro um cinza mais forte, para os campos aparecerem no fundo branco
const borda_campo = ({ theme }) => (theme.modo === 'escuro' ? theme.superficie_titulo : '#a8a8a8');

// ContainerInput, Label, Input e Select de modals/register do antigo, em grupos
export const Group = styled.section`
    display: flex;
    flex-direction: column;
    gap: 12px;
`;

export const GroupTitle = styled.h4`
    margin: 0;
    color: ${({ theme }) => theme.texto_secundario};
    font-size: 0.78em;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.4px;
`;

export const Grid = styled.div`
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 16px 14px;

    ${mobile} {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
`;

export const Field = styled.div`
    display: flex;
    flex-direction: column;
    gap: 6px;
    min-width: 0;
    grid-column: ${({ $largo }) => ($largo ? '1 / -1' : 'auto')};
`;

export const Label = styled.label`
    color: ${({ theme }) => theme.texto_secundario};
    font-size: 0.78em;
`;

const campo = css`
    width: 100%;
    padding: 10px;
    border: solid 1px ${({ theme, $erro }) => ($erro ? '#e53935' : borda_campo({ theme }))};
    border-radius: 4px;
    background-color: ${({ theme }) => theme.fundo_pagina};
    color: ${({ theme }) => theme.texto_principal};
    font: inherit;

    &:focus {
        outline: none;
        border-color: ${({ theme }) => theme.principal};
    }

    &:disabled {
        opacity: 0.6;
    }
`;

export const Input = styled.input`${campo}`;

export const Select = styled.select`${campo}`;

export const TextArea = styled.textarea`
    ${campo}
    resize: vertical;
`;

export const Chips = styled.div`
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 6px;
`;

// esporte da lista: marcado fica cheio na cor do tema; desmarcado, só com a borda
export const Chip = styled.button`
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 5px 10px;
    border: solid 1px ${({ theme, $selecionado }) => ($selecionado ? theme.principal : borda_campo({ theme }))};
    border-radius: 14px;
    background-color: ${({ theme, $selecionado }) => ($selecionado ? theme.principal : 'transparent')};
    color: ${({ theme, $selecionado }) => ($selecionado ? '#fff' : theme.texto_secundario)};
    font: inherit;
    font-size: 0.78em;
    cursor: pointer;
    transition: background-color 0.15s, border-color 0.15s;

    i {
        font-size: 15px;
    }

    &:hover:not(:disabled) {
        border-color: ${({ theme }) => theme.principal};
        color: ${({ theme, $selecionado }) => ($selecionado ? '#fff' : theme.texto_principal)};
    }

    &:disabled {
        cursor: default;
    }
`;

export const Error = styled.span`
    color: #e53935;
    font-size: 0.72em;
`;
