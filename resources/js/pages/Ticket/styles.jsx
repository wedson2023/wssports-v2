import styled from 'styled-components';
import { mobile } from '../../theme/tokens';

// a página rola por dentro (o body é fixo na altura da tela, GlobalStyle)
export const Container = styled.div`
    height: 100%;
    overflow-x: hidden;
    overflow-y: auto;
    -webkit-overflow-scrolling: touch;
    background-color: ${({ theme }) => theme.fundo_pagina};
    color: ${({ theme }) => theme.texto_principal};
    padding: 0 16px 24px;

    button:focus-visible,
    a:focus-visible {
        outline: 2px solid ${({ theme }) => theme.principal};
        outline-offset: 2px;
    }

    ${mobile} {
        padding: 0 12px 96px;
    }
`;

export const TopBar = styled.header`
    display: grid;
    grid-template-columns: 45px 1fr 45px;
    align-items: center;
    max-width: 1100px;
    margin: 0 auto;
    padding: 6px 0;
`;

export const BackButton = styled.button`
    width: 45px;
    height: 45px;
    border: 0;
    background: none;
    color: ${({ theme }) => theme.principal};
    cursor: pointer;

    .material-icons {
        font-size: 30px;
    }
`;

export const Logo = styled.img`
    justify-self: center;
    width: 100px;
    max-height: 48px;
    object-fit: contain;
`;

export const Grid = styled.div`
    max-width: 1100px;
    margin: 0 auto;
    display: grid;
    grid-template-columns: minmax(0, 1fr) 360px;
    grid-template-areas: 'palpites resumo';
    gap: 16px;
    align-items: start;

    ${mobile} {
        grid-template-columns: minmax(0, 1fr);
        grid-template-areas: 'resumo' 'palpites';
    }
`;

export const Aside = styled.aside`
    grid-area: resumo;
    position: sticky;
    top: 12px;
    display: flex;
    flex-direction: column;
    gap: 12px;

    ${mobile} {
        position: static;
    }
`;

export const Main = styled.main`
    grid-area: palpites;
    min-width: 0;
`;

export const Hunches = styled.div`
    display: flex;
    flex-direction: column;
    gap: 10px;
`;

export const Invite = styled.p`
    padding: 12px 14px;
    border-radius: 10px;
    font-size: 13px;
    background-color: ${({ theme }) => theme.superficie_titulo};
    color: ${({ theme }) => theme.texto_secundario};

    a {
        color: ${({ theme }) => theme.principal};
        font-weight: 500;
    }
`;

export const Empty = styled.div`
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 14px;
    padding: 60px 16px;
    text-align: center;
    color: ${({ theme }) => theme.texto_secundario};

    button {
        border: 0;
        border-radius: 6px;
        padding: 10px 16px;
        cursor: pointer;
        color: #fff;
        background-color: ${({ theme }) => theme.principal};
    }
`;
