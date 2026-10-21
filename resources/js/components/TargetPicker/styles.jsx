import styled from 'styled-components';
import { mobile } from '../../theme/tokens';

// borda dos campos: no tema claro um cinza mais forte, para os campos aparecerem no fundo branco
const borda_campo = ({ theme }) => (theme.modo === 'escuro' ? theme.superficie_titulo : '#a8a8a8');

export const Field = styled.div`
    display: flex;
    flex: 1;
    flex-direction: column;
    gap: 6px;
    min-width: 260px;

    ${mobile} {
        flex: 1 1 100%;
        min-width: 0;
    }
`;

export const Label = styled.label`
    color: ${({ theme }) => theme.texto_secundario};
    font-size: 0.8em;
`;

export const Select = styled.select`
    width: 100%;
    min-height: 44px;
    padding: 10px;
    border: solid 1px ${borda_campo};
    border-radius: 6px;
    background-color: ${({ theme }) => theme.fundo_pagina};
    color: ${({ theme }) => theme.texto_principal};
    font: inherit;

    &:focus {
        outline: none;
        border-color: ${({ theme }) => theme.principal};
    }
`;

export const Hint = styled.span`
    color: ${({ theme }) => theme.texto_apagado};
    font-size: 0.78em;
`;
