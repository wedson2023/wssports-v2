import styled from 'styled-components';

// Tr e Td de screens/adm/dashboard/styles.js (lista de "Apostas por um jogo")
export const Row = styled.div`
    display: grid;
    grid-template-columns: 40px 1fr 1fr 32px;
    align-items: center;
    gap: 8px;
    padding: 6px 0;
    border-bottom: solid 1px ${({ theme }) => theme.superficie_titulo};
    font-size: 0.85em;
`;

export const Index = styled.span`
    color: ${({ theme }) => theme.principal};
    font-weight: 500;
    text-align: center;
`;

export const Name = styled.span`
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
`;

export const Prize = styled.div`
    display: flex;
    flex-direction: column;
    gap: 4px;
`;

export const Bar = styled.div`
    width: 100%;
    height: 6px;
    border-radius: 3px;
    background-color: ${({ theme }) => theme.superficie_titulo};
`;

export const BarFill = styled.div`
    height: 100%;
    border-radius: 3px;
    background-color: ${({ theme }) => theme.principal};
`;

export const Eye = styled.i.attrs(() => ({ className: 'material-icons', role: 'button' }))`
    color: ${({ theme }) => theme.principal};
    cursor: pointer;
`;
