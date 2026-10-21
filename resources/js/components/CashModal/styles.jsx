import styled, { css } from 'styled-components';
import { mobile } from '../../theme/tokens';

// borda dos campos: no tema claro um cinza mais forte, para os campos aparecerem no fundo branco
const borda_campo = ({ theme }) => (theme.modo === 'escuro' ? '#3a3a3a' : '#a8a8a8');

// moldura dos modais do painel, com borda em degradê cinza → branco para marcar os limites
export const Container = styled.div`
    position: fixed;
    top: 50%;
    left: 50%;
    z-index: 48;
    display: flex;
    flex-direction: column;
    width: min(${({ $largura }) => $largura}px, calc(100vw - 32px));
    height: ${({ $altura_fixa }) => ($altura_fixa ? 'min(640px, 85vh)' : 'auto')};
    height: ${({ $altura_fixa }) => ($altura_fixa ? 'min(640px, 85dvh)' : 'auto')};
    max-height: 90vh;
    max-height: 90dvh;
    border: solid 1px transparent;
    border-radius: 8px;
    overflow: hidden;
    background:
        linear-gradient(${({ theme }) => theme.fundo_pagina}, ${({ theme }) => theme.fundo_pagina}) padding-box,
        linear-gradient(160deg, ${({ theme }) => (theme.modo === 'escuro' ? '#ffffffb3, #5a5a5a' : '#8a8a8a, #e6e6e6')}) border-box;
    color: ${({ theme }) => theme.texto_principal};
    box-shadow: 0 12px 40px rgba(0, 0, 0, 0.4);
    transform: translate(-50%, ${({ $visivel }) => ($visivel ? '-50%' : '-45%')});
    transition-duration: 0.25s;
    opacity: ${({ $visivel }) => ($visivel ? 1 : 0)};
    visibility: ${({ $visivel }) => ($visivel ? 'visible' : 'hidden')};

    ${mobile} {
        width: 100%;
        height: 100vh;
        height: 100dvh;
        max-height: 100dvh;
        border: none;
        border-radius: 0;
    }
`;

export const Body = styled.div`
    display: flex;
    flex: 1;
    flex-direction: column;
    gap: 16px;
    min-height: 0;
    padding: 16px;
    overflow-y: auto;

    > * {
        flex-shrink: 0;
    }
`;

export const Footer = styled.footer`
    flex-shrink: 0;
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    padding: 12px 16px;
    border-top: solid 1px ${({ theme }) => theme.superficie_titulo};

    ${mobile} {
        > * {
            flex: 1;
        }
    }
`;

export const Fields = styled.div`
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16px 14px;

    ${mobile} {
        grid-template-columns: minmax(0, 1fr);
    }
`;

export const Field = styled.div`
    display: flex;
    flex-direction: column;
    gap: 6px;
    min-width: 0;
    grid-column: ${({ $inteiro }) => ($inteiro ? '1 / -1' : 'auto')};
`;

export const Label = styled.label`
    color: ${({ theme }) => theme.texto_secundario};
    font-size: 0.8em;
`;

const campo = css`
    width: 100%;
    padding: 10px;
    border: solid 1px ${({ theme, $erro }) => ($erro ? '#e53935' : borda_campo({ theme }))};
    border-radius: 4px;
    background-color: ${({ theme }) => theme.fundo_pagina};
    color: ${({ theme }) => theme.texto_principal};
    font: inherit;
    color-scheme: ${({ theme }) => (theme.modo === 'escuro' ? 'dark' : 'light')};

    &:focus {
        outline: none;
        border-color: ${({ theme }) => theme.principal};
    }

    &:disabled {
        opacity: 0.6;
    }
`;

export const Input = styled.input`
    ${campo}
`;

export const Textarea = styled.textarea`
    ${campo}
    min-height: 80px;
    resize: vertical;
`;

export const Hint = styled.p`
    margin: 0;
    color: ${({ theme }) => theme.texto_secundario};
    font-size: 0.8em;
`;

export const Error = styled.span`
    color: #e53935;
    font-size: 0.75em;
`;

// aviso destacado (cor do tema; vermelho no perigo)
export const Warning = styled.div`
    display: flex;
    align-items: flex-start;
    gap: 8px;
    padding: 10px 12px;
    border-left: solid 3px ${({ $tom, theme }) => $tom ?? theme.principal};
    border-radius: 4px;
    background-color: ${({ theme }) => theme.superficie_barra};
    font-size: 0.85em;

    i {
        color: ${({ $tom, theme }) => $tom ?? theme.principal};
        font-size: 20px;
    }
`;

// quadro de resumo (rótulo à esquerda, valor à direita)
export const Summary = styled.dl`
    display: flex;
    flex-direction: column;
    margin: 0;
    border: solid 1px ${({ theme }) => theme.superficie_titulo};
    border-radius: 6px;
    overflow: hidden;
`;

export const SummaryRow = styled.div`
    display: flex;
    justify-content: space-between;
    gap: 10px;
    padding: 8px 12px;
    font-size: ${({ $destaque }) => ($destaque ? '1em' : '0.85em')};
    font-weight: ${({ $destaque }) => ($destaque ? 'bold' : 'normal')};
    background-color: ${({ $destaque, theme }) => ($destaque ? theme.superficie_barra : 'transparent')};

    & + & {
        border-top: solid 1px ${({ theme }) => theme.superficie_titulo};
    }

    dt {
        color: ${({ theme }) => theme.texto_secundario};
    }

    dd {
        margin: 0;
        color: ${({ $cor, theme }) => $cor ?? theme.texto_principal};
        text-align: right;
    }
`;

export const Button = styled.button`
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    min-height: 44px;
    padding: 0 20px;
    border: none;
    border-radius: 4px;
    background-color: ${({ $tom, theme }) => $tom ?? theme.principal};
    color: #fff;
    font: inherit;
    font-weight: 500;
    cursor: pointer;

    &:disabled {
        opacity: 0.6;
        cursor: default;
    }
`;

export const GhostButton = styled.button`
    min-height: 44px;
    padding: 0 18px;
    border: solid 1px ${borda_campo};
    border-radius: 4px;
    background: transparent;
    color: ${({ theme }) => theme.texto_principal};
    font: inherit;
    cursor: pointer;
`;
