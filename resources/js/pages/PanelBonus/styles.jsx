import styled from 'styled-components';
import { mobile } from '../../theme/tokens';

import { Chips as ChipsBase, SearchBox as SearchBoxBase } from '../PanelClients/styles';

// mesma base da tela Clientes (spec 019): seção, ferramentas, cartões, busca, chips, carregando e erro
export {
    Area, Cards, Chip, Choices, Error, HeaderActions, Loading, Retry, Search, Section, Skeleton, Tool,
} from '../PanelClients/styles';

// topo em grade: no computador, abas à esquerda e ferramentas à direita; o painel de filtros e as
// etiquetas na largura toda; embaixo, os chips de situação (que não alargam a coluna das abas) e a
// busca. No celular, uma coluna só
const areas_computador = ({ $com_filtros, $com_etiquetas }) => [
    '"abas . ferramentas"',
    $com_filtros && '"filtros filtros filtros"',
    $com_etiquetas && '"etiquetas etiquetas etiquetas"',
    '"escolhas escolhas busca"',
].filter(Boolean).join(' ');

const areas_celular = ({ $com_etiquetas }) => ['"abas"', '"ferramentas"', $com_etiquetas && '"etiquetas"', '"busca"', '"escolhas"']
    .filter(Boolean)
    .join(' ');

export const Top = styled.div`
    display: grid;
    grid-template-columns: max-content minmax(0, 1fr) max-content;
    grid-template-areas: ${areas_computador};
    align-items: center;
    gap: 14px 16px;

    ${mobile} {
        grid-template-columns: minmax(0, 1fr);
        grid-template-areas: ${areas_celular};
        gap: 12px;
    }
`;

// abas do tamanho do texto, sem quebrar o rótulo ("Bônus recebidos" numa linha só); no celular,
// dividem a largura toda
export const Tabs = styled.div`
    [role='tab'] {
        justify-content: center;
        white-space: nowrap;
    }

    ${mobile} {
        [role='tab'] {
            flex: 1 1 0;
            padding: 10px 6px;
        }
    }
`;

// busca com largura própria no computador; no celular, a linha toda
export const SearchBox = styled(SearchBoxBase)`
    flex: none;
    width: 300px;
    min-width: 0;

    ${mobile} {
        width: 100%;
    }
`;

// situações do bônus: no celular, numa linha só com rolagem para o lado
export const Chips = styled(ChipsBase)`
    ${mobile} {
        flex-wrap: nowrap;
        overflow-x: auto;
        scrollbar-width: none;

        &::-webkit-scrollbar {
            display: none;
        }

        > button {
            flex-shrink: 0;
            white-space: nowrap;
        }
    }
`;
