import styled from 'styled-components';

// mesma tabela da lista de apostas (spec 018) e dos afiliados (spec 022)
export {
    Actions, Cell, Code, Detail, Details, DetailsRow, Empty, Expand, HeadCell, Retry, Row, Scroll, Skeleton, Sub, Table,
} from '../BetsTable/styles';

// cor do selo de cada situação, legível nos dois temas com texto branco
export const cores_situacao = {
    Aguardando: '#1565c0',
    Encerrado: '#2e7d32',
    Cancelado: '#757575',
    Ativa: '#1565c0',
    Desativada: '#757575',
    Anulada: '#6d4c41',
    Vencedora: '#2e7d32',
    Perdedora: '#c62828',
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

// categoria Aguardando com a data limite já passada: falta encerrar (FR-004)
export const Late = styled.span`
    display: inline-block;
    margin-left: 6px;
    padding: 2px 6px;
    border-radius: 0.3em;
    background-color: #ef6c00;
    color: #fff;
    font-size: 0.8em;
    white-space: nowrap;
`;
