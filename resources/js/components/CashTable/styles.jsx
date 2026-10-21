import styled, { css, keyframes } from 'styled-components';
import { mobile } from '../../theme/tokens';

const pulsar = keyframes`
    0% { opacity: 0.4; }
    50% { opacity: 0.8; }
    100% { opacity: 0.4; }
`;

// borda dos campos: no tema claro um cinza mais forte, para os campos aparecerem no fundo branco
const borda_campo = ({ theme }) => (theme.modo === 'escuro' ? '#3a3a3a' : '#a8a8a8');

// cores do sinal (as mesmas do StatCard), legíveis nos dois temas
const cores_sinal = { positivo: '#43a047', negativo: '#e53935' };

// colunas que somem no celular (como na lista de usuários)
const secundaria = ({ $secundaria }) => $secundaria && css`
    ${mobile} {
        display: none;
    }
`;

// busca e ordenação acima da lista
export const Bar = styled.div`
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
`;

export const SearchBox = styled.label`
    display: flex;
    flex: 1;
    align-items: center;
    gap: 8px;
    min-width: 220px;
    min-height: 44px;
    padding: 0 10px;
    border: solid 1px ${borda_campo};
    border-radius: 6px;
    background-color: ${({ theme }) => theme.fundo_pagina};
    color: ${({ theme }) => theme.texto_secundario};

    &:focus-within {
        border-color: ${({ theme }) => theme.principal};
    }

    ${mobile} {
        min-width: 0;
        width: 100%;
        flex-basis: 100%;
    }
`;

export const Search = styled.input`
    flex: 1;
    min-width: 0;
    border: none;
    outline: none;
    background: transparent;
    color: ${({ theme }) => theme.texto_principal};
    font: inherit;
`;

export const Order = styled.div`
    display: flex;
    gap: 6px;

    select {
        min-height: 44px;
        padding: 0 10px;
        border: solid 1px ${borda_campo};
        border-radius: 6px;
        background-color: ${({ theme }) => theme.fundo_pagina};
        color: ${({ theme }) => theme.texto_principal};
        font: inherit;
    }

    ${mobile} {
        flex: 1;

        select {
            flex: 1;
        }
    }
`;

export const More = styled.button`
    display: flex;
    align-items: center;
    justify-content: center;
    width: 44px;
    min-height: 44px;
    border: solid 1px ${borda_campo};
    border-radius: 6px;
    background-color: ${({ theme }) => theme.fundo_pagina};
    color: ${({ theme }) => theme.texto_principal};
    cursor: pointer;
`;

// Table, Thead, Th e Td da lista de usuários do painel
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
        width: ${({ $acoes, $nome, $com_menu }) => ($acoes ? ($com_menu ? '82px' : '42px') : ($nome ? '46%' : 'auto'))};

        span {
            display: ${({ $acoes }) => ($acoes ? 'none' : 'inline')};
        }
    }
`;

export const Cell = styled.td`
    padding: 10px 8px;
    color: ${({ theme }) => theme.texto_principal};
    white-space: nowrap;

    ${secundaria}

    ${mobile} {
        padding: 10px 4px;
        overflow: hidden;
        text-overflow: ellipsis;
    }
`;

export const Name = styled.span`
    display: block;
    max-width: 240px;
    overflow: hidden;
    font-weight: 500;
    text-overflow: ellipsis;

    ${mobile} {
        max-width: 100%;
    }
`;

// nome do gestor: abre o nível abaixo
export const NameButton = styled.button`
    display: flex;
    align-items: center;
    gap: 2px;
    max-width: 260px;
    padding: 0;
    border: none;
    background: none;
    color: ${({ theme }) => theme.principal};
    font: inherit;
    font-weight: 500;
    text-align: left;
    cursor: pointer;

    span {
        overflow: hidden;
        text-overflow: ellipsis;
    }

    i {
        flex-shrink: 0;
        font-size: 18px;
    }

    &:hover span {
        text-decoration: underline;
    }

    ${mobile} {
        max-width: 100%;
        min-height: 28px;
    }
`;

// login (ou contato do cliente) embaixo do nome
export const Login = styled.span`
    display: flex;
    align-items: center;
    gap: 6px;
    min-width: 0;
    color: ${({ theme }) => theme.texto_secundario};
    font-size: 0.85em;

    > span:first-child {
        overflow: hidden;
        text-overflow: ellipsis;
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

// valor com a cor do sinal (verde, vermelho ou cinza); null = sem cor
export const Value = styled.span`
    font-weight: ${({ $forte }) => ($forte ? 'bold' : 'normal')};

    ${({ $sinal, theme }) => $sinal && css`
        color: ${cores_sinal[$sinal] ?? theme.texto_apagado};
    `}
`;

// três pontinhos e o botão de expandir
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

// linha expandida com todas as informações
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
        height: 38px;
        border-radius: 4px;
        background-color: ${({ theme }) => theme.superficie_titulo};
        animation: ${pulsar} 1.2s ease-in-out infinite;
    }
`;
