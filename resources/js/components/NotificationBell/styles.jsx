import styled from 'styled-components';

// o mesmo tamanho dos ícones do cabeçalho da Minha Conta
export const Bell = styled.button`
    width: 45px;
    height: 45px;
    border: 0;
    background: none;
    cursor: pointer;
    font-size: 25px;
    color: ${({ $ativo, theme }) => ($ativo ? theme.principal : theme.texto_secundario)};

    &:disabled {
        opacity: 0.5;
        cursor: default;
    }

    &:focus-visible {
        outline: 2px solid ${({ theme }) => theme.principal};
        outline-offset: 2px;
    }
`;
