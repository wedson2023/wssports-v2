import { createGlobalStyle } from 'styled-components';
import { mobile } from '../../theme/tokens';

// estilo global igual ao do sistema antigo (styles/index.js), com a altura visível real (FR-003b)
export const Global = createGlobalStyle`
    * {
        margin: 0;
        padding: 0;
        outline: 0;
        box-sizing: border-box;
    }

    html, body, #app {
        height: 100vh;
        height: 100dvh;
        overflow: hidden;
        padding-right: 0 !important;
    }

    ${mobile} {
        html, body, #app {
            min-height: calc(100vh - 56px);
            min-height: calc(100dvh - 56px);
        }
    }

    html, body {
        background-color: ${({ theme }) => theme.fundo_pagina};
    }

    body {
        font: 14px 'Roboto', sans-serif;
        color: #333;
        -webkit-font-smoothing: antialiased !important;
    }

    ul {
        list-style: none;
    }

    button, input {
        font-family: 'Roboto', sans-serif;
    }

    /* alertas (sweetalert2) menores no mobile: 80% da largura */
    ${mobile} {
        .swal2-popup {
            width: 80% !important;
            padding: 0 0 1em;
            font-size: 0.85rem;
        }

        .swal2-icon {
            transform: scale(0.75);
            margin: 1em auto 0;
        }

        .swal2-title {
            font-size: 1.3em;
            padding-top: 0.4em;
        }
    }
`;
