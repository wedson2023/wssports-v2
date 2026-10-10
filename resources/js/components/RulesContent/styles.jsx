import styled from 'styled-components';
import { Link } from '@inertiajs/react';
import { mobile } from '../../theme/tokens';

// regulamento redesenhado com o responsável: barra com "Voltar", leitura em largura confortável,
// regras numeradas e atalho para tirar dúvidas
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

export const Hero = styled.div`
    max-width: 760px;
    margin: 0 auto;
    padding: 28px 16px 20px;
    text-align: center;
`;

export const Logo = styled.img`
    width: 96px;
    margin-bottom: 12px;
`;

export const Title = styled.h2`
    font-size: 24px;
    font-weight: 500;

    ${mobile} {
        font-size: 20px;
    }
`;

export const Subtitle = styled.p`
    margin-top: 6px;
    font-size: 14px;
    color: ${({ theme }) => theme.texto_secundario};
`;

export const Card = styled.section`
    max-width: 760px;
    margin: 0 auto 24px;
    padding: 8px 24px;
    background: #fff;
    border-radius: 12px;
    color: #333;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);

    ${mobile} {
        margin: 0 12px 20px;
        padding: 4px 16px;
    }
`;

export const RuleList = styled.ol`
    list-style: none;
`;

export const Rule = styled.li`
    display: flex;
    gap: 14px;
    padding: 16px 0;
    border-bottom: 1px solid #eee;

    &:last-child {
        border-bottom: none;
    }
`;

export const RuleNumber = styled.span`
    flex: none;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: ${({ theme }) => theme.principal};
    color: #fff;
    font-size: 13px;
    font-weight: 500;
`;

export const RuleText = styled.p`
    font-size: 15px;
    line-height: 1.6;
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
