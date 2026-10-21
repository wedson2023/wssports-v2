import styled from 'styled-components';
import { mobile } from '../../theme/tokens';

// borda dos campos: no tema claro um cinza mais forte, para os campos aparecerem no fundo branco
const borda_campo = ({ theme }) => (theme.modo === 'escuro' ? '#3a3a3a' : '#a8a8a8');

export const Filters = styled.div`
    display: grid;
    grid-template-columns: minmax(0, 1.2fr) repeat(2, minmax(0, 1fr));
    gap: 8px;

    ${mobile} {
        grid-template-columns: repeat(2, minmax(0, 1fr));

        > :first-child {
            grid-column: 1 / -1;
        }
    }
`;

const campo = `
    min-height: 40px;
    padding: 0 10px;
    border-radius: 4px;
    font: inherit;
`;

export const Select = styled.select`
    ${campo}
    border: solid 1px ${borda_campo};
    background-color: ${({ theme }) => theme.fundo_pagina};
    color: ${({ theme }) => theme.texto_principal};
`;

export const DateInput = styled.input`
    ${campo}
    border: solid 1px ${borda_campo};
    background-color: ${({ theme }) => theme.fundo_pagina};
    color: ${({ theme }) => theme.texto_principal};
    color-scheme: ${({ theme }) => (theme.modo === 'escuro' ? 'dark' : 'light')};
`;

export const Empty = styled.div`
    display: flex;
    justify-content: center;
    padding: 30px 10px;
    color: ${({ theme }) => theme.texto_apagado};
    text-align: center;
`;

export const List = styled.ul`
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin: 0;
    padding: 0;
    list-style: none;
`;

export const Item = styled.li`
    display: grid;
    grid-template-columns: 36px minmax(0, 1fr) auto;
    align-items: flex-start;
    gap: 10px;
    padding: 10px;
    border-radius: 8px;
    background-color: ${({ theme }) => theme.superficie_barra};
    opacity: ${({ $anulado }) => ($anulado ? 0.65 : 1)};
`;

export const ItemIcon = styled.i.attrs(() => ({ className: 'material-icons', 'aria-hidden': true }))`
    display: flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background-color: ${({ $tom }) => `${$tom}22`};
    color: ${({ $tom }) => $tom};
    font-size: 20px;
`;

export const ItemInfo = styled.div`
    display: flex;
    flex-direction: column;
    gap: 2px;
    min-width: 0;
`;

export const ItemTitle = styled.div`
    display: flex;
    justify-content: space-between;
    gap: 10px;
    font-weight: 500;
`;

export const Amount = styled.span`
    flex-shrink: 0;
    text-decoration: ${({ $anulado }) => ($anulado ? 'line-through' : 'none')};
`;

export const ItemMeta = styled.span`
    color: ${({ theme }) => theme.texto_secundario};
    font-size: 0.8em;
`;

export const Note = styled.span`
    color: ${({ $alerta, theme }) => ($alerta ? '#e53935' : theme.texto_secundario)};
    font-size: 0.8em;
    word-break: break-word;
`;

export const ItemActions = styled.div`
    display: flex;
    gap: 6px;
`;

// botão com fundo e borda suaves na cor da ação (como as ações das listas do painel)
export const Action = styled.button`
    display: flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    border: solid 1px ${({ $tom }) => `${$tom}66`};
    border-radius: 8px;
    background-color: ${({ $tom }) => `${$tom}1f`};
    color: ${({ $tom }) => $tom};
    cursor: pointer;

    &:hover {
        background-color: ${({ $tom }) => $tom};
        color: #fff;
    }
`;
