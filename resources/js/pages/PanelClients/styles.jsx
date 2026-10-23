import styled from 'styled-components';
import { mobile } from '../../theme/tokens';

// mesma base da tela Apostas (spec 018): seção, ferramentas, cartões, busca, abas, carregando e erro
export {
    Area, Cards, Error, FullTabs, HeaderActions, Loading, Retry, Search, SearchBox, Section, Skeleton, Tool,
} from '../PanelBets/styles';
export { Chip, Chips } from '../../components/BetsFilters/styles';
export { Input as DateInput } from '../../components/CashModal/styles';

// topo em grade (contracts/paginas §1): no computador, abas à esquerda e ferramentas à direita; o
// painel de filtros e as etiquetas na largura toda; embaixo, as escolhas da aba e a busca. No
// celular, uma coluna só
const areas_computador = ({ $com_filtros, $com_etiquetas }) => [
    '"abas . ferramentas"',
    $com_filtros && '"filtros filtros filtros"',
    $com_etiquetas && '"etiquetas etiquetas etiquetas"',
    '"escolhas . busca"',
].filter(Boolean).join(' ');

const areas_celular = ({ $com_etiquetas }) => ['"abas"', '"ferramentas"', $com_etiquetas && '"etiquetas"', '"busca"', '"escolhas"']
    .filter(Boolean)
    .join(' ');

export const Top = styled.div`
    display: grid;
    grid-template-columns: minmax(0, max-content) minmax(0, 1fr) minmax(0, max-content);
    grid-template-areas: ${areas_computador};
    align-items: start;
    gap: 14px 16px;

    ${mobile} {
        grid-template-columns: minmax(0, 1fr);
        grid-template-areas: ${areas_celular};
        gap: 12px;
    }
`;

// escolhas da aba (situação e período dos saques; período e critério do ranking), uma por linha
export const Choices = styled.div`
    display: flex;
    flex-direction: column;
    gap: 8px;

    &:empty {
        display: none;
    }
`;

// datas de/até das abas Saques e Ranking: rótulo e campo na mesma linha, com a mesma largura, e o
// "Todo o período" alinhado a eles; no celular, as duas datas dividem a linha
export const DateRow = styled.div`
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px 16px;

    ${mobile} {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 8px;

        > button {
            grid-column: 1 / -1;
            justify-self: start;
        }
    }
`;

export const DateField = styled.label`
    display: flex;
    align-items: center;
    gap: 8px;
    color: ${({ theme }) => theme.texto_secundario};
    font-size: 0.85em;
    white-space: nowrap;

    input {
        width: 170px;
        min-height: 36px;
        padding: 6px 10px;
    }

    ${mobile} {
        flex-direction: column;
        align-items: stretch;
        gap: 4px;

        input {
            width: 100%;
        }
    }
`;
