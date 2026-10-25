import styled from 'styled-components';
import { mobile } from '../../theme/tokens';

import { Fields as FieldsBase, Input } from '../CashModal';

export { Error, Field, Hint, Input, Label, Textarea } from '../CashModal';

// grade dos campos: duas colunas (padrão) ou uma só nos cartões estreitos, para os campos ficarem
// alinhados; no celular sempre uma
export const Fields = styled(FieldsBase)`
    grid-template-columns: repeat(${({ $colunas }) => $colunas}, minmax(0, 1fr));
    align-items: start;

    ${mobile} {
        grid-template-columns: minmax(0, 1fr);
    }
`;

// campo de seleção com a mesma borda, altura e cores dos outros campos
export const Select = styled(Input).attrs({ as: 'select' })`
    min-height: 42px;
    cursor: pointer;

    &:disabled {
        cursor: default;
    }
`;

// interruptor com o rótulo ao lado, na altura de um campo
export const SwitchRow = styled.div`
    display: flex;
    align-items: center;
    gap: 10px;
    min-height: 42px;

    span {
        color: ${({ theme }) => theme.texto_principal};
        font-size: 0.9em;
    }
`;

// opções de marcar várias (ex.: meios de depósito)
export const Chips = styled.div`
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
`;

export const Chip = styled.button`
    min-height: 36px;
    padding: 0 14px;
    border: solid 1px ${({ $marcado, theme }) => ($marcado ? theme.principal : (theme.modo === 'escuro' ? '#3a3a3a' : '#a8a8a8'))};
    border-radius: 18px;
    background-color: ${({ $marcado, theme }) => ($marcado ? theme.principal : 'transparent')};
    color: ${({ $marcado, theme }) => ($marcado ? '#fff' : theme.texto_principal)};
    font: inherit;
    font-size: 0.85em;
    cursor: pointer;

    &:disabled {
        opacity: 0.5;
        cursor: default;
    }
`;

// valor só de leitura (ex.: trava geral do ao vivo, endereço de retorno do cassino)
export const ReadOnly = styled.div`
    display: flex;
    align-items: center;
    min-height: 42px;
    padding: 0 10px;
    border-radius: 4px;
    background-color: ${({ theme }) => theme.superficie_barra};
    color: ${({ theme }) => theme.texto_secundario};
    font-size: 0.9em;
    word-break: break-all;
`;

export const Secret = styled.p`
    margin: 0;
    color: ${({ theme }) => theme.texto_secundario};
    font-size: 0.8em;

    ${mobile} {
        font-size: 0.78em;
    }
`;
