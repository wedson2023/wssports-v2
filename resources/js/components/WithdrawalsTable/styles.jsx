import styled from 'styled-components';

// mesma tabela da lista de apostas (spec 018)
export {
    Actions, Cell, Code, Detail, Details, DetailsRow, Empty, Expand, HeadCell, Retry, Row, Scroll, Skeleton, Sub, Table,
} from '../BetsTable/styles';

// cor do selo de cada situação do saque, legível nos dois temas com texto branco
const cores_situacao = {
    Pendente: '#ef6c00',
    Processando: '#1565c0',
    Pago: '#2e7d32',
    Recusado: '#c62828',
};

export const Status = styled.span`
    display: inline-block;
    padding: 4px 8px;
    border-radius: 0.3em;
    background-color: ${({ $situacao }) => cores_situacao[$situacao] ?? '#757575'};
    color: #fff;
    font-size: 0.85em;
    white-space: nowrap;
`;
