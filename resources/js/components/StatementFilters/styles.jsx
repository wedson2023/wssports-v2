import styled from 'styled-components';

// ContainerFilter e Select de screens/credits/styles.js
export const Container = styled.div`
    display: flex;
    margin: 10px auto;
    justify-content: space-between;
    align-items: center;
    width: 90%;

    i {
        color: #999;
    }
`;

export const Select = styled.select`
    border: solid 3px #666;
    border-radius: 0.5em;
    padding: 10px;
    background-color: ${({ theme }) => theme.fundo_pagina};
    flex: 1;
    min-width: 0;
    margin-left: 15px;
    color: #999;

    option {
        color: ${({ theme }) => theme.texto_principal};
        background-color: ${({ theme }) => theme.fundo_pagina};
    }

    &:disabled {
        opacity: 0.6;
    }
`;
