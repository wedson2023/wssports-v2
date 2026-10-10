import styled from 'styled-components';

// Message de main/styles.js
export const Message = styled.p`
    display: block;
    text-align: center;
    margin: 35px auto;
    color: ${({ theme }) => theme.texto_apagado};
    font-size: 0.9em;
    user-select: none;
`;
