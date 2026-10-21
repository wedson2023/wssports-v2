import styled from 'styled-components';

export const Container = styled.nav`
    display: flex;
    justify-content: center;
    gap: 6px;
    padding: 12px 0 4px;
`;

export const Button = styled.button`
    display: flex;
    align-items: center;
    justify-content: center;
    min-width: 34px;
    height: 34px;
    padding: 0 8px;
    border: solid 1px ${({ theme, $atual }) => ($atual ? theme.principal : theme.superficie_titulo)};
    border-radius: 4px;
    background-color: ${({ theme, $atual }) => ($atual ? theme.principal : 'transparent')};
    color: ${({ theme, $atual }) => ($atual ? '#fff' : theme.texto_principal)};
    font: inherit;
    cursor: pointer;

    &:disabled {
        opacity: 0.4;
        cursor: default;
    }

    i {
        font-size: 20px;
    }
`;
