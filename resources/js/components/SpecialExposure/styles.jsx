import styled from 'styled-components';

import { Entry } from '../AffiliateDrawer/styles';

// mesma lista em cartões do ranking do jogo (spec 023) e os selos da lista de especiais
export {
    Empty, Entry, EntryLine, List, Loading, Retry, Summary,
} from '../AffiliateDrawer/styles';
export { Status } from '../SpecialsTable/styles';

// opção com o maior possível retorno da categoria (FR-021)
export const Highlight = styled(Entry)`
    border-color: ${({ theme }) => theme.principal};
    border-width: 2px;
`;

export const VoidButton = styled.button`
    display: inline-flex;
    align-items: center;
    gap: 4px;
    min-height: 40px;
    padding: 0 10px;
    border: solid 1px #6d4c41;
    border-radius: 4px;
    background: transparent;
    color: inherit;
    font: inherit;
    font-size: 0.85em;
    cursor: pointer;

    i {
        font-size: 18px;
    }
`;
