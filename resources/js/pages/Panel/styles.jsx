import styled from 'styled-components';

// azul da marca do Mercado Pago e o tom do hover/pressionado
const azul = '#009ee3';
const azul_escuro = '#007eb5';

// tela branca cobrindo o layout, com o botão no centro nos dois eixos
export const Page = styled.div`
    position: fixed;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 16px;
    background: #fff;
`;

export const ConnectButton = styled.button`
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    min-height: 52px;
    min-width: 260px;
    max-width: 100%;
    padding: 0 28px;
    border: none;
    border-radius: 12px;
    background: ${azul};
    color: #fff;
    font-size: 16px;
    font-weight: 600;
    letter-spacing: 0.2px;
    cursor: pointer;
    box-shadow: 0 6px 18px rgba(0, 158, 227, 0.3), 0 1px 3px rgba(0, 0, 0, 0.08);
    transition: background-color 0.15s ease, box-shadow 0.15s ease, transform 0.15s ease;

    i {
        font-size: 22px;
    }

    &:hover:not(:disabled) {
        background: ${azul_escuro};
        box-shadow: 0 10px 24px rgba(0, 158, 227, 0.35), 0 2px 4px rgba(0, 0, 0, 0.08);
        transform: translateY(-1px);
    }

    &:active:not(:disabled) {
        transform: translateY(0);
        box-shadow: 0 4px 12px rgba(0, 158, 227, 0.3);
    }

    &:focus-visible {
        outline: 3px solid rgba(0, 158, 227, 0.45);
        outline-offset: 3px;
    }

    &:disabled {
        cursor: progress;
        opacity: 0.85;
    }

    @media (prefers-reduced-motion: reduce) {
        transition: none;

        &:hover:not(:disabled) {
            transform: none;
        }
    }
`;
