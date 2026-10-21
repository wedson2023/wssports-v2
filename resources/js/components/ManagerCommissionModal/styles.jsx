import styled from 'styled-components';

// campo com o sufixo % dentro dele
export const Wrapper = styled.div`
    position: relative;

    input {
        padding-right: 32px;
    }
`;

export const Suffix = styled.span`
    position: absolute;
    top: 50%;
    right: 12px;
    color: ${({ theme }) => theme.texto_secundario};
    transform: translateY(-50%);
    pointer-events: none;
`;
