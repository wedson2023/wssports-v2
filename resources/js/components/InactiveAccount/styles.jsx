import styled from 'styled-components';
import { mobile } from '../../theme/tokens';

// Pending, Text, Button e Link de screens/credits/styles.js
export const Container = styled.div`
    width: 40%;
    margin-left: -20%;
    left: 50%;
    top: 50%;
    position: absolute;
    margin-top: -100px;
    text-align: center;

    ${mobile} {
        width: 80%;
        margin-left: -40%;
    }
`;

export const Text = styled.p`
    margin: 25px auto;
    color: ${({ theme }) => theme.texto_principal};
    max-width: 300px;
`;

export const Button = styled.button`
    background-color: green;
    width: 120px;
    display: block;
    margin: auto;
    color: #fff;
    padding: 10px;
    text-align: center;
    border-radius: 5px;
    border: none;
    cursor: pointer;
`;

export const ExitButton = styled(Button)`
    background-color: red;
    margin-top: 15px;
`;
