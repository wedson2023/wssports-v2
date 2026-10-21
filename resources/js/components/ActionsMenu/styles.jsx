import styled, { keyframes } from 'styled-components';

const surgir = keyframes`
    from { opacity: 0; transform: translateY(-4px); }
    to { opacity: 1; transform: translateY(0); }
`;

export const Wrapper = styled.div`
    display: inline-flex;
`;

export const Trigger = styled.button`
    display: flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    border: solid 1px ${({ theme, $aberto }) => ($aberto ? theme.principal : theme.superficie_titulo)};
    border-radius: 8px;
    background-color: ${({ theme, $aberto }) => ($aberto ? theme.superficie_titulo : 'transparent')};
    color: ${({ theme }) => theme.texto_principal};
    cursor: pointer;

    &:hover {
        background-color: ${({ theme }) => theme.superficie_titulo};
    }
`;

// lista das ações, como um balão escuro/claro conforme o tema, com borda para destacar do fundo
export const Menu = styled.div`
    position: fixed;
    z-index: 40;
    display: flex;
    flex-direction: column;
    min-width: 170px;
    padding: 6px;
    border: solid 1px ${({ theme }) => (theme.modo === 'escuro' ? '#444' : '#d5d5d5')};
    border-radius: 8px;
    background-color: ${({ theme }) => (theme.modo === 'escuro' ? '#1e1e1e' : '#ffffff')};
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.45);
    animation: ${surgir} 0.12s ease-out;
`;

export const Item = styled.button`
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 12px;
    border: none;
    border-radius: 6px;
    background: transparent;
    color: ${({ theme }) => theme.texto_principal};
    font: inherit;
    font-size: 0.9em;
    text-align: left;
    cursor: pointer;

    i {
        color: ${({ $tom }) => $tom};
        font-size: 20px;
    }

    &:hover {
        background-color: ${({ $tom }) => `${$tom}22`};
    }
`;
