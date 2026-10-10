import styled from 'styled-components';
import { mobile } from '../../theme/tokens';

// modal com os mercados de um jogo: cartão branco com cantos arredondados e sombra, abas em
// pílula, categorias com destaque na cor do tema e linhas com o botão de cotação arredondado
export const Container = styled.div`
    width: min(560px, calc(100vw - 32px));
    height: min(600px, 85vh);
    background: #fff;
    display: flex;
    flex-direction: column;
    position: fixed;
    z-index: 50;
    top: 50%;
    left: 50%;
    transform: translate(-50%, ${({ $visivel }) => ($visivel ? '-50%' : 'calc(-50% + 24px)')});
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 20px 50px rgba(0, 0, 0, 0.35);
    transition: opacity 0.25s ease, transform 0.25s ease, visibility 0.25s;
    opacity: ${({ $visivel }) => ($visivel ? 1 : 0)};
    visibility: ${({ $visivel }) => ($visivel ? 'visible' : 'hidden')};

    ${mobile} {
        width: 100%;
        height: 100vh;
        height: 100dvh;
        top: 0;
        left: 0;
        transform: translateY(${({ $visivel }) => ($visivel ? 0 : '24px')});
        border-radius: 0;
    }

    @media (prefers-reduced-motion: reduce) {
        transition: none;
    }
`;

export const Loading = styled.div`
    width: 45px;
    height: 45px;
    position: absolute;
    top: 50%;
    left: 50%;
    margin: -22px 0 0 -22px;
`;

export const Header = styled.header`
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    min-height: 56px;
    padding: 10px 12px 10px 20px;
    background: #1c1c1c;
    border-bottom: 3px solid ${({ theme }) => theme.principal};
`;

export const Icon = styled.i.attrs(() => ({ className: 'material-icons' }))`
    user-select: none;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    color: #fff;
    font-size: 22px;
    cursor: pointer;
    transition: background 0.15s;

    &:hover {
        background: ${({ theme }) => theme.principal};
    }
`;

export const TextMatch = styled.span`
    color: #fff;
    font-size: 16px;
    font-weight: 500;
    overflow: hidden;
    white-space: nowrap;
    text-overflow: ellipsis;
`;

// abas em pílula dentro de um trilho cinza
export const Tabs = styled.div`
    display: flex;
    flex-direction: row;
    gap: 4px;
    margin: 12px 12px 4px;
    padding: 4px;
    background: #f0f1f3;
    border-radius: 12px;
`;

export const Tab = styled.a`
    display: flex;
    flex: 1;
    padding: 9px 6px;
    justify-content: center;
    align-items: center;
    border-radius: 9px;
    background-color: ${({ $ativa, theme }) => ($ativa ? theme.principal : 'transparent')};
    box-shadow: ${({ $ativa }) => ($ativa ? '0 2px 6px rgba(0, 0, 0, 0.2)' : 'none')};
    cursor: pointer;
    user-select: none;
    transition: background 0.15s;

    &:hover {
        background-color: ${({ $ativa, theme }) => ($ativa ? theme.principal : '#e2e4e8')};
    }
`;

export const TabText = styled.span`
    font-size: 13px;
    color: ${({ $ativa }) => ($ativa ? '#fff' : '#555')};
    font-weight: 500;
    white-space: nowrap;
`;

export const List = styled.section`
    overflow-x: hidden;
    overflow-y: auto;
    -webkit-overflow-scrolling: touch;
    padding: 4px 12px 70px;
    flex: 1;

    &::-webkit-scrollbar {
        width: 6px;
    }

    &::-webkit-scrollbar-track {
        background: transparent;
    }

    &::-webkit-scrollbar-thumb {
        background-color: #c5c5c5;
        border-radius: 3px;
    }
`;

// título da categoria: texto em caixa alta com uma faixa na cor do tema à esquerda
export const Category = styled.div`
    margin: 14px 0 6px;
    padding: 4px 10px;
    border-left: 3px solid ${({ theme }) => theme.principal};
    color: #222;
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 0.5px;
    text-transform: uppercase;
`;

export const Item = styled.div`
    display: flex;
    gap: 12px;
    padding: 8px 8px 8px 14px;
    margin-bottom: 6px;
    justify-content: space-between;
    align-items: center;
    background: #f6f7f9;
    border-radius: 10px;
    transition: background 0.15s;

    &:hover {
        background: #eef0f3;
    }

    /* botão de cotação largo arredondado só dentro do modal */
    > a {
        border-radius: 8px;
        justify-content: center;
    }
`;

export const TextOdd = styled.span`
    flex: 1;
    display: block;
    overflow: hidden;
    white-space: nowrap;
    text-overflow: ellipsis;
    user-select: none;
    color: #333;
    font-size: 14px;
`;

export const Message = styled.p`
    display: block;
    text-align: center;
    margin: 35px auto;
    color: #999;
    font-size: 0.9em;
    user-select: none;
`;
