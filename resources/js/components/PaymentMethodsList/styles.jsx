import styled from 'styled-components';

export { Select } from '../BettingRulesFields/styles';
export { Tag } from '../BetsTable/styles';

export const Loading = styled.div`
    display: flex;
    justify-content: center;
    padding: 24px 0;
    color: ${({ theme }) => theme.principal};
`;

export const Empty = styled.p`
    color: ${({ theme }) => theme.texto_apagado};
    font-size: 0.9em;
`;

export const Items = styled.ul`
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin: 0 0 12px;
    padding: 0;
    list-style: none;
`;

// uma chave Pix: dados à esquerda, etiqueta e botões à direita
export const Item = styled.li`
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px;
    padding: 10px 12px;
    border: solid 1px ${({ theme }) => theme.superficie_titulo};
    border-radius: 6px;

    > div:first-child {
        display: flex;
        flex: 1;
        flex-direction: column;
        min-width: 0;
        gap: 2px;
        color: ${({ theme }) => theme.texto_principal};
        font-size: 0.9em;
        overflow-wrap: anywhere;

        span {
            color: ${({ theme }) => theme.texto_secundario};
            font-size: 0.9em;
        }
    }
`;

export const Actions = styled.div`
    display: flex;
    justify-content: flex-end;
    gap: 8px;
`;

export const Form = styled.div`
    display: flex;
    flex-direction: column;
    gap: 12px;
    padding-top: 12px;
    border-top: solid 1px ${({ theme }) => theme.superficie_titulo};
`;
