import styled from 'styled-components';
import { mobile } from '../../theme/tokens';

// moldura dos modais do painel (mesma do modal da combinação de risco, spec 014), com borda em
// degradê cinza → branco. Já abre na altura fixa: a lista de permissões chega depois e é longa,
// então o modal não encolhe no carregamento nem cresce quando ela aparece
export const Container = styled.div`
    position: fixed;
    top: 50%;
    left: 50%;
    z-index: 46;
    display: flex;
    flex-direction: column;
    width: min(620px, calc(100vw - 32px));
    height: min(640px, 85vh);
    height: min(640px, 85dvh);
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
        border: none;
        border-radius: 0;
    }
`;

export const Body = styled.div`
    display: flex;
    flex-direction: column;
    gap: 12px;
    flex: 1;
    min-height: 0;
    padding: 16px;
    overflow-y: auto;

    > * {
        flex-shrink: 0;
    }
`;
