import styled from 'styled-components';
import { mobile } from '../../theme/tokens';

export { Error, Hint } from '../CashModal';

export const Previews = styled.div`
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
`;

// a logo sobre o fundo escuro e o claro, para conferir as duas versões do site
export const Preview = styled.div`
    display: flex;
    align-items: center;
    justify-content: center;
    height: 140px;
    padding: 12px;
    border: solid 1px ${({ theme }) => theme.superficie_titulo};
    border-radius: 6px;
    background-color: ${({ $fundo }) => $fundo};

    img {
        max-width: 100%;
        max-height: 100%;
        object-fit: contain;
    }

    ${mobile} {
        height: 110px;
    }
`;

export const Actions = styled.div`
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px 14px;
`;

export const Upload = styled.button`
    display: flex;
    align-items: center;
    gap: 6px;
    min-height: 44px;
    padding: 0 18px;
    border: none;
    border-radius: 4px;
    background-color: ${({ theme }) => theme.principal};
    color: #fff;
    font: inherit;
    font-weight: 500;
    cursor: pointer;

    &:disabled {
        opacity: 0.6;
        cursor: default;
    }
`;

// aviso amarelo de imagem pequena
export const Warning = styled.div`
    display: flex;
    align-items: flex-start;
    gap: 8px;
    padding: 10px 12px;
    border-left: solid 3px #f9a825;
    border-radius: 4px;
    background-color: ${({ theme }) => theme.superficie_barra};
    font-size: 0.85em;

    i {
        color: #f9a825;
        font-size: 20px;
    }
`;

// ícones gerados em caixas do mesmo tamanho, alinhados, cada um no tamanho real até 56 px
export const Icons = styled.div`
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 10px;
`;

export const Icon = styled.figure`
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
    margin: 0;

    div {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 100%;
        height: 76px;
        border-radius: 6px;
        background-color: ${({ theme }) => theme.superficie_barra};
    }

    img {
        max-width: 56px;
        max-height: 56px;
    }

    span {
        color: ${({ theme }) => theme.texto_apagado};
        font-size: 0.72em;
    }
`;
