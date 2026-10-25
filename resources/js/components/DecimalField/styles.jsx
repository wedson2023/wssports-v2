import styled from 'styled-components';

export { Input } from '../CashModal';

// campo com prefixo (R$) ou sufixo (%) por dentro da borda
export const Affix = styled.div`
    position: relative;
    display: flex;
    align-items: center;

    input {
        padding-${({ $sufixo }) => ($sufixo ? 'right' : 'left')}: 38px;
    }

    span {
        position: absolute;
        ${({ $sufixo }) => ($sufixo ? 'right' : 'left')}: 10px;
        color: ${({ theme }) => theme.texto_apagado};
        font-size: 0.85em;
        pointer-events: none;
    }
`;
