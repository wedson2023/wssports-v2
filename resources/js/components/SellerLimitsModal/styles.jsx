import styled from 'styled-components';

// limite atual e a prévia do novo valor, acima do campo
export const Current = styled.div`
    display: flex;
    justify-content: space-between;
    gap: 10px;
    color: ${({ theme }) => theme.texto_secundario};
    font-size: 0.8em;
`;

export const Preview = styled.span`
    color: ${({ $negativo, theme }) => ($negativo ? '#e53935' : theme.principal)};
    font-weight: bold;
`;
