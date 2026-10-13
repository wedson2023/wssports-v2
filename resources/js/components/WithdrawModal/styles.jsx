import styled from 'styled-components';

export {
    ErrorText, Field, Input, Label, Prefix, Success,
} from '../DepositModal/styles';

export const Available = styled.div`
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 14px;
    margin-bottom: 16px;
    border-radius: 12px;
    background: #f6f7f9;
    font-size: 13px;
    color: #555;

    strong {
        font-size: 18px;
        color: #222;
    }
`;

export const Key = styled.div`
    display: flex;
    align-items: center;
    gap: 12px;
    min-height: 56px;
    padding: 10px 14px;
    border: 1px solid #e1e3e8;
    border-radius: 12px;
    font-size: 13px;
    color: #555;

    i {
        font-size: 28px;
        color: ${({ theme }) => theme.principal};
    }

    div {
        display: flex;
        flex-direction: column;
        gap: 2px;
        min-width: 0;
    }

    strong {
        color: #222;
        word-break: break-all;
    }
`;

export const Summary = styled.div`
    margin-top: 16px;
    padding-top: 10px;
    border-top: 1px dashed #ddd;
`;

export const Row = styled.div`
    display: flex;
    justify-content: space-between;
    padding: 4px 0;
    font-size: ${({ $total }) => ($total ? '15px' : '13px')};
    font-weight: ${({ $total }) => ($total ? 600 : 400)};
    color: ${({ $total }) => ($total ? '#222' : '#666')};
`;
