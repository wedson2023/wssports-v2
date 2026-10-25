import styled from 'styled-components';

import { Input } from '../CashModal';

// campo de seleção com a mesma borda, altura e cores dos outros campos do modal
export const Select = styled(Input).attrs({ as: 'select' })`
    min-height: 42px;
    cursor: pointer;

    &:disabled {
        cursor: default;
    }
`;

// cada grupo de campos do cadastro (Identificação, Ganho, Período...)
export const Group = styled.section`
    display: flex;
    flex-direction: column;
    gap: 12px;

    & + & {
        margin-top: 20px;
    }
`;

export const Title = styled.h3`
    margin: 0;
    padding-bottom: 6px;
    border-bottom: solid 1px ${({ theme }) => theme.superficie_titulo};
    color: ${({ theme }) => theme.texto_secundario};
    font-size: 0.85em;
    font-weight: 500;
    text-transform: uppercase;
`;
