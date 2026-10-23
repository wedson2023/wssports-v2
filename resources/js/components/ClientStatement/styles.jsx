import styled from 'styled-components';
import { mobile } from '../../theme/tokens';

export { Money } from '../ClientsTable/styles';
export { Input } from '../CashModal/styles';
export { Select } from '../BettingRulesFields/styles';

export const Filters = styled.div`
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 8px;

    ${mobile} {
        grid-template-columns: repeat(2, minmax(0, 1fr));

        > select {
            grid-column: 1 / -1;
        }
    }
`;

export const Empty = styled.p`
    color: ${({ theme }) => theme.texto_apagado};
    font-size: 0.9em;
`;

export const Statement = styled.ul`
    display: flex;
    flex-direction: column;
    margin: 0;
    padding: 0;
    list-style: none;
`;

// uma transação: origem, data, carteira e autor à esquerda; valor e saldo depois à direita
export const StatementRow = styled.li`
    display: flex;
    justify-content: space-between;
    gap: 12px;
    padding: 10px 0;
    border-bottom: solid 1px ${({ theme }) => theme.superficie_titulo};
    font-size: 0.9em;

    > div {
        display: flex;
        flex-direction: column;
        gap: 2px;
        min-width: 0;

        span {
            color: ${({ theme }) => theme.texto_secundario};
            font-size: 0.85em;
            overflow-wrap: anywhere;
        }
    }

    > div:last-child {
        flex-shrink: 0;
        align-items: flex-end;
        text-align: right;
    }
`;

export const Loading = styled.div`
    display: flex;
    justify-content: center;
    padding: 16px 0;
    color: ${({ theme }) => theme.principal};
`;

export const More = styled.button`
    align-self: center;
    padding: 8px 14px;
    border: solid 2px ${({ theme }) => theme.principal};
    border-radius: 4px;
    background: transparent;
    color: ${({ theme }) => theme.principal};
    font-weight: bold;
    cursor: pointer;
`;
