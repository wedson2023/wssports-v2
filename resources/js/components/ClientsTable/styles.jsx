import styled from 'styled-components';

// mesma tabela da lista de apostas (spec 018): linha clicável, colunas secundárias que somem no
// celular, detalhes ao expandir, vazio, erro e esqueleto
export {
    Actions, Cell, Code, Detail, Details, DetailsRow, Empty, Expand, HeadCell, Retry, Row, Scroll, Skeleton, Sub, Table, Tag,
} from '../BetsTable/styles';

// valor colorido pelo sinal (saldo líquido, resultado): verde positivo, cinza zero, vermelho negativo
export const Money = styled.span`
    color: ${({ $sinal }) => ({ 1: '#2e7d32', 0: '#9e9e9e', [-1]: '#c62828' }[$sinal] ?? 'inherit')};
    font-weight: bold;
`;

// título de coluna que ordena a lista
export const Sort = styled.button`
    display: inline-flex;
    align-items: center;
    gap: 2px;
    padding: 0;
    border: none;
    background: transparent;
    color: ${({ $ativa, theme }) => ($ativa ? theme.principal : 'inherit')};
    font: inherit;
    text-transform: inherit;
    cursor: pointer;

    i {
        font-size: 14px;
    }
`;
