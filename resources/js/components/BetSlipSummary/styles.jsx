import styled from 'styled-components';
import { mobile } from '../../theme/tokens';

// CheckTicket de main/styles.js: só no mobile
export const Container = styled.div`
    display: none;
    padding: 10px 7px;
    background: ${({ theme }) => theme.superficie_titulo};

    ${mobile} {
        display: flex;
        justify-content: space-around;
    }
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
    margin-right: 10px;
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

export const CheckButton = styled.button`
    width: 35%;
    background-color: ${({ theme }) => theme.principal};
    outline: none;
    border: none;
    height: 37px;
    display: flex;
    justify-content: space-around;
    align-items: center;
    cursor: pointer;
    transition-duration: 0.3s;

    &:hover {
        opacity: 0.8;
    }
`;

export const CheckText = styled.span`
    font-weight: 400;
    color: #fff;
    font-size: 14px;
    flex: 1;
    padding-left: 10px;
    padding-right: 10px;
`;

export const Count = styled.strong`
    color: #fff;
    display: flex;
    justify-content: center;
    align-items: center;
    background: #666;
    height: 37px;
    width: 37px;
`;
