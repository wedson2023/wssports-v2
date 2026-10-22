import styled, { css, keyframes } from 'styled-components';
import { mobile } from '../../theme/tokens';

const pulsar = keyframes`
    0% { opacity: 0.4; }
    50% { opacity: 0.8; }
    100% { opacity: 0.4; }
`;

// borda dos campos: no tema claro um cinza mais forte, para os campos aparecerem no fundo branco
const borda_campo = ({ theme }) => (theme.modo === 'escuro' ? '#3a3a3a' : '#a8a8a8');

// cor do selo de cada situação (FR-008): as do bilhete, legíveis nos dois temas com texto branco
const cores_situacao = {
    Aguardando: '#757575',
    Ativa: '#757575',
    Vencedor: '#2e7d32',
    Perdedor: '#c62828',
    Devolvido: '#1565c0',
    Encerrada: '#7c5cd6',
    Cancelada: '#9e9e9e',
};

// colunas que somem no celular (vão para os detalhes)
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
    background-color: ${({ $aberto, theme }) => ($aberto ? theme.superficie_barra : 'transparent')};
    cursor: pointer;

    thead & {
        cursor: default;
    }

    tbody &:hover {
        background-color: ${({ theme }) => theme.superficie_barra};
    }
`;

export const HeadCell = styled.th`
    padding: 10px 8px;
    color: ${({ theme }) => theme.texto_secundario};
    font-size: 0.85em;
    font-weight: 500;
    text-align: ${({ $acoes }) => ($acoes ? 'right' : 'left')};
    text-transform: uppercase;
    white-space: nowrap;
    width: ${({ $acoes }) => ($acoes ? '96px' : 'auto')};

    ${secundaria}

    ${mobile} {
        width: ${({ $acoes, $codigo }) => ($acoes ? '82px' : ($codigo ? '40%' : 'auto'))};

        span {
            display: ${({ $acoes }) => ($acoes ? 'none' : 'inline')};
        }
    }
`;

export const Cell = styled.td`
    padding: 10px 8px;
    color: ${({ theme }) => theme.texto_principal};
    font-weight: ${({ $forte }) => ($forte ? 'bold' : 'normal')};
    white-space: nowrap;

    ${secundaria}

    ${mobile} {
        padding: 10px 4px;
        overflow: hidden;
        text-overflow: ellipsis;
    }
`;

// código da aposta (abre o bilhete ao tocar na linha)
export const Code = styled.span`
    display: block;
    color: ${({ theme }) => theme.principal};
    font-weight: bold;
    letter-spacing: 0.5px;
`;

// cliente embaixo do código, com as etiquetas
export const Sub = styled.span`
    display: flex;
    align-items: center;
    gap: 6px;
    min-width: 0;
    max-width: 260px;
    color: ${({ theme }) => theme.texto_secundario};
    font-size: 0.85em;

    > span:first-child {
        overflow: hidden;
        text-overflow: ellipsis;
    }

    ${mobile} {
        max-width: 100%;
    }
`;

export const Tag = styled.span`
    flex-shrink: 0;
    padding: 1px 6px;
    border-radius: 10px;
    background-color: ${({ theme }) => theme.superficie_titulo};
    color: ${({ theme }) => theme.texto_secundario};
    font-size: 0.85em;
`;

// selo da situação (Status do antigo)
export const Status = styled.span`
    display: inline-block;
    padding: 4px 8px;
    border-radius: 0.3em;
    background-color: ${({ $situacao }) => cores_situacao[$situacao] ?? '#757575'};
    color: #fff;
    font-size: 0.85em;
    white-space: nowrap;

    ${mobile} {
        padding: 3px 6px;
        font-size: 0.8em;
    }
`;

export const Actions = styled.div`
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 6px;
`;

export const Expand = styled.button`
    display: flex;
    align-items: center;
    justify-content: center;
    width: 34px;
    height: 34px;
    border: solid 1px ${borda_campo};
    border-radius: 8px;
    background: transparent;
    color: ${({ theme }) => theme.texto_principal};
    cursor: pointer;

    &[aria-expanded='true'] {
        border-color: ${({ theme }) => theme.principal};
        color: ${({ theme }) => theme.principal};
    }

    &:focus-visible {
        outline: solid 2px ${({ theme }) => theme.principal};
        outline-offset: 2px;
    }
`;

export const DetailsRow = styled.tr`
    border-bottom: solid 1px ${({ theme }) => theme.superficie_titulo};
    background-color: ${({ theme }) => theme.superficie_barra};

    td {
        padding: 4px 8px 12px;
    }
`;

export const Details = styled.div`
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: 10px 16px;

    ${mobile} {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
`;

// item dos detalhes; os das colunas secundárias só aparecem no celular (no computador já estão na linha)
export const Detail = styled.div`
    display: ${({ $so_celular }) => ($so_celular ? 'none' : 'flex')};
    flex-direction: column;
    gap: 2px;
    min-width: 0;

    > span:first-child {
        color: ${({ theme }) => theme.texto_secundario};
        font-size: 0.85em;
    }

    > span:last-child {
        overflow: hidden;
        color: ${({ theme }) => theme.texto_principal};
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    ${mobile} {
        display: flex;
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

    i {
        font-size: 32px;
    }
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
        height: 44px;
        border-radius: 4px;
        background-color: ${({ theme }) => theme.superficie_titulo};
        animation: ${pulsar} 1.2s ease-in-out infinite;
    }
`;
