import styled from 'styled-components';

// RowTicket e BtnValue de main/styles.js
export const Row = styled.div`
    display: flex;
    justify-content: space-between;
    margin-bottom: 8px;
`;

export const Button = styled.button`
    flex: 1;
    border: none;
    cursor: pointer;
    height: 35px;
    outline: none;
    font-size: 12px;
    font-weight: bold;
    margin-right: 1px;
    color: #fff;
    background-color: ${({ theme }) => theme.principal};
`;
