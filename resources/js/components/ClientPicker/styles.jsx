import styled from 'styled-components';

// mesma busca com resultados do filtro de vendedor (SellerPicker)
export { Box, Input, Option, Options, Tag, Wrapper } from '../SellerPicker/styles';

export const Chips = styled.div`
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-top: 8px;
`;

// cliente escolhido, com o botão de tirar
export const Chip = styled.span`
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 4px 4px 10px;
    border-radius: 16px;
    background-color: ${({ theme }) => theme.superficie_titulo};
    color: ${({ theme }) => theme.texto_principal};
    font-size: 0.85em;

    button {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 26px;
        height: 26px;
        border: none;
        border-radius: 50%;
        background: transparent;
        color: ${({ theme }) => theme.texto_secundario};
        cursor: pointer;
    }

    i {
        font-size: 16px;
    }
`;
