import styled from 'styled-components';

export const Empty = styled.div`
    display: flex;
    justify-content: center;
    padding: 40px 10px;
    color: ${({ theme }) => theme.texto_apagado};
    text-align: center;
`;
