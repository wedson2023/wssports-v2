import styled from 'styled-components';

export const Icon = styled.i`
    display: block;
    text-align: center;
    font-size: 48px;
    color: ${({ theme }) => theme.principal};
    margin: 4px auto 12px;
`;

export const Message = styled.p`
    text-align: center;
    font-size: 15px;
    line-height: 1.5;
    color: #444;
    white-space: pre-line;
`;
