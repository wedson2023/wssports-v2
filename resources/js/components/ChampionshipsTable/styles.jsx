import styled from 'styled-components';

// mesma tabela e selos da lista de jogos (spec 023)
export {
    Actions, Cell, Code, Detail, Details, DetailsRow, Empty, Expand, HeadCell, Retry, Row, Scroll, Skeleton, Status, Statuses, Sub, Table, Tag,
} from '../MatchesTable/styles';

export const Star = styled.i`
    color: ${({ $marcada, theme }) => ($marcada ? '#f9a825' : theme.texto_apagado)};
    font-size: 20px;
    vertical-align: middle;
`;
