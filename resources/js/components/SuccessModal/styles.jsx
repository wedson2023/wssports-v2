import styled from 'styled-components';
import { mobile } from '../../theme/tokens';

// cartão de confirmação do código (redesenhado com o responsável): centralizado, largura de
// leitura confortável, ações empilhadas e grandes para o toque
export const Container = styled.div`
    position: fixed;
    z-index: 50;
    top: 50%;
    left: 50%;
    width: 420px;
    max-width: 92vw;
    max-height: 92dvh;
    overflow-y: auto;
    padding: 28px 24px 20px;
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 12px 40px rgba(0, 0, 0, 0.35);
    color: #333;
    text-align: center;

    /* abre e fecha com transição suave (opacidade e deslocamento), sem trocar o display */
    opacity: ${({ $visivel }) => ($visivel ? 1 : 0)};
    visibility: ${({ $visivel }) => ($visivel ? 'visible' : 'hidden')};
    pointer-events: ${({ $visivel }) => ($visivel ? 'auto' : 'none')};
    transform: translate(-50%, -50%) ${({ $visivel }) => ($visivel ? 'scale(1)' : 'translateY(16px) scale(0.97)')};
    transition: opacity 0.25s ease, transform 0.25s ease, visibility 0.25s;

    ${mobile} {
        padding: 24px 18px 16px;
    }
`;

export const CloseButton = styled.button`
    position: absolute;
    top: 10px;
    right: 10px;
    width: 36px;
    height: 36px;
    border: none;
    border-radius: 50%;
    background: transparent;
    color: #777;
    cursor: pointer;

    &:hover {
        background: #f0f0f0;
    }
`;

export const SuccessIcon = styled.i.attrs(() => ({ className: 'material-icons' }))`
    font-size: 56px;
    color: #28b351;
`;

export const Title = styled.h2`
    margin: 6px 0 6px;
    font-size: 20px;
    font-weight: 500;
`;

export const Subtitle = styled.p`
    margin: 0 auto 18px;
    max-width: 320px;
    font-size: 14px;
    line-height: 1.5;
    color: #555;
`;

// número do bilhete da aposta confirmada do cliente: informativo, sem destaque de "levar ao vendedor"
export const TicketNumber = styled.p`
    margin: -4px 0 0;
    font-size: 14px;
    color: #666;

    strong {
        color: #222;
        font-weight: 500;
        letter-spacing: 1px;
    }
`;

export const CodeBox = styled.div`
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    padding: 12px 12px 12px 18px;
    border: 2px dashed ${({ theme }) => theme.principal};
    border-radius: 10px;
    background: #fafafa;
`;

export const Code = styled.strong`
    font-family: 'Roboto Mono', Consolas, monospace;
    font-size: 28px;
    letter-spacing: 4px;
    color: #222;
    user-select: all;

    ${mobile} {
        font-size: 24px;
        letter-spacing: 3px;
    }
`;

export const CopyButton = styled.button`
    display: flex;
    align-items: center;
    gap: 4px;
    padding: 8px 12px;
    border: none;
    border-radius: 8px;
    background: ${({ $copiado }) => ($copiado ? '#28b351' : '#e9e9e9')};
    color: ${({ $copiado }) => ($copiado ? '#fff' : '#333')};
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    transition: background 0.2s;

    i {
        font-size: 18px;
    }
`;

export const Summary = styled.dl`
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 8px;
    margin: 16px 0 20px;
`;

export const SummaryItem = styled.div`
    padding: 8px 4px;
    border-radius: 8px;
    background: #f5f5f5;

    dt {
        font-size: 11px;
        color: #777;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    dd {
        margin: 2px 0 0;
        font-size: 14px;
        font-weight: 500;
        color: #222;
    }
`;

export const Actions = styled.div`
    display: flex;
    flex-direction: column;
    gap: 10px;
`;

export const ActionButton = styled.button`
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    width: 100%;
    min-height: 46px;
    border: none;
    border-radius: 10px;
    color: #fff;
    font-size: 15px;
    font-weight: 500;
    cursor: pointer;
    background-color: ${({ $tipo, theme }) => ($tipo === 'codigo' ? '#28b351' : theme.principal)};
    transition: opacity 0.2s;

    &:hover {
        opacity: 0.9;
    }

    i {
        font-size: 20px;
    }
`;

export const TextButton = styled.button`
    margin-top: 4px;
    padding: 10px;
    border: none;
    background: none;
    color: #777;
    font-size: 14px;
    cursor: pointer;

    &:hover {
        color: #333;
        text-decoration: underline;
    }
`;
