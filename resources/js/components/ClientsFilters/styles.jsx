import styled from 'styled-components';

// mesmo painel, chips e limpar dos filtros da lista de apostas (spec 018)
export { Chip, Chips, ClearButton, Group, GroupTitle, Panel } from '../BetsFilters/styles';
export { Select } from '../BettingRulesFields/styles';

// dois campos lado a lado (de/até, mínimo/máximo)
export const Row = styled.div`
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 8px;
`;

// Limpar e Aplicar no fim do painel do computador
export const Actions = styled.div`
    grid-column: 1 / -1;
    display: flex;
    justify-content: flex-end;
    gap: 10px;
`;
