import styled from 'styled-components';

// campo de seleção igual ao dos outros modais do painel
export { Select } from '../PromotionFormModal/styles';

export const Options = styled.div`
    display: flex;
    flex-direction: column;
    gap: 8px;
`;

// cada opção da categoria: a marcada como vencedora com a borda na cor do tema
export const Option = styled.div`
    display: flex;
    flex-direction: column;
    gap: 6px;
    padding: 8px 12px;
    border: solid 1px ${({ $marcada, theme }) => ($marcada ? theme.principal : theme.superficie_titulo)};
    border-radius: 6px;
    opacity: ${({ $anulada }) => ($anulada ? 0.6 : 1)};
`;

export const Check = styled.label`
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 10px;
    min-height: 40px;
    padding-left: ${({ $secundario }) => ($secundario ? '28px' : '0')};
    font-size: ${({ $secundario }) => ($secundario ? '0.9em' : '1em')};
    cursor: pointer;

    input {
        width: 18px;
        height: 18px;
        accent-color: ${({ theme }) => theme.principal};
    }

    small {
        margin-left: auto;
        color: ${({ theme }) => theme.texto_secundario};
    }
`;

export const Preview = styled.span`
    margin-left: auto;
    color: ${({ theme }) => theme.principal};
    font-weight: 500;
`;
