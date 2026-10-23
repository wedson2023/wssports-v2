import styled from 'styled-components';

// chips de escolha dos filtros e contador do lançamento do caixa
export { Chip, Chips } from '../BetsFilters/styles';
export { Counter } from '../CashEntryModal/styles';

// envios restantes hoje e, para o Admin, o limite do dia
export const LimitRow = styled.div`
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px;
    color: ${({ theme }) => theme.texto_secundario};
    font-size: 0.9em;

    > span {
        flex: 1;
        min-width: 160px;
    }

    > input {
        width: 80px;
    }
`;

// prévia da notificação como aparece no celular
export const Preview = styled.div`
    display: flex;
    gap: 10px;
    padding: 12px;
    border-radius: 10px;
    background-color: ${({ theme }) => theme.superficie_barra};

    i {
        color: ${({ theme }) => theme.principal};
    }

    div {
        display: flex;
        flex-direction: column;
        gap: 2px;
        min-width: 0;
        overflow-wrap: anywhere;
    }

    span {
        color: ${({ theme }) => theme.texto_secundario};
        font-size: 0.9em;
    }
`;

export const Reach = styled.p`
    margin: 0;
    color: ${({ theme }) => theme.texto_principal};
    font-weight: bold;
`;
