import styled from 'styled-components';

// mesma tabela da lista de apostas (spec 018) e o valor colorido da lista de clientes
export { Cell, Code, Empty, HeadCell, Retry, Row, Scroll, Skeleton, Sub, Table, Tag } from '../BetsTable/styles';
export { Money } from '../ClientsTable/styles';

// posição no ranking; do 1º ao 3º, em destaque na cor do tema
export const Position = styled.span`
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 34px;
    height: 28px;
    padding: 0 6px;
    border-radius: 14px;
    background-color: ${({ $destaque, theme }) => ($destaque ? theme.principal : theme.superficie_titulo)};
    color: ${({ $destaque, theme }) => ($destaque ? '#fff' : theme.texto_principal)};
    font-weight: bold;
`;
