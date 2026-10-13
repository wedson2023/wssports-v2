import styled from 'styled-components';

export { ErrorText, Notice } from '../DepositModal/styles';

export const Group = styled.div`
    flex: 1;
    min-width: 0;
    margin-bottom: 12px;
`;

export const Row = styled.div`
    display: flex;
    gap: 12px;
`;

export const Label = styled.label`
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 6px;
    font-size: 13px;
    font-weight: 500;
    color: #444;
`;

const campo = `
    width: 100%;
    height: 46px;
    padding: 0 12px;
    border: 2px solid #e1e3e8;
    border-radius: 10px;
    background: #fff;
    font-size: 15px;
    color: #222;
    outline: none;
    transition: border-color 0.15s;
`;

// o MercadoPago.js monta o iframe dentro desta caixa
export const SecureField = styled.div`
    ${campo}
    display: flex;
    align-items: center;

    iframe {
        width: 100%;
        height: 100%;
        border: none;
    }

    &:focus-within {
        border-color: ${({ theme }) => theme.principal};
    }
`;

export const Brand = styled.img`
    height: 18px;
`;

export const Input = styled.input`
    ${campo}

    &:focus {
        border-color: ${({ theme }) => theme.principal};
    }
`;

export const Select = styled.select`
    ${campo}

    &:focus {
        border-color: ${({ theme }) => theme.principal};
    }
`;

export const Total = styled.div`
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 2px;
    margin: 6px 0 14px;
    padding: 12px;
    border-radius: 12px;
    background: #f6f7f9;
    font-size: 13px;
    color: #555;

    strong {
        font-size: 20px;
        color: #222;
    }

    small {
        font-size: 11px;
        color: #888;
        text-align: center;
    }
`;
