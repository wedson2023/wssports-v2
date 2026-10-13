import styled from 'styled-components';

export const Summary = styled.div`
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 8px;
    margin-bottom: 14px;

    div {
        display: flex;
        flex-direction: column;
        align-items: center;
        padding: 10px 6px;
        border-radius: 12px;
        background: #f6f7f9;
        text-align: center;
    }

    strong {
        font-size: 22px;
        color: ${({ theme }) => theme.principal};
    }

    span {
        font-size: 11px;
        color: #666;
    }
`;

export const List = styled.div`
    display: flex;
    flex-direction: column;
    gap: 8px;
`;

export const Item = styled.div`
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
    padding: 10px 14px;
    border: 1px solid #ececf0;
    border-radius: 10px;
`;

export const Name = styled.p`
    font-size: 14px;
    font-weight: 500;
    color: #222;
`;

export const When = styled.span`
    font-size: 12px;
    color: #888;
`;

export const Badge = styled.span`
    flex-shrink: 0;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 500;
    background: ${({ $valida }) => ($valida ? '#e7f6ec' : '#fff4e0')};
    color: ${({ $valida }) => ($valida ? '#1e7e34' : '#b26a00')};
`;

export const Empty = styled.p`
    padding: 20px 0;
    text-align: center;
    font-size: 13px;
    color: #888;
    display: flex;
    justify-content: center;
`;
