import styled, { css, keyframes } from 'styled-components';
import { mobile } from '../../theme/tokens';

const pulsar = keyframes`
    0% { opacity: 0.4; }
    50% { opacity: 0.8; }
    100% { opacity: 0.4; }
`;

// colunas que somem no celular
const secundaria = ({ $secundaria }) => $secundaria && css`
    ${mobile} {
        display: none;
    }
`;

export const Scroll = styled.div`
    width: 100%;
    overflow-x: auto;
    opacity: ${({ $atualizando }) => ($atualizando ? 0.6 : 1)};
    transition: opacity 0.2s;
`;

// no celular a largura é fixa, para os textos longos serem cortados em vez de alargar a tabela
export const Table = styled.table`
    width: 100%;
    border-collapse: collapse;
    font-size: 0.85em;

    ${mobile} {
        table-layout: fixed;
    }
`;

export const Row = styled.tr`
    border-bottom: solid 1px ${({ theme }) => theme.superficie_titulo};

    tbody &:hover {
        background-color: ${({ theme }) => theme.superficie_barra};
    }
`;

export const HeadCell = styled.th`
    padding: 10px 8px;
    color: ${({ theme }) => theme.texto_secundario};
    font-size: 0.85em;
    font-weight: 500;
    text-align: left;
    text-transform: uppercase;
    white-space: nowrap;

    ${secundaria}

    ${mobile} {
        width: ${({ $acoes, $estreita }) => {
            if ($acoes) return '58px';
            return $estreita ? '40%' : 'auto';
        }};
    }
`;

export const Cell = styled.td`
    padding: 10px 8px;
    color: ${({ theme }) => theme.texto_principal};
    white-space: nowrap;

    ${secundaria}

    > span:first-child {
        display: block;
        max-width: 320px;
        overflow: hidden;
        font-weight: ${({ $principal }) => ($principal ? 500 : 400)};
        text-overflow: ellipsis;
    }

    ${mobile} {
        overflow: hidden;

        > span:first-child {
            max-width: 100%;
        }
    }
`;

// subtítulo da coluna principal (país, hora...), só no celular
export const Sub = styled.span`
    display: none;

    ${mobile} {
        display: block;
        overflow: hidden;
        color: ${({ theme }) => theme.texto_secundario};
        font-size: 0.85em;
        text-overflow: ellipsis;
    }
`;

// botões de ação no computador; no celular dão lugar aos três pontinhos
export const Actions = styled.div`
    display: flex;
    gap: 6px;

    ${mobile} {
        display: none;
    }
`;

export const MenuCell = styled.div`
    display: none;

    ${mobile} {
        display: flex;
        justify-content: flex-end;
    }
`;

// botão com fundo e borda suaves na cor da ação; no hover fica cheio
export const Action = styled.button`
    display: flex;
    align-items: center;
    justify-content: center;
    width: 34px;
    height: 34px;
    border: solid 1px ${({ $tom }) => `${$tom}66`};
    border-radius: 8px;
    background-color: ${({ $tom }) => `${$tom}1f`};
    color: ${({ $tom }) => $tom};
    cursor: pointer;
    transition: background-color 0.15s, color 0.15s, transform 0.15s;

    i {
        font-size: 19px;
    }

    &:hover {
        background-color: ${({ $tom }) => $tom};
        color: #fff;
        transform: translateY(-1px);
    }

    &:focus-visible {
        outline: solid 2px ${({ $tom }) => $tom};
        outline-offset: 2px;
    }
`;

export const Empty = styled.div`
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 10px;
    padding: 30px 10px;
    color: ${({ theme }) => theme.texto_apagado};
    text-align: center;
`;

export const Retry = styled.button`
    padding: 8px 14px;
    border: solid 2px ${({ theme }) => theme.principal};
    border-radius: 4px;
    background: transparent;
    color: ${({ theme }) => theme.principal};
    font-weight: bold;
    cursor: pointer;
`;

export const Skeleton = styled.div`
    display: flex;
    flex-direction: column;
    gap: 8px;

    span {
        height: 42px;
        border-radius: 4px;
        background-color: ${({ theme }) => theme.superficie_titulo};
        animation: ${pulsar} 1.2s ease-in-out infinite;
    }
`;
