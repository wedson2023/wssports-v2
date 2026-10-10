import styled, { keyframes } from 'styled-components';
import { mobile } from '../../theme/tokens';

// entrada suave: o cartão sobe um pouco no desktop; a folha sobe de baixo no mobile
const entrar_cartao = keyframes`
    from { opacity: 0; transform: translate(-50%, calc(-50% + 16px)); }
    to { opacity: 1; transform: translate(-50%, -50%); }
`;

const entrar_folha = keyframes`
    from { transform: translateY(100%); }
    to { transform: translateY(0); }
`;

export const Card = styled.div`
    position: fixed;
    z-index: 70;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: min(480px, calc(100vw - 32px));
    max-height: 90vh;
    display: flex;
    flex-direction: column;
    padding: 20px;
    border-radius: 16px;
    background: ${({ theme }) => theme.superficie_titulo};
    color: ${({ theme }) => theme.texto_principal};
    box-shadow: 0 12px 40px rgba(0, 0, 0, 0.4);
    animation: ${entrar_cartao} 200ms ease-out;

    ${mobile} {
        top: auto;
        bottom: 0;
        left: 0;
        transform: none;
        width: 100%;
        max-height: 85vh;
        max-height: 85dvh;
        padding: 12px 16px calc(16px + env(safe-area-inset-bottom));
        border-radius: 16px 16px 0 0;
        animation: ${entrar_folha} 200ms ease-out;
    }

    @media (prefers-reduced-motion: reduce) {
        animation: none;
    }
`;

// puxador visual da folha (só no mobile)
export const Handle = styled.span`
    display: none;

    ${mobile} {
        display: block;
        width: 40px;
        height: 4px;
        margin: 0 auto 10px;
        border-radius: 2px;
        background: ${({ theme }) => theme.texto_apagado};
    }
`;

export const CloseButton = styled.button`
    position: absolute;
    top: 10px;
    right: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    border: none;
    border-radius: 50%;
    background: rgba(0, 0, 0, 0.35);
    color: #fff;
    cursor: pointer;

    &:hover,
    &:focus-visible {
        background: rgba(0, 0, 0, 0.55);
    }
`;

export const Title = styled.h2`
    margin: 0 44px 12px 0;
    font-size: 16px;
    font-weight: 500;
`;

export const ImageLink = styled.a`
    display: block;
    min-height: 0;
    border-radius: 10px;
    overflow: hidden;
`;

// imagem inteira, sem corte
export const Image = styled.img`
    display: block;
    width: 100%;
    max-height: 70vh;
    object-fit: contain;
    border-radius: 10px;
    background: rgba(0, 0, 0, 0.15);

    ${mobile} {
        max-height: 60vh;
        max-height: 60dvh;
    }
`;

export const Actions = styled.div`
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    margin-top: 16px;

    ${mobile} {
        flex-direction: column-reverse;
    }
`;

const botao = `
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    min-height: 44px;
    padding: 0 20px;
    border-radius: 10px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
`;

export const PrimaryButton = styled.button`
    ${botao}
    border: none;
    background: ${({ theme }) => theme.principal};
    color: #fff;

    &:hover {
        background: ${({ theme }) => theme.derivada};
    }

    &:focus-visible {
        outline: 2px solid ${({ theme }) => theme.texto_principal};
        outline-offset: 2px;
    }

    i {
        font-size: 18px;
    }
`;

export const SecondaryButton = styled.button`
    ${botao}
    border: none;
    background: transparent;
    color: ${({ theme }) => theme.texto_secundario};

    &:hover,
    &:focus-visible {
        color: ${({ theme }) => theme.texto_principal};
        background: rgba(127, 127, 127, 0.15);
    }
`;
