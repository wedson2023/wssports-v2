import styled from 'styled-components';

// carregando os pendentes, dentro do corpo do modal
export const Loading = styled.div`
    display: flex;
    justify-content: center;
    padding: 24px 0;
    color: ${({ theme }) => theme.principal};
`;
