import styled from 'styled-components';

export const Container = styled.div`
    background-color: #222;
    min-height: 100vh;
`;

export const Loading = styled.div`
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    position: absolute;
    height: 100vh;
    width: 100%;
    z-index: 100;
    background-color: #000;
    color: #fff;
`;

// área rolável entre o cabeçalho e a barra inferior; o fundo é a imagem do cassino antigo
export const Content = styled.div`
    height: calc(100vh - 48px);
    overflow-x: hidden;
    overflow-y: auto;
    background-image: url('/images/gamming.png');
    background-size: 70%;
    padding-bottom: 80px;
    filter: ${({ $desfocado }) => ($desfocado ? 'blur(8px)' : 'none')};

    @media screen and (min-width: 900px) {
        -webkit-overflow-scrolling: touch;

        &::-webkit-scrollbar-track {
            background-color: #cccccc;
        }

        &::-webkit-scrollbar {
            width: 7px;
        }

        &::-webkit-scrollbar-thumb {
            background-color: #666;
        }
    }
`;

export const Slide = styled.div`
    border-radius: 1em;
    width: 90%;
    overflow: hidden;
    margin: 25px auto;
`;

export const List = styled.div`
    width: 90%;
    margin: 0 auto;
    padding-bottom: 10px;
`;
