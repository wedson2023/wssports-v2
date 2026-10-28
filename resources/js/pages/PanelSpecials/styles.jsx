import styled from 'styled-components';

// mesma base da tela Afiliados (spec 022) e Bônus (spec 020): seção, topo em grade, abas,
// ferramentas, cartões, busca, carregando e erro
export {
    Area, Cards, Error, HeaderActions, Loading, Retry, Search, SearchBox, Section, Skeleton, Tabs, Tool, Top,
} from '../PanelBonus/styles';

// aviso de que os números contam só a rede de quem consulta (FR-002)
export const Legend = styled.p`
    margin: -6px 0 0;
    color: ${({ theme }) => theme.texto_secundario};
    font-size: 0.8em;
`;
