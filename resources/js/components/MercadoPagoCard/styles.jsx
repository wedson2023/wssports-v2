import styled from 'styled-components';
import { mobile } from '../../theme/tokens';

export const Loading = styled.div`
    display: flex;
    justify-content: center;
    padding: 20px 0;
`;

// situação e datas do vínculo (ou da conexão do robô), em linha no computador
export const Details = styled.div`
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 10px 24px;
`;

export const Chip = styled.span`
    padding: 4px 12px;
    border-radius: 12px;
    background-color: ${({ $cor }) => $cor};
    color: #fff;
    font-size: 0.8em;
    font-weight: 500;
`;

export const Data = styled.span`
    display: flex;
    flex-direction: column;
    color: ${({ theme }) => theme.texto_principal};
    font-size: 0.88em;

    span {
        color: ${({ theme }) => theme.texto_apagado};
        font-size: 0.85em;
    }
`;

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

export const Actions = styled.div`
    display: flex;
    flex-wrap: wrap;
    gap: 10px;

    ${mobile} {
        > button {
            flex: 1;
            justify-content: center;
        }
    }
`;

// botão cheio na cor do tema ou só com o contorno (ação secundária)
export const Button = styled.button`
    display: flex;
    align-items: center;
    gap: 6px;
    min-height: 44px;
    padding: 0 18px;
    border: solid 2px ${({ theme }) => theme.principal};
    border-radius: 4px;
    background-color: ${({ $contorno, theme }) => ($contorno ? 'transparent' : theme.principal)};
    color: ${({ $contorno, theme }) => ($contorno ? theme.principal : '#fff')};
    font: inherit;
    font-weight: 500;
    cursor: pointer;

    &:disabled {
        opacity: 0.6;
        cursor: default;
    }
`;

export const Note = styled.p`
    margin: 0;
    color: ${({ theme }) => theme.texto_apagado};
    font-size: 0.85em;
`;
