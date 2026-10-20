import styled from 'styled-components';
import { mobile } from '../../theme/tokens';

export const Loading = styled.div`
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 100vh;
    background-color: ${({ theme }) => theme.fundo_pagina};
`;

// grade das seções: duas colunas no desktop (largura máxima do Group antigo), uma no celular
export const Grid = styled.div`
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px;
    max-width: 1250px;
    margin: 14px auto 0;
    padding: 0 14px;

    ${mobile} {
        grid-template-columns: minmax(0, 1fr);
        gap: 10px;
        padding: 0 8px;
    }
`;

// cartões e gráfico de uma seção (Apurado e Caixa online)
export const Summary = styled.div`
    display: flex;
    flex-direction: column;
    gap: 14px;
`;

// grade dos cartões; no celular, 2 colunas para o vendedor ler sem rolar (SC-008)
export const Cards = styled.div`
    display: grid;
    grid-template-columns: ${({ $colunas }) => ($colunas ? `repeat(${$colunas}, minmax(0, 1fr))` : 'repeat(auto-fill, minmax(190px, 1fr))')};
    gap: 8px;

    ${mobile} {
        grid-template-columns: ${({ $colunas }) => ($colunas ? `repeat(${$colunas}, minmax(0, 1fr))` : 'repeat(2, minmax(0, 1fr))')};
    }
`;

// gráfico ao lado da lista no desktop, um embaixo do outro no celular
export const Charts = styled.div`
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px;
    align-items: center;

    ${mobile} {
        grid-template-columns: minmax(0, 1fr);
    }
`;

// liga/desliga o som dos avisos de combinação, no título da seção
export const SoundIcon = styled.i.attrs(() => ({ className: 'material-icons', role: 'button' }))`
    color: #fff;
    font-size: 22px;
    cursor: pointer;
    user-select: none;
`;
