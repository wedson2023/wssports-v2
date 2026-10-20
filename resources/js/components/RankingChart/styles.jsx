import styled from 'styled-components';

// Table, Tr e Td de screens/adm/dashboard/styles.js (Vendedores, Jogos e Opções mais apostados)
export const List = styled.div`
    display: flex;
    flex-direction: column;
`;

export const Row = styled.div`
    display: grid;
    grid-template-columns: 40px 1fr auto auto;
    align-items: center;
    gap: 10px;
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

export const Value = styled.span`
    text-align: right;
    white-space: nowrap;
`;
