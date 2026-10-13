import styled, { css } from 'styled-components';
import { mobile } from '../../theme/tokens';

// base visual dos modais novos da spec 007, no padrão do TableModal/MatchDetailsModal refeitos:
// cartão branco com cantos retos e sombra; no mobile, folha que sobe de baixo
export const Card = styled.div`
    width: ${({ $largura }) => `min(${$largura}px, calc(100vw - 32px))`};
    max-height: 88vh;
    max-height: 88dvh;
    background: #fff;
    color: #333;
    display: flex;
    flex-direction: column;
    position: fixed;
    z-index: 160;
    top: 50%;
    left: 50%;
    transform: translate(-50%, ${({ $visivel }) => ($visivel ? '-50%' : 'calc(-50% + 24px)')});
    overflow: hidden;
    box-shadow: 0 20px 50px rgba(0, 0, 0, 0.35);
    transition: opacity 0.25s ease, transform 0.25s ease, visibility 0.25s;
    opacity: ${({ $visivel }) => ($visivel ? 1 : 0)};
    visibility: ${({ $visivel }) => ($visivel ? 'visible' : 'hidden')};

    ${mobile} {
        width: 100%;
        max-height: 92vh;
        max-height: 92dvh;
        top: auto;
        bottom: 0;
        left: 0;
        transform: translateY(${({ $visivel }) => ($visivel ? 0 : '100%')});
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
    padding: 8px 8px 8px 16px;
    background: #1c1c1c;
    border-bottom: 3px solid ${({ theme }) => theme.principal};
`;

export const Titles = styled.div`
    display: flex;
    flex-direction: column;
    gap: 2px;
    min-width: 0;
`;

export const Title = styled.h2`
    color: #fff;
    font-size: 14px;
    font-weight: 500;
`;

export const Subtitle = styled.span`
    color: #aaa;
    font-size: 12px;
`;

export const CloseButton = styled.button`
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border: none;
    border-radius: 50%;
    background: transparent;
    color: rgba(255, 255, 255, 0.6);
    cursor: pointer;
    transition: background 0.15s, color 0.15s;

    /* fechar discreto: só clareia no hover, sem a cor do tema */
    &:hover,
    &:focus-visible {
        background: rgba(255, 255, 255, 0.1);
        color: #fff;
        outline: none;
    }

    i {
        font-size: 18px;
    }
`;

export const Body = styled.div`
    flex: 1;
    min-height: 0;
    overflow-y: auto;
    -webkit-overflow-scrolling: touch;
    padding: 18px 20px;

    &::-webkit-scrollbar {
        width: 6px;
    }

    &::-webkit-scrollbar-thumb {
        background-color: #c5c5c5;
        border-radius: 3px;
    }
`;

export const Footer = styled.div`
    display: flex;
    gap: 8px;
    padding: 12px 16px;
    border-top: 1px solid #eee;
    background: #fff;

    ${mobile} {
        padding-bottom: calc(12px + env(safe-area-inset-bottom));
    }
`;

// botão dos modais: principal preenchido na cor do tema; secundário com contorno
export const ModalButton = styled.button`
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    min-height: 44px;
    padding: 0 12px;
    border: 2px solid ${({ theme }) => theme.principal};
    border-radius: 6px;
    background: ${({ $secundario, theme }) => ($secundario ? '#fff' : theme.principal)};
    color: ${({ $secundario, theme }) => ($secundario ? theme.principal : '#fff')};
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: filter 0.15s, transform 0.1s;

    &:hover:not(:disabled),
    &:focus-visible {
        filter: brightness(0.92);
        outline: none;
    }

    &:active:not(:disabled) {
        transform: scale(0.98);
    }

    &:disabled {
        opacity: 0.6;
        cursor: default;
    }

    i {
        font-size: 18px;
    }

    ${({ $largo }) => $largo && css`
        flex: none;
        width: 100%;
    `}
`;
