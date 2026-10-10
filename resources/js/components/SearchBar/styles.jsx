import styled from 'styled-components';

// ContainerSearch e InputSearch de main/styles.js
export const Container = styled.div`
    background-color: ${({ theme }) => theme.superficie_titulo};
    padding: 7px 10px;
    display: flex;
    justify-content: space-between;
    flex-shrink: 0;
`;

export const Field = styled.div`
    background-color: ${({ theme }) => theme.superficie_cupom};
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 7px;
    flex: 1;
    min-width: 0;

    & + & {
        margin-left: 10px;
    }
`;

export const Icon = styled.i.attrs(() => ({ className: 'material-icons' }))`
    user-select: none;
    color: ${({ theme }) => theme.texto_principal};
    font-size: 25px;
`;

export const Input = styled.input`
    background: transparent;
    outline: none;
    border: none;
    width: 100%;
    color: ${({ theme }) => theme.texto_principal};
    margin-left: 10px;
    font-size: 1em;
`;
