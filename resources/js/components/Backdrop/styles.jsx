import styled from 'styled-components';

// fundo escurecido de modais e da gaveta (Screen de main/styles.js)
export const Screen = styled.div`
    position: fixed;
    width: 100%;
    height: 100vh;
    height: 100dvh;
    z-index: ${({ $camada }) => $camada};
    background: rgba(0, 0, 0, 0.8);
    top: 0;
    left: 0;
    transition-duration: 0.5s;
    opacity: ${({ $visivel }) => ($visivel ? 1 : 0)};
    visibility: ${({ $visivel }) => ($visivel ? 'visible' : 'hidden')};
`;
