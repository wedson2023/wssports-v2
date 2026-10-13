import styled, { css, keyframes } from 'styled-components';

export const Label = styled.label`
    display: block;
    font-size: 14px;
    font-weight: 500;
    color: #333;
    margin: 4px 0 8px;
`;

export const Field = styled.div`
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 0 14px;
    border: 2px solid ${({ $erro }) => ($erro ? '#e53935' : '#e1e3e8')};
    border-radius: 12px;
    transition: border-color 0.15s;

    &:focus-within {
        border-color: ${({ theme, $erro }) => ($erro ? '#e53935' : theme.principal)};
    }
`;

export const Prefix = styled.span`
    font-size: 18px;
    font-weight: 500;
    color: #888;
`;

export const Input = styled.input`
    flex: 1;
    min-width: 0;
    height: 52px;
    border: none;
    outline: none;
    font-size: 22px;
    font-weight: 500;
    color: #222;
    background: transparent;
`;

export const Hint = styled.p`
    margin-top: 6px;
    font-size: 12px;
    color: #888;
`;

export const ErrorText = styled.p`
    margin-top: 6px;
    font-size: 13px;
    color: #e53935;
`;

export const Chips = styled.div`
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin: 12px 0 16px;
`;

export const Chip = styled.button`
    padding: 8px 14px;
    border-radius: 6px;
    border: 1px solid ${({ $ativo, theme }) => ($ativo ? theme.principal : '#d9dbe0')};
    background: ${({ $ativo, theme }) => ($ativo ? `color-mix(in srgb, ${theme.principal} 10%, #fff)` : '#fff')};
    color: ${({ $ativo, theme }) => ($ativo ? theme.principal : '#444')};
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
`;

export const BonusBox = styled.div`
    padding: 12px 14px;
    margin-bottom: 18px;
    border-radius: 12px;
    background: #f6f7f9;

    small {
        display: block;
        margin-top: 4px;
        color: #777;
        font-size: 12px;
    }
`;

export const Check = styled.label`
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 14px;
    font-weight: 500;
    color: #333;
    cursor: pointer;

    input {
        width: 18px;
        height: 18px;
        accent-color: ${({ theme }) => theme.principal};
    }
`;

export const Methods = styled.div`
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 10px;
`;

export const Method = styled.button`
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 14px;
    border-radius: 8px;
    border: 2px solid #e1e3e8;
    background: #fff;
    color: #333;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: border-color 0.15s, background 0.15s;

    i {
        font-size: 24px;
        color: #888;
    }

    ${({ $ativo, theme }) => $ativo && css`
        border-color: ${theme.principal};
        background: color-mix(in srgb, ${theme.principal} 7%, #fff);

        i {
            color: ${theme.principal};
        }
    `}
`;

// animações do sucesso do depósito
const tracar = keyframes`
    to { stroke-dashoffset: 0; }
`;

const preencher = keyframes`
    to { fill-opacity: 1; }
`;

const estourar = keyframes`
    0% { transform: scale(1); }
    50% { transform: scale(1.12); }
    100% { transform: scale(1); }
`;

const pulsar = keyframes`
    0% { box-shadow: 0 0 0 0 rgba(40, 167, 69, 0.45); }
    100% { box-shadow: 0 0 0 18px rgba(40, 167, 69, 0); }
`;

const subir = keyframes`
    from { opacity: 0; transform: translateY(8px); }
    to { opacity: 1; transform: translateY(0); }
`;

export const Notice = styled.div`
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 10px;
    padding: 24px 0;
    text-align: center;
    color: #555;
    font-size: 14px;

    i {
        font-size: 40px;
        color: #999;
    }
`;

export const Success = styled(Notice)`
    p {
        font-size: 16px;
        font-weight: 500;
        color: #222;
        animation: ${subir} 0.4s ease-out 0.75s both;
    }

    @media (prefers-reduced-motion: reduce) {
        p {
            animation: none;
        }
    }
`;

// check do sucesso: círculo traçado e preenchido, "pop" e o check traçado por cima
export const SuccessMark = styled.svg`
    width: 72px;
    height: 72px;
    border-radius: 50%;
    animation: ${estourar} 0.35s ease-out 0.55s both, ${pulsar} 0.9s ease-out 0.6s both;

    .circulo {
        fill: #28a745;
        fill-opacity: 0;
        stroke: #28a745;
        stroke-width: 2;
        stroke-dasharray: 151;
        stroke-dashoffset: 151;
        animation: ${tracar} 0.5s cubic-bezier(0.65, 0, 0.45, 1) forwards, ${preencher} 0.3s ease-out 0.45s forwards;
    }

    .check {
        fill: none;
        stroke: #fff;
        stroke-width: 4;
        stroke-linecap: round;
        stroke-linejoin: round;
        stroke-dasharray: 36;
        stroke-dashoffset: 36;
        animation: ${tracar} 0.35s cubic-bezier(0.65, 0, 0.45, 1) 0.65s forwards;
    }

    @media (prefers-reduced-motion: reduce) {
        animation: none;

        .circulo,
        .check {
            animation: none;
            stroke-dashoffset: 0;
        }

        .circulo {
            fill-opacity: 1;
        }
    }
`;
