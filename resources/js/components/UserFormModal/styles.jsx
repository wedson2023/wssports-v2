import styled from 'styled-components';
import { mobile } from '../../theme/tokens';

// borda dos campos: no tema claro um cinza mais forte, para os campos aparecerem no fundo branco
const borda_campo = ({ theme }) => (theme.modo === 'escuro' ? theme.superficie_titulo : '#a8a8a8');

// modals/register do antigo, na moldura dos modais do painel (spec 014), com borda em degradê
// cinza → branco para marcar os limites. A altura é a do conteúdo (cadastro, só com Dados); com
// várias abas (edição), que carregam e mudam de tamanho, já abre na altura fixa ($altura_fixa)
export const Container = styled.div`
    position: fixed;
    top: 50%;
    left: 50%;
    z-index: 46;
    display: flex;
    flex-direction: column;
    width: min(760px, calc(100vw - 32px));
    height: ${({ $altura_fixa }) => ($altura_fixa ? 'min(680px, 90vh)' : 'auto')};
    height: ${({ $altura_fixa }) => ($altura_fixa ? 'min(680px, 90dvh)' : 'auto')};
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
    flex-direction: column;
    gap: 24px;
    flex: 1;
    min-height: 0;
    padding: 16px;
    overflow-y: auto;

    > * {
        flex-shrink: 0;
    }
`;

export const Grid = styled.div`
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16px 14px;

    ${mobile} {
        grid-template-columns: minmax(0, 1fr);
    }
`;

export const Field = styled.div`
    position: relative;
    display: flex;
    flex-direction: column;
    gap: 6px;
    min-width: 0;
`;

export const Label = styled.label`
    color: ${({ theme }) => theme.texto_secundario};
    font-size: 0.8em;
`;

export const Input = styled.input`
    width: 100%;
    padding: 10px;
    border: solid 1px ${({ theme, $erro }) => ($erro ? '#e53935' : borda_campo({ theme }))};
    border-radius: 4px;
    background-color: ${({ theme }) => theme.fundo_pagina};
    color: ${({ theme }) => theme.texto_principal};
    font: inherit;

    &:focus {
        outline: none;
        border-color: ${({ theme }) => theme.principal};
    }

    &:disabled {
        opacity: 0.6;
    }
`;

export const Select = styled.select`
    width: 100%;
    padding: 10px;
    border: solid 1px ${({ theme, $erro }) => ($erro ? '#e53935' : borda_campo({ theme }))};
    border-radius: 4px;
    background-color: ${({ theme }) => theme.fundo_pagina};
    color: ${({ theme }) => theme.texto_principal};
    font: inherit;

    &:focus {
        outline: none;
        border-color: ${({ theme }) => theme.principal};
    }
`;

export const Hint = styled.p`
    margin: 0;
    color: ${({ theme }) => theme.texto_secundario};
    font-size: 0.8em;
`;

export const Warning = styled.p`
    margin: 0;
    padding: 20px;
    color: ${({ theme }) => theme.texto_apagado};
    text-align: center;
`;

export const Error = styled.span`
    color: #e53935;
    font-size: 0.75em;
`;

export const Footer = styled.footer`
    flex-shrink: 0;
    display: flex;
    justify-content: flex-end;
    padding: 12px 16px;
    border-top: solid 1px ${({ theme }) => theme.superficie_titulo};
`;

export const SaveButton = styled.button`
    display: flex;
    align-items: center;
    justify-content: center;
    min-width: 130px;
    height: 40px;
    padding: 0 18px;
    border: none;
    border-radius: 4px;
    background-color: ${({ theme }) => theme.principal};
    color: #fff;
    font: inherit;
    font-weight: 500;
    text-transform: uppercase;
    cursor: pointer;

    &:disabled {
        opacity: 0.7;
        cursor: default;
    }
`;
