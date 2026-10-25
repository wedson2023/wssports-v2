import styled from 'styled-components';
import { mobile } from '../../theme/tokens';

import { BankRichText } from '../RulesContent/styles';

export { Error } from '../CashModal';

// moldura do editor, com a barra fixa no topo e a área de escrita rolando embaixo
export const Frame = styled.div`
    display: flex;
    flex-direction: column;
    border: solid 1px ${({ $erro, theme }) => ($erro ? '#e53935' : theme.superficie_titulo)};
    border-radius: 6px;
    overflow: hidden;

    .tiptap {
        min-height: 320px;
        max-height: 60dvh;
        padding: 14px 16px;
        overflow-y: auto;
        background-color: #fff;
        color: #333;
        line-height: 1.6;
        outline: none;

        h2 { font-size: 1.25em; margin: 14px 0 6px; }
        h3 { font-size: 1.1em; margin: 12px 0 6px; }
        p { margin: 0 0 8px; }
        ul, ol { margin: 0 0 8px; padding-left: 22px; }
        a { color: ${({ theme }) => theme.principal}; text-decoration: underline; }
    }
`;

export const Bar = styled.div`
    position: sticky;
    top: 0;
    z-index: 1;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 2px;
    padding: 6px;
    border-bottom: solid 1px ${({ theme }) => theme.superficie_titulo};
    background-color: ${({ theme }) => theme.superficie_barra};
`;

export const Select = styled.select`
    min-height: 36px;
    margin-right: 4px;
    padding: 0 8px;
    border: solid 1px ${({ theme }) => theme.superficie_titulo};
    border-radius: 4px;
    background-color: ${({ theme }) => theme.fundo_pagina};
    color: ${({ theme }) => theme.texto_principal};
    font: inherit;
    font-size: 0.85em;
`;

export const Button = styled.button`
    display: flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    border: none;
    border-radius: 4px;
    background-color: ${({ $ativo, theme }) => ($ativo ? theme.principal : 'transparent')};
    color: ${({ $ativo, theme }) => ($ativo ? '#fff' : theme.texto_principal)};
    cursor: pointer;

    i {
        font-size: 20px;
    }

    &:disabled {
        opacity: 0.35;
        cursor: default;
    }

    ${mobile} {
        width: 40px;
        height: 40px;
    }
`;

export const Divider = styled.span`
    width: 1px;
    height: 22px;
    margin: 0 4px;
    background-color: ${({ theme }) => theme.superficie_titulo};
`;

// paleta de cores aberta embaixo do botão
export const Colors = styled.div`
    position: relative;

    [role='menu'] {
        position: absolute;
        top: 40px;
        left: 0;
        z-index: 2;
        display: grid;
        grid-template-columns: repeat(4, 28px);
        gap: 6px;
        padding: 8px;
        border: solid 1px ${({ theme }) => theme.superficie_titulo};
        border-radius: 6px;
        background-color: ${({ theme }) => theme.fundo_pagina};
        box-shadow: 0 6px 16px rgba(0, 0, 0, 0.25);
    }
`;

export const Swatch = styled.button`
    width: 28px;
    height: 28px;
    border: solid 1px #888;
    border-radius: 50%;
    background-color: ${({ $cor }) => $cor};
    cursor: pointer;
`;

// prévia com o mesmo estilo da página de regras do site
export const Preview = styled(BankRichText)`
    min-height: 320px;
    margin: 0;
    border-radius: 0;
`;

// no computador, editor e prévia lado a lado; no celular, um embaixo do outro (a prévia vai pelo botão)
export const Workspace = styled.div`
    display: grid;
    grid-template-columns: minmax(0, 3fr) minmax(0, 2fr);
    align-items: start;
    gap: 16px;

    ${mobile} {
        grid-template-columns: minmax(0, 1fr);
    }
`;

// coluna da prévia: acompanha a rolagem enquanto o editor cresce
export const Side = styled.aside`
    position: sticky;
    top: 12px;
    display: flex;
    flex-direction: column;
    border: solid 1px ${({ theme }) => theme.superficie_titulo};
    border-radius: 6px;
    overflow: hidden;

    ${Preview} {
        min-height: 0;
        max-height: 60dvh;
        overflow-y: auto;
    }
`;

export const SideTitle = styled.div`
    display: flex;
    align-items: center;
    gap: 6px;
    min-height: 49px;
    padding: 0 12px;
    border-bottom: solid 1px ${({ theme }) => theme.superficie_titulo};
    background-color: ${({ theme }) => theme.superficie_barra};
    color: ${({ theme }) => theme.texto_secundario};
    font-size: 0.85em;

    i {
        color: ${({ theme }) => theme.principal};
        font-size: 18px;
    }
`;

export const Empty = styled.p`
    margin: 0;
    padding: 24px 16px;
    color: ${({ theme }) => theme.texto_apagado};
    font-size: 0.85em;
    text-align: center;
`;

export const Footer = styled.div`
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
`;

export const PreviewToggle = styled.button`
    display: flex;
    align-items: center;
    gap: 6px;
    min-height: 40px;
    padding: 0 14px;
    border: solid 1px ${({ theme }) => theme.principal};
    border-radius: 4px;
    background: transparent;
    color: ${({ theme }) => theme.principal};
    font: inherit;
    font-size: 0.88em;
    cursor: pointer;
`;

export const Counter = styled.span`
    color: ${({ $passou, theme }) => ($passou ? '#e53935' : theme.texto_apagado)};
    font-size: 0.8em;
`;
