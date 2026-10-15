import styled from 'styled-components';

export const Card = styled.article`
    background-color: ${({ theme }) => theme.superficie_titulo};
    border-radius: 10px;
    padding: 12px 14px;
    display: flex;
    flex-direction: column;
    gap: 10px;
    color: ${({ theme }) => theme.texto_principal};
`;

export const Meta = styled.div`
    display: flex;
    justify-content: space-between;
    gap: 8px;
    font-size: 11px;
    color: ${({ theme }) => theme.texto_apagado};

    span:last-child {
        flex-shrink: 0;
        font-variant-numeric: tabular-nums;
    }
`;

export const Header = styled.div`
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    padding-top: 8px;
    border-top: 1px solid ${({ theme }) => theme.superficie_barra};
`;

export const Title = styled.strong`
    font-size: 13px;
    font-weight: 500;
`;

export const Badge = styled.span`
    flex-shrink: 0;
    padding: 3px 8px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 500;
    color: #fff;
    background-color: ${({ $cor }) => $cor};
`;

export const List = styled.ul`
    display: flex;
    flex-direction: column;
    gap: 4px;
`;

export const Row = styled.li`
    display: flex;
    justify-content: space-between;
    gap: 8px;
    font-size: 12px;
    color: ${({ theme }) => theme.texto_secundario};
    text-decoration: ${({ $cancelado }) => ($cancelado ? 'line-through' : 'none')};
`;

export const Odd = styled.span`
    font-variant-numeric: tabular-nums;
    font-weight: 500;
    color: ${({ $cor, theme }) => $cor ?? theme.texto_principal};
`;
