import styled, { css } from 'styled-components';
import { mobile } from '../../theme/tokens';

// modal da tabela do vendedor: cartão branco com cantos arredondados e sombra, lista com o item
// marcado em destaque na cor do tema e botões com hierarquia (limpar é secundário)
export const Container = styled.div`
    width: min(520px, calc(100vw - 32px));
    max-height: 85vh;
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
        max-height: none;
        height: 100vh;
        height: 100dvh;
        top: 0;
        left: 0;
        transform: translateY(${({ $visivel }) => ($visivel ? 0 : '24px')});
        border-radius: 0;
        z-index: 150;
    }

    @media (prefers-reduced-motion: reduce) {
        transition: none;
    }
`;

export const Header = styled.header`
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    padding: 14px 12px 14px 20px;
    background: #1c1c1c;
    border-bottom: 3px solid ${({ theme }) => theme.principal};
`;

export const Title = styled.div`
    display: flex;
    flex-direction: column;
    gap: 2px;
    color: #fff;
    font-size: 16px;
    font-weight: 500;
`;

// quantidade de campeonatos marcados, abaixo do título
export const Counter = styled.span`
    color: #aaa;
    font-size: 12px;
    font-weight: 400;
`;

export const Icon = styled.i.attrs(() => ({ className: 'material-icons' }))`
    user-select: none;
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

    &:hover,
    &:focus-visible {
        background: ${({ $cor }) => $cor};
        outline: none;
    }
`;

export const List = styled.div`
    padding: 12px;
    overflow-x: hidden;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: 6px;
    height: 360px;
    background: #f6f7f9;

    ${mobile} {
        flex: 1;
        height: auto;
        min-height: 0;
    }

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

export const Message = styled.p`
    display: block;
    text-align: center;
    margin: 35px auto;
    color: #999;
    font-size: 0.9em;
    user-select: none;
`;

export const Label = styled.label`
    display: flex;
    align-items: center;
    gap: 12px;
    flex-shrink: 0;
    padding: 12px 14px;
    background: #fff;
    border: 1px solid #e6e6e6;
    border-radius: 10px;
    color: #444;
    font-size: 14px;
    user-select: none;
    cursor: pointer;
    transition: border-color 0.15s, background 0.15s;

    &:hover {
        border-color: ${({ theme }) => theme.principal};
    }

    ${({ $marcado, theme }) => $marcado && css`
        border-color: ${theme.principal};
        background: color-mix(in srgb, ${theme.principal} 7%, #fff);
        color: #222;
        font-weight: 500;
    `}
`;

export const CheckBox = styled.input.attrs(() => ({ type: 'checkbox' }))`
    flex-shrink: 0;
    width: 18px;
    height: 18px;
    margin: 0;
    accent-color: ${({ theme }) => theme.principal};
    cursor: pointer;
`;

export const Text = styled.span``;

export const Buttons = styled.div`
    display: flex;
    gap: 8px;
    padding: 12px;
    background: #fff;
    border-top: 1px solid #eee;

    ${mobile} {
        padding-bottom: calc(12px + env(safe-area-inset-bottom));
    }
`;

export const Button = styled.button`
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    height: 42px;
    padding: 0 8px;
    border: 2px solid ${({ $cor }) => $cor};
    border-radius: 10px;
    outline: none;
    background-color: ${({ $cor, $secundario }) => ($secundario ? '#fff' : $cor)};
    color: ${({ $cor, $secundario }) => ($secundario ? $cor : '#fff')};
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    transition: filter 0.15s, transform 0.1s;

    &:hover,
    &:focus-visible {
        filter: brightness(0.92);
    }

    &:active {
        transform: scale(0.98);
    }

    i {
        font-size: 18px;
    }

    ${mobile} {
        font-size: 12px;

        i {
            display: none;
        }
    }
`;
