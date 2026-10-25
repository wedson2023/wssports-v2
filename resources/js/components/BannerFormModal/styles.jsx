import styled from 'styled-components';

// prévia da imagem na proporção do lugar onde ela aparece no site
export const Preview = styled.div`
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    aspect-ratio: ${({ $proporcao }) => $proporcao};
    border: dashed 1px ${({ theme }) => theme.texto_apagado};
    border-radius: 4px;
    background-color: ${({ theme }) => theme.superficie_barra};
    overflow: hidden;

    img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    span {
        color: ${({ theme }) => theme.texto_apagado};
        font-size: 0.85em;
    }
`;

export const Choose = styled.div`
    input {
        max-width: 100%;
        color: ${({ theme }) => theme.texto_secundario};
        font-size: 0.85em;
    }
`;

export const SwitchRow = styled.div`
    display: flex;
    align-items: center;
    gap: 10px;
    min-height: 42px;

    span {
        color: ${({ theme }) => theme.texto_principal};
        font-size: 0.9em;
    }
`;
