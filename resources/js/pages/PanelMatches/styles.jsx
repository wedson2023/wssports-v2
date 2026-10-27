import styled from 'styled-components';

// mesma base da tela Bônus (spec 020): seção, topo em grade, abas, ferramentas, cartões, busca,
// chips, carregando e erro
export {
    Area, Cards, Chip, Chips, Choices, Error, HeaderActions, Loading, Retry, Search, SearchBox, Section, Skeleton, Tabs, Tool, Top,
} from '../PanelBonus/styles';

// aviso da atualização automática que falhou, sem apagar a lista
export const Notice = styled.p`
    margin: 0;
    padding: 8px 12px;
    border-radius: 4px;
    background-color: #fff3e0;
    color: #e65100;
    font-size: 0.85em;
`;
