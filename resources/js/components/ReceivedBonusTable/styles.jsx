import styled from 'styled-components';

// mesma tabela da lista de apostas (spec 018)
export {
    Actions, Cell, Code, Detail, Details, DetailsRow, Empty, Expand, HeadCell, Retry, Row, Scroll, Skeleton, Sub, Table,
} from '../BetsTable/styles';

// cor do selo de cada situação do bônus, legível nos dois temas com texto branco
const cores_situacao = {
    'Em andamento': '#1565c0',
    Cumprido: '#2e7d32',
    Convertido: '#1b5e20',
    Expirado: '#757575',
    'Excluído pelo cliente': '#455a64',
    Estornado: '#c62828',
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

// barra do rollover e o "falta" embaixo
export const Bar = styled.div`
    display: flex;
    flex-direction: column;
    gap: 2px;
    margin-top: 4px;
`;

export const Progress = styled.progress`
    width: 100%;
    min-width: 90px;
    height: 6px;
    accent-color: ${({ theme }) => theme.principal};
`;
