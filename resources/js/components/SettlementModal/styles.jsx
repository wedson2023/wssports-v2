import styled from 'styled-components';

export const Loading = styled.div`
    display: flex;
    justify-content: center;
    padding: 20px;
    color: ${({ theme }) => theme.texto_secundario};
`;

// vendedores do lote com o valor apurado de cada um
export const Sellers = styled.ul`
    display: flex;
    flex-direction: column;
    margin: 0;
    padding: 0;
    border: solid 1px ${({ theme }) => theme.superficie_titulo};
    border-radius: 6px;
    list-style: none;
    overflow: hidden;
`;

export const Seller = styled.li`
    display: flex;
    justify-content: space-between;
    gap: 10px;
    padding: 8px 12px;
    font-size: 0.85em;
    opacity: ${({ $apagado }) => ($apagado ? 0.55 : 1)};

    & + & {
        border-top: solid 1px ${({ theme }) => theme.superficie_titulo};
    }

    span {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
`;

export const Amount = styled.strong`
    flex-shrink: 0;
    color: ${({ $cor, theme }) => $cor ?? theme.texto_principal};
`;
