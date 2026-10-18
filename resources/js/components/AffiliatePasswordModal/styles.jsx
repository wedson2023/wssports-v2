import styled from 'styled-components';

// mesmos campos do "Meus dados" do cliente (ProfileModal), no visual novo dos modais (ModalCard)
export { ErrorText, Field, Hint } from '../ProfileModal/styles';

export const Fields = styled.div`
    display: flex;
    flex-direction: column;
    gap: 12px;
`;

export const PasswordBox = styled.div`
    display: flex;
    align-items: center;
    height: 44px;
    border: 2px solid ${({ $erro }) => ($erro ? '#d32f2f' : '#e1e3e8')};
    border-radius: 10px;
    background: #fff;

    &:focus-within {
        border-color: ${({ theme }) => theme.principal};
    }

    input {
        flex: 1;
        min-width: 0;
        height: 100%;
        padding: 0 12px;
        border: none;
        background: transparent;
        font-size: 14px;
        color: #222;
        outline: none;
    }

    button {
        display: flex;
        padding: 0 10px;
        border: none;
        background: transparent;
        color: #999;
        cursor: pointer;
    }
`;
