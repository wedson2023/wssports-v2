import styled from 'styled-components';

// Loading de main/styles.js
export const Container = styled.div`
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    position: absolute;
    height: 100vh;
    height: 100dvh;
    width: 100%;
    z-index: 100;
    background-color: ${({ theme }) => theme.fundo_pagina};
`;

export const Text = styled.p`
    color: ${({ theme }) => theme.texto_apagado};
`;
