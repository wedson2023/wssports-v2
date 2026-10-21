import styled from 'styled-components';
import { mobile } from '../../theme/tokens';

// borda dos campos: no tema claro um cinza mais forte, para os campos aparecerem no fundo branco
const borda_campo = ({ theme }) => (theme.modo === 'escuro' ? theme.superficie_titulo : '#a8a8a8');

// ContainerComissao, Label e Input de modals/register/seller do antigo
export const Section = styled.section`
    display: flex;
    flex-direction: column;
    gap: 12px;
`;

export const SectionTitle = styled.h4`
    margin: 0;
    color: ${({ theme }) => theme.texto_secundario};
    font-size: 0.78em;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.4px;
`;

export const Grid = styled.div`
    display: grid;
    grid-template-columns: repeat(6, minmax(0, 1fr));
    gap: 16px 12px;

    ${mobile} {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
`;

export const Field = styled.div`
    display: flex;
    flex-direction: column;
    gap: 6px;
    min-width: 0;
`;

export const Label = styled.label`
    color: ${({ theme }) => theme.texto_secundario};
    font-size: 0.75em;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
`;

export const Input = styled.input`
    width: 100%;
    padding: 8px;
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

export const Error = styled.span`
    color: #e53935;
    font-size: 0.72em;
`;
