import styled from 'styled-components';

// RowTicket, LabelClear, TextClear, LabelFinish, TextFinish e QtdeHunches de main/styles.js
export const Row = styled.div`
    display: flex;
    justify-content: space-between;
    margin-bottom: 8px;
`;

export const ClearButton = styled.label`
    width: 50%;
    margin-right: 10px;
    background: ${({ theme }) => theme.superficie_campeonato};
    height: 37px;
    padding-right: 10px;
    display: flex;
    justify-content: space-around;
    align-items: center;
    cursor: pointer;
    transition-duration: 0.3s;

    &:hover {
        opacity: 0.8;
    }
`;

export const ClearText = styled.span`
    font-weight: 400;
    color: #fff;
    font-size: 14px;
`;

// verde no "Validar", como o LabelFinish do antigo
export const FinishButton = styled.button`
    width: ${({ $largura }) => $largura};
    background-color: ${({ $validar, theme }) => ($validar ? 'green' : theme.principal)};
    outline: none;
    border: none;
    height: 37px;
    display: flex;
    justify-content: space-around;
    align-items: center;
    cursor: pointer;
    transition-duration: 0.3s;

    &:hover {
        opacity: 0.8;
    }

    &:disabled {
        cursor: default;
    }
`;

export const FinishText = styled.span`
    font-weight: 400;
    color: #fff;
    font-size: 14px;
    flex: 1;
    padding-left: 10px;
    padding-right: 10px;
`;

export const Count = styled.strong`
    color: #fff;
    display: flex;
    justify-content: center;
    align-items: center;
    background: #666;
    height: 37px;
    width: 37px;
`;

export const SpinnerArea = styled.span`
    flex: 1;
    display: flex;
    justify-content: center;
`;

export const Icon = styled.i.attrs(() => ({ className: 'material-icons' }))`
    user-select: none;
    color: #fff;
    font-size: 25px;
`;
