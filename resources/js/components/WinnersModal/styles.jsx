import styled from 'styled-components';

// prévia da imagem, na largura do modal
export const Preview = styled.img`
    display: block;
    width: 100%;
    height: auto;
    border: solid 1px ${({ theme }) => theme.superficie_titulo};
    border-radius: 4px;
`;

export const Empty = styled.div`
    display: flex;
    justify-content: center;
    padding: 30px 10px;
    color: ${({ theme }) => theme.texto_apagado};
    text-align: center;
`;
