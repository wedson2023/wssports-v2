import styled from 'styled-components';

// borda dos campos: no tema claro um cinza mais forte, para os campos aparecerem no fundo branco
const borda_campo = ({ theme }) => (theme.modo === 'escuro' ? '#3a3a3a' : '#a8a8a8');

export const Wrapper = styled.div`
    position: relative;
`;

export const Box = styled.label`
    display: flex;
    align-items: center;
    gap: 8px;
    min-height: 44px;
    padding: 0 10px;
    border: solid 1px ${borda_campo};
    border-radius: 6px;
    background-color: ${({ theme }) => theme.fundo_pagina};
    color: ${({ theme }) => theme.texto_secundario};

    &:focus-within {
        border-color: ${({ theme }) => theme.principal};
    }
`;

export const Input = styled.input`
    flex: 1;
    min-width: 0;
    border: none;
    outline: none;
    background: transparent;
    color: ${({ theme }) => theme.texto_principal};
    font: inherit;
`;

// resultados abaixo do campo
export const Options = styled.div`
    position: absolute;
    top: calc(100% + 4px);
    left: 0;
    right: 0;
    z-index: 5;
    display: flex;
    flex-direction: column;
    max-height: 240px;
    overflow-y: auto;
    border: solid 1px ${borda_campo};
    border-radius: 6px;
    background-color: ${({ theme }) => theme.fundo_pagina};
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.3);
`;

export const Option = styled.button`
    display: flex;
    align-items: center;
    gap: 8px;
    min-height: 44px;
    padding: 6px 10px;
    border: none;
    border-bottom: solid 1px ${({ theme }) => theme.superficie_titulo};
    background: transparent;
    color: ${({ theme }) => theme.texto_principal};
    font: inherit;
    font-size: 0.9em;
    text-align: left;
    cursor: pointer;

    span {
        color: ${({ theme }) => theme.texto_secundario};
        font-size: 0.9em;
    }

    &:hover {
        background-color: ${({ theme }) => theme.superficie_barra};
    }
`;

export const Tag = styled.span`
    padding: 1px 6px;
    border-radius: 10px;
    background-color: ${({ theme }) => theme.superficie_titulo};
`;

// vendedor escolhido, com o botão de trocar
export const Selected = styled.div`
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    min-height: 44px;
    padding: 0 4px 0 12px;
    border: solid 1px ${({ theme }) => theme.principal};
    border-radius: 6px;
    color: ${({ theme }) => theme.texto_principal};

    span {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
`;

export const Clear = styled.button`
    display: flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    border: none;
    border-radius: 50%;
    background: transparent;
    color: ${({ theme }) => theme.principal};
    cursor: pointer;
`;
