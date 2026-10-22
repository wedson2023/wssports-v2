import styled from 'styled-components';

// borda dos campos: no tema claro um cinza mais forte, para os campos aparecerem no fundo branco
const borda_campo = ({ theme }) => (theme.modo === 'escuro' ? '#3a3a3a' : '#a8a8a8');

// painel de filtros do computador, abaixo do cabeçalho
export const Panel = styled.section`
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    align-items: start;
    gap: 16px 20px;
    padding: 14px;
    border: solid 1px ${borda_campo};
    border-radius: 8px;

    > button:last-child {
        justify-self: start;
    }
`;

export const Group = styled.div`
    display: flex;
    flex-direction: column;
    gap: 6px;
`;

export const GroupTitle = styled.span`
    color: ${({ theme }) => theme.texto_secundario};
    font-size: 0.85em;
`;

export const Chips = styled.div`
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
`;

// chip de marcar: o marcado fica na cor principal
export const Chip = styled.button`
    min-height: 36px;
    padding: 0 12px;
    border: solid 1px ${borda_campo};
    border-radius: 18px;
    background: transparent;
    color: ${({ theme }) => theme.texto_principal};
    font: inherit;
    font-size: 0.85em;
    cursor: pointer;

    &[aria-pressed='true'] {
        border-color: ${({ theme }) => theme.principal};
        background-color: ${({ theme }) => theme.principal};
        color: #fff;
    }
`;
