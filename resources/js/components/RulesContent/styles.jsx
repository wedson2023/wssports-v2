import styled from 'styled-components';
import { Link } from '@inertiajs/react';

// regulamento: barra com "Voltar" e atalho para tirar dúvidas (spec 005) e blocos de regras no
// visual de "Regras de apostas" do sistema antigo (spec 006)
export const Page = styled.div`
    height: 100vh;
    height: 100dvh;
    overflow-y: auto;
    -webkit-overflow-scrolling: touch;
    background: ${({ theme }) => theme.fundo_pagina};
    color: ${({ theme }) => theme.texto_principal};
`;

export const TopBar = styled.header`
    position: sticky;
    top: 0;
    z-index: 5;
    display: flex;
    align-items: center;
    gap: 12px;
    height: 56px;
    padding: 0 16px;
    background: ${({ theme }) => theme.superficie_titulo};
    border-bottom: 2px solid ${({ theme }) => theme.principal};
`;

export const BackLink = styled(Link)`
    display: flex;
    align-items: center;
    gap: 4px;
    padding: 6px 10px 6px 4px;
    border-radius: 8px;
    color: ${({ theme }) => theme.texto_principal};
    text-decoration: none;
    font-size: 14px;

    &:hover {
        background: ${({ theme }) => theme.superficie_barra};
    }

    i {
        color: ${({ theme }) => theme.principal};
    }
`;

export const TopTitle = styled.h1`
    font-size: 16px;
    font-weight: 500;
`;

// logo da banca no topo, como no sistema antigo (200px no antigo; tamanho da spec 005)
export const LogoArea = styled.div`
    max-width: 760px;
    margin: 0 auto;
    padding: 28px 16px 20px;
    text-align: center;
`;

export const Logo = styled.img`
    width: 96px;
`;

// regras da banca escritas no editor do painel (spec 021): HTML limpo no servidor, no mesmo cartão
// claro com a borda na cor do tema que os parágrafos tinham antes
export const BankRichText = styled.div`
    margin: 8px 0;
    padding: 12px 16px;
    background: #f6f7f9;
    border-left: 3px solid ${({ theme }) => theme.principal};
    border-radius: 8px;
    font-size: 0.9em;
    line-height: 1.6;
    color: #333;
    overflow-wrap: anywhere;

    h2,
    h3 {
        margin: 14px 0 6px;
        color: #222;
        line-height: 1.3;
    }

    h2 {
        font-size: 1.25em;
    }

    h3 {
        font-size: 1.1em;
    }

    p {
        margin: 0 0 8px;
    }

    ul,
    ol {
        margin: 0 0 8px;
        padding-left: 22px;
    }

    li + li {
        margin-top: 4px;
    }

    a {
        color: ${({ theme }) => theme.principal};
        text-decoration: underline;
    }

    > :first-child {
        margin-top: 0;
    }

    > :last-child {
        margin-bottom: 0;
    }
`;

export const Help = styled.div`
    max-width: 760px;
    margin: 0 auto 40px;
    padding: 0 16px;
    text-align: center;
    font-size: 14px;
    color: ${({ theme }) => theme.texto_secundario};

    button {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        margin-top: 10px;
        padding: 10px 18px;
        border: none;
        border-radius: 10px;
        background: #28b351;
        color: #fff;
        font-size: 14px;
        cursor: pointer;
    }
`;
