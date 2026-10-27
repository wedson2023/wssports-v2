import styled from 'styled-components';

import { Input as InputBase } from '../CashModal';

// mesma gaveta do detalhe do cliente (spec 019) e da promoção (spec 020)
export {
    Actions, Body, Button, Container, Data, DataItem, Loading,
} from '../ClientDrawer/styles';
export { Title } from '../PromotionDrawer/styles';
export { Chip, Chips } from '../BetsFilters/styles';
export { Empty, Retry } from '../BetsTable/styles';
export { Money, ProfileLink } from '../AffiliatesTable/styles';

// faixa do topo: código, situação, regra e o valor a receber
export const Top = styled.div`
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    gap: 6px 12px;
    padding: 10px 16px;
    border-bottom: solid 1px ${({ theme }) => theme.superficie_titulo};
    color: ${({ theme }) => theme.texto_secundario};
    font-size: 0.85em;

    > span {
        display: inline-flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 8px;
    }
`;

// texto corrido ("Sobre você", soma do extrato)
export const Summary = styled.p`
    margin: 0;
    color: ${({ theme }) => theme.texto_principal};
    white-space: pre-line;
    overflow-wrap: anywhere;
`;

// filtros do extrato: situação e as datas
export const Filters = styled.div`
    display: flex;
    flex-wrap: wrap;
    align-items: flex-end;
    gap: 10px;
`;

export const Label = styled.label`
    display: flex;
    flex-direction: column;
    gap: 4px;
    color: ${({ theme }) => theme.texto_secundario};
    font-size: 0.8em;
`;

export const Input = styled(InputBase)`
    min-height: 40px;
    padding: 6px 10px;
`;

export const List = styled.ul`
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin: 0;
    padding: 0;
    list-style: none;
`;

// cada lançamento, cliente, prestação ou regra, em cartão
export const Entry = styled.li`
    display: flex;
    flex-direction: column;
    gap: 4px;
    padding: 10px 12px;
    border: solid 1px ${({ theme }) => theme.superficie_titulo};
    border-radius: 6px;
`;

export const EntryLine = styled.div`
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    gap: 4px 12px;
    font-size: 0.9em;

    > span {
        color: ${({ theme }) => theme.texto_secundario};
    }
`;
