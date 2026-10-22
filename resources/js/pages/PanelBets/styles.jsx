import styled, { keyframes } from 'styled-components';
import { mobile } from '../../theme/tokens';

const pulsar = keyframes`
    0% { opacity: 0.4; }
    50% { opacity: 0.8; }
    100% { opacity: 0.4; }
`;

// telas bem estreitas (até 400 px): abas com rolagem lateral e ferramentas em duas linhas
const estreita = '@media screen and (max-width: 400px)';

// borda dos campos: no tema claro um cinza mais forte, para os campos aparecerem no fundo branco
const borda_campo = ({ theme }) => (theme.modo === 'escuro' ? '#3a3a3a' : '#a8a8a8');

export const Loading = styled.div`
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 100vh;
    background-color: ${({ theme }) => theme.fundo_pagina};
`;

// área da tela, na largura das listas do painel
export const Section = styled.section`
    display: flex;
    flex-direction: column;
    gap: 14px;
    max-width: 1250px;
    margin: 14px auto 0;
    padding: 0 14px 24px;

    ${mobile} {
        padding: 0 8px 32px;
    }
`;

// período à esquerda e as ferramentas à direita
// topo da tela em grade (contracts/paginas §1): no computador, período e abas na coluna da esquerda,
// ferramentas e busca na da direita, e o painel de filtros e as etiquetas na largura toda entre as
// duas linhas; no celular, uma coluna só, com a busca acima das abas
const areas_computador = ({ $com_filtros, $com_etiquetas }) => [
    '"periodo . ferramentas"',
    $com_filtros && '"filtros filtros filtros"',
    $com_etiquetas && '"etiquetas etiquetas etiquetas"',
    '"abas . busca"',
].filter(Boolean).join(' ');

const areas_celular = ({ $com_etiquetas }) => ['"periodo"', '"ferramentas"', $com_etiquetas && '"etiquetas"', '"busca"', '"abas"']
    .filter(Boolean)
    .join(' ');

export const Top = styled.div`
    display: grid;
    grid-template-columns: max-content minmax(0, 1fr) max-content;
    grid-template-areas: ${areas_computador};
    align-items: start;
    gap: 14px 16px;

    ${mobile} {
        grid-template-columns: minmax(0, 1fr);
        grid-template-areas: ${areas_celular};
        gap: 12px;
    }
`;

export const Area = styled.div`
    grid-area: ${({ $area }) => $area};
    min-width: 0;
`;

// no computador, os botões ficam à direita, na largura do grupo
export const HeaderActions = styled.div`
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-end;
    gap: 8px;

    ${mobile} {
        width: 100%;
    }
`;

// ferramenta do cabeçalho; no celular os botões dividem a linha e o de só ícone ($compacto) fica estreito
export const Tool = styled.button`
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    min-height: 40px;
    padding: 0 14px;
    border: solid 1px ${borda_campo};
    border-radius: 6px;
    background-color: ${({ theme }) => theme.fundo_pagina};
    color: ${({ theme }) => theme.texto_principal};
    font: inherit;
    font-size: 0.85em;
    cursor: pointer;
    white-space: nowrap;

    i {
        color: ${({ theme }) => theme.principal};
        font-size: 20px;
    }

    &:disabled {
        opacity: 0.6;
        cursor: default;
    }

    &:hover:not(:disabled) {
        border-color: ${({ theme }) => theme.principal};
    }

    ${mobile} {
        flex: ${({ $compacto }) => ($compacto ? '0 0 44px' : '1')};
        padding: ${({ $compacto }) => ($compacto ? '0' : '0 14px')};
    }

    /* até 400 px: Filtros e Premiados dividem a primeira linha; Baixar planilha ($quebra) desce para a
       segunda linha, à esquerda, ao lado do Atualizar */
    ${estreita} {
        flex: ${({ $compacto, $quebra }) => ($compacto ? '0 0 44px' : ($quebra ? '1 1 calc(100% - 52px)' : '1 1 calc(50% - 4px)'))};
    }
`;

// cartões de totais: 4 colunas no computador, 2 no celular (FR-027)
export const Cards = styled.div`
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 8px;

    ${mobile} {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
`;

export const Skeleton = styled.div`
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 8px;

    span {
        height: 62px;
        border-radius: 6px;
        background-color: ${({ theme }) => theme.superficie_titulo};
        animation: ${pulsar} 1.2s ease-in-out infinite;
    }

    ${mobile} {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
`;

export const Error = styled.div`
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 10px;
    padding: 24px 10px;
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

// as abas (Todas, Pré-jogo, Ao vivo e Canceladas) dividem a largura da área, por igual: no computador,
// a largura dos balões do período; no celular, a tela toda. Até 400 px ficam todas da mesma largura,
// numa linha só, e a faixa rola para o lado
export const FullTabs = styled.div`
    [role='tab'] {
        flex: 1 1 0;
        justify-content: center;
    }

    ${mobile} {
        [role='tab'] {
            padding: 10px 6px;
        }
    }

    ${estreita} {
        [role='tab'] {
            flex: 0 0 120px;
            white-space: nowrap;
        }
    }
`;

// busca por código ou nome do cliente e a opção "Todo o período"
export const SearchRow = styled.div`
    flex: 1;
    min-width: 0;
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px 14px;
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

export const Check = styled.label`
    display: flex;
    align-items: center;
    gap: 8px;
    min-height: 40px;
    color: ${({ theme }) => theme.texto_secundario};
    font-size: 0.85em;
    cursor: pointer;

    input {
        width: 18px;
        height: 18px;
        accent-color: ${({ theme }) => theme.principal};
    }
`;
