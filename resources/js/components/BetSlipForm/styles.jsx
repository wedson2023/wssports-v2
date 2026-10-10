import styled from 'styled-components';

// InfoTicket, RowTicket, Label, TextValue, Input e IconTicket de main/styles.js
export const Container = styled.div`
    background-color: ${({ theme }) => theme.fundo_pagina};
    width: 100%;
    padding: 10px 10px 2px 10px;
    user-select: none;
`;

export const Row = styled.div`
    display: flex;
    justify-content: space-between;
    margin-bottom: 8px;
`;

export const Label = styled.label`
    border: 1px solid #999;
    border-radius: 5px;
    padding: 8px;
    display: flex;
    flex-direction: row;
    justify-content: space-between;
    align-items: center;
    flex: 1;
    min-width: 0;

    & + & {
        margin-left: 10px;
    }
`;

export const TextValue = styled.span`
    width: 100%;
    color: ${({ theme }) => (theme.modo === 'claro' ? theme.texto_principal : '#f1f1f1')};
    margin-left: 10px;
    display: block;
    text-align: center;
`;

export const Input = styled.input.attrs(() => ({ autoComplete: 'off' }))`
    width: 100%;
    min-width: 0;
    background-color: transparent;
    border: none;
    outline: none;
    color: ${({ theme }) => (theme.modo === 'claro' ? theme.texto_principal : '#f1f1f1')};
    margin-left: 10px;
    text-align: center;
`;

export const IconImage = styled.img`
    width: 18px;
`;

export const Icon = styled.i.attrs(() => ({ className: 'material-icons' }))`
    user-select: none;
    color: #ccc;
    font-size: 25px;
`;
