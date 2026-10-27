import styled from 'styled-components';

// mesma tabela da lista de apostas (spec 018) e dos afiliados (spec 022)
export {
    Actions, Cell, Code, Detail, Details, DetailsRow, Empty, Expand, HeadCell, Retry, Row, Scroll, Skeleton, Sub, Table,
} from '../BetsTable/styles';
export { Money } from '../AffiliatesTable/styles';

// cor do selo de cada situação do jogo, legível nos dois temas com texto branco
const cores_situacao = {
    Ativo: '#2e7d32',
    Desativado: '#757575',
    'Desativado para a minha rede': '#ef6c00',
    'Campeonato desativado': '#757575',
    Encerrado: '#1565c0',
    Cancelado: '#c62828',
    Adiado: '#6a1b9a',
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

export const Statuses = styled.span`
    display: inline-flex;
    flex-wrap: wrap;
    gap: 4px;
`;

// selo "Ao vivo 63' 1–0" e marca "Manual" embaixo do jogo
export const Tag = styled.span`
    display: inline-flex;
    align-items: center;
    gap: 2px;
    padding: 1px 6px;
    border-radius: 0.3em;
    background-color: ${({ $ao_vivo }) => ($ao_vivo ? '#c62828' : '#546e7a')};
    color: #fff;
    font-size: 0.8em;
    font-weight: 500;
    white-space: nowrap;
`;

export const Legend = styled.p`
    margin: 8px 0 0;
    color: ${({ theme }) => theme.texto_secundario};
    font-size: 0.85em;
`;
