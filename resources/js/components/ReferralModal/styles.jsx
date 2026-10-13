import styled from 'styled-components';

export const Center = styled.div`
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
    text-align: center;
`;

export const Text = styled.p`
    font-size: ${({ $pequeno }) => ($pequeno ? '12px' : '14px')};
    color: ${({ $pequeno }) => ($pequeno ? '#888' : '#444')};
`;

export const Big = styled.strong`
    font-size: 48px;
    line-height: 1;
    color: ${({ theme }) => theme.principal};
`;

export const Progress = styled.div`
    width: 100%;
    height: 8px;
    margin: 8px 0;
    border-radius: 4px;
    background: linear-gradient(
        to right,
        ${({ theme }) => theme.principal} ${({ $porcentagem }) => $porcentagem}%,
        #e6e7ea ${({ $porcentagem }) => $porcentagem}%
    );
`;

export const Link = styled.button`
    display: flex;
    align-items: center;
    gap: 8px;
    width: 100%;
    margin-top: 10px;
    padding: 10px 12px;
    border: 1px dashed #c5c5c5;
    border-radius: 10px;
    background: #f6f7f9;
    color: #444;
    font-size: 12px;
    cursor: pointer;

    span {
        flex: 1;
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        text-align: left;
    }

    i {
        font-size: 18px;
        color: ${({ theme }) => theme.principal};
    }
`;

export const Copied = styled.span`
    font-size: 12px;
    color: #28a745;
`;
