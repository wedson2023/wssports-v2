import styled from 'styled-components';
import { mobile } from '../../theme/tokens';

// cadastro público de afiliado no visual do modals/affiliated do sistema antigo: cabeçalho escuro
// com o título, campos em duas colunas com ícone na cor do tema e botão "CRIAR CONTA"
export const Container = styled.div`
    position: fixed;
    z-index: 50;
    top: 12%;
    left: 50%;
    width: 60%;
    max-height: 84dvh;
    display: flex;
    flex-direction: column;
    background-color: #fff;
    color: #333;

    opacity: ${({ $visivel }) => ($visivel ? 1 : 0)};
    visibility: ${({ $visivel }) => ($visivel ? 'visible' : 'hidden')};
    pointer-events: ${({ $visivel }) => ($visivel ? 'auto' : 'none')};
    transform: translateX(-50%) ${({ $visivel }) => ($visivel ? 'translateY(0)' : 'translateY(13vh)')};
    transition: opacity 0.5s, transform 0.5s, visibility 0.5s;

    ${mobile} {
        top: 0;
        width: 100%;
        height: 100vh;
        height: 100dvh;
        max-height: none;
        transform: translateX(-50%);
    }
`;

export const Header = styled.header`
    display: flex;
    justify-content: space-between;
    align-items: center;
    height: 45px;
    flex-shrink: 0;
    padding-left: 10px;
    background: #222;
    color: #fff;
`;

export const Title = styled.span`
    color: #fff;
`;

export const CloseButton = styled.button`
    border: none;
    background: transparent;
    padding: 0 10px;
    color: ${({ theme }) => theme.principal};
    cursor: pointer;
    user-select: none;

    &:hover {
        opacity: 0.8;
    }
`;

// só a lista rola, como no antigo
export const List = styled.div`
    flex: 1;
    min-height: 0;
    overflow-x: hidden;
    overflow-y: auto;
    -webkit-overflow-scrolling: touch;

    &::-webkit-scrollbar-track {
        background-color: #999;
    }

    &::-webkit-scrollbar {
        width: 7px;
    }

    &::-webkit-scrollbar-thumb {
        background-color: #333;
    }

    ${mobile} {
        padding-bottom: 75px;
    }
`;

export const Form = styled.form`
    display: grid;
    grid-template-columns: 1fr 1fr;
    grid-gap: 10px;
    padding: 10px;

    ${mobile} {
        grid-template-columns: 100%;
    }
`;

export const Field = styled.div`
    display: flex;
    flex-direction: column;
    gap: 4px;
    min-width: 0;
`;

// rótulo visível acima do campo (melhoria aprovada sobre o antigo, que só tinha o placeholder)
export const Caption = styled.span`
    font-size: 12px;
    color: #666;
`;

export const Label = styled.label`
    display: flex;
    align-items: center;
    width: 100%;
    padding: 8px 10px;
    border: solid thin ${({ $erro }) => ($erro ? '#d32f2f' : '#999')};
    border-radius: 7px;

    input,
    select,
    textarea {
        flex: 1;
        min-width: 0;
        padding: 8px 10px;
        border: none;
        background-color: transparent;
        color: #333;
        font-size: 14px;
        outline: none;
    }

    /* "Sobre você" (spec 022) */
    textarea {
        min-height: 80px;
        font-family: inherit;
        resize: vertical;
    }

    select {
        padding: 10px;
    }

    input:disabled {
        opacity: 0.5;
    }
`;

export const InputIcon = styled.i`
    margin-right: 15px;
    font-size: 25px;
    text-align: center;
    color: ${({ theme }) => theme.principal};
`;

export const EyeButton = styled.button`
    display: flex;
    border: none;
    background: transparent;
    color: #999;
    cursor: pointer;
`;

export const FieldError = styled.span`
    font-size: 12px;
    color: #d32f2f;
`;

export const Hint = styled.span`
    font-size: 12px;
    color: #888;
`;

export const SubmitButton = styled.button`
    grid-column: 1 / -1;
    display: flex;
    justify-content: center;
    align-items: center;
    height: 48px;
    width: 100%;
    border: none;
    border-radius: 7px;
    background-color: ${({ theme }) => theme.principal};
    color: #fff;
    cursor: pointer;
    transition-duration: 0.3s;

    &:hover:not(:disabled) {
        opacity: 0.8;
    }

    &:disabled {
        cursor: default;
        opacity: 0.8;
    }
`;
