import styled from 'styled-components';
import { mobile } from '../../theme/tokens';

// cartão de entrar / criar conta, no mesmo visual do modal de sucesso; o topo (fechar, logo e
// abas) fica fixo e só o formulário rola, dentro do padding do cartão
export const Container = styled.div`
    position: fixed;
    z-index: 50;
    top: 50%;
    left: 50%;
    width: 540px;
    max-width: 94vw;
    max-height: 94dvh;
    overflow: hidden;
    padding: 20px 24px 22px;
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 12px 40px rgba(0, 0, 0, 0.35);
    color: #333;
    display: flex;
    flex-direction: column;

    /* abre e fecha com transição suave (opacidade e deslocamento), sem trocar o display */
    opacity: ${({ $visivel }) => ($visivel ? 1 : 0)};
    visibility: ${({ $visivel }) => ($visivel ? 'visible' : 'hidden')};
    pointer-events: ${({ $visivel }) => ($visivel ? 'auto' : 'none')};
    transform: translate(-50%, -50%) ${({ $visivel }) => ($visivel ? 'scale(1)' : 'translateY(16px) scale(0.97)')};
    transition: opacity 0.25s ease, transform 0.25s ease, visibility 0.25s;

    /* mobile: ocupa a tela inteira */
    ${mobile} {
        top: 0;
        left: 0;
        transform: ${({ $visivel }) => ($visivel ? 'none' : 'translateY(32px)')};
        width: 100%;
        max-width: none;
        height: 100vh;
        height: 100dvh;
        max-height: none;
        padding: 16px 16px 18px;
        border-radius: 0;
        box-shadow: none;
    }
`;

// área do formulário: rola na vertical, nunca na horizontal
export const Body = styled.div`
    flex: 1;
    min-height: 0;
    overflow-x: hidden;
    overflow-y: auto;
    -webkit-overflow-scrolling: touch;
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

export const Logo = styled.img`
    display: block;
    width: 56px;
    margin: 4px auto 12px;
`;

export const Tabs = styled.div`
    display: flex;
    margin-bottom: 18px;
    border-bottom: 1px solid #e5e5e5;
`;

export const Tab = styled.button`
    flex: 1;
    padding: 10px;
    border: none;
    border-bottom: 3px solid ${({ $ativa, theme }) => ($ativa ? theme.principal : 'transparent')};
    background: none;
    color: ${({ $ativa }) => ($ativa ? '#222' : '#888')};
    font-size: 15px;
    font-weight: 500;
    cursor: pointer;
`;

export const Form = styled.form`
    display: flex;
    flex-direction: column;
    gap: 12px;
`;

export const Row = styled.div`
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;

    ${mobile} {
        grid-template-columns: 1fr;
    }
`;

export const Field = styled.label`
    display: flex;
    flex-direction: column;
    min-width: 0;
    gap: 4px;
    font-size: 13px;
    color: #555;
`;

export const InputBox = styled.div`
    display: flex;
    align-items: center;
    min-width: 0;
    border: 1px solid ${({ $erro }) => ($erro ? '#d32f2f' : '#ccc')};
    border-radius: 8px;
    background: #fff;
    transition: border-color 0.2s;

    &:focus-within {
        border-color: ${({ theme, $erro }) => ($erro ? '#d32f2f' : theme.principal)};
    }

    input, select {
        flex: 1;
        min-width: 0;
        height: 42px;
        padding: 0 12px;
        border: none;
        outline: none;
        background: transparent;
        font-size: 15px;
        color: #222;
    }

    button {
        display: flex;
        padding: 0 10px;
        border: none;
        background: none;
        color: #888;
        cursor: pointer;
    }
`;

export const FieldError = styled.span`
    font-size: 12px;
    color: #d32f2f;
`;

export const Hint = styled.span`
    font-size: 12px;
    color: #888;
`;

export const Check = styled.label`
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    color: #555;
    cursor: pointer;
`;

export const SubmitButton = styled.button`
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    min-height: 46px;
    margin-top: 4px;
    border: none;
    border-radius: 10px;
    background: ${({ theme }) => theme.principal};
    color: #fff;
    font-size: 15px;
    font-weight: 500;
    cursor: pointer;

    &:disabled {
        opacity: 0.7;
        cursor: default;
    }
`;

export const Switch = styled.p`
    margin-top: 14px;
    text-align: center;
    font-size: 14px;
    color: #666;

    button {
        border: none;
        background: none;
        color: ${({ theme }) => theme.principal};
        font-size: 14px;
        font-weight: 500;
        cursor: pointer;
    }
`;
