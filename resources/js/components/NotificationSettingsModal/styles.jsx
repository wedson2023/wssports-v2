import styled from 'styled-components';

export const Device = styled.p`
    margin-bottom: 10px;
    font-size: 12px;
    color: #777;
`;

export const Options = styled.ul`
    display: flex;
    flex-direction: column;
    gap: 2px;
`;

export const Option = styled.li`
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 10px 0;
    border-bottom: 1px solid #eee;
    text-align: left;

    span {
        display: flex;
        flex-direction: column;
        gap: 2px;
    }

    strong {
        font-size: 14px;
        font-weight: 500;
        color: #333;
    }

    small {
        font-size: 12px;
        color: #777;
    }

    input {
        width: 20px;
        height: 20px;
        flex-shrink: 0;
        accent-color: ${({ theme }) => theme.principal};
        cursor: pointer;
    }
`;
