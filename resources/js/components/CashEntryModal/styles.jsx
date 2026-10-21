import styled from 'styled-components';
import { mobile } from '../../theme/tokens';

// escolha do tipo em dois cartões com a explicação de cada um
export const Types = styled.div`
    display: grid;
    grid-auto-columns: minmax(0, 1fr);
    grid-auto-flow: column;
    gap: 10px;

    ${mobile} {
        grid-auto-flow: row;
    }
`;

export const Type = styled.button`
    display: grid;
    grid-template-columns: 28px minmax(0, 1fr);
    align-items: center;
    column-gap: 8px;
    row-gap: 2px;
    padding: 12px;
    border: solid 2px ${({ theme }) => theme.superficie_titulo};
    border-radius: 8px;
    background-color: ${({ theme }) => theme.superficie_barra};
    color: ${({ theme }) => theme.texto_principal};
    font: inherit;
    text-align: left;
    cursor: pointer;

    i {
        grid-row: 1 / 3;
        color: ${({ theme }) => theme.principal};
    }

    span {
        color: ${({ theme }) => theme.texto_secundario};
        font-size: 0.8em;
    }

    &[aria-checked='true'] {
        border-color: ${({ theme }) => theme.principal};
    }
`;

export const Counter = styled.span`
    align-self: flex-end;
    color: ${({ theme }) => theme.texto_apagado};
    font-size: 0.75em;
`;
