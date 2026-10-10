import styled from 'styled-components';

// Container de main/styles.js: ocupa a altura visível da tela
export const Container = styled.div`
    height: 100vh;
    height: 100dvh;
    background-color: ${({ theme }) => theme.fundo_pagina};
`;
