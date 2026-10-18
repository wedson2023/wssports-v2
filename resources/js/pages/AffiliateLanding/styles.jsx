import styled from 'styled-components';
import { mobile } from '../../theme/tokens';

// página "Seja um afiliado!" igual à screens/new-affiliated do sistema antigo
export const Container = styled.div`
    display: grid;
    grid-template-columns: auto auto;
    grid-gap: 10px;
    min-height: 100vh;
    background: #fff;
    color: #333;

    ${mobile} {
        display: flex;
        flex-direction: column;
        height: auto;
    }
`;

export const Header = styled.div`
    grid-column-start: 1;
    grid-column-end: 3;
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px;
    background-color: #222;
`;

export const Logo = styled.img`
    width: 60px;
`;

export const Buttons = styled.div`
    display: flex;
    justify-content: flex-end;
    align-items: center;
`;

export const Button = styled.button`
    margin-right: 15px;
    padding: 10px 15px;
    border: solid thin ${({ theme }) => theme.principal};
    border-radius: 0.3em;
    background-color: ${({ $preenchido, theme }) => ($preenchido ? theme.principal : 'transparent')};
    color: #fff;
    font-size: 15px;
    text-align: center;
    cursor: pointer;
    user-select: none;
    transition-duration: 0.3s;

    &:hover {
        background-color: ${({ theme }) => theme.principal};
    }
`;

export const Description = styled.div`
    width: 80%;
    align-self: center;
    margin: auto;

    ${mobile} {
        display: flex;
        flex-direction: column;
        text-align: center;
    }

    ${Button} {
        color: ${({ theme }) => theme.principal};
    }

    ${Button}:hover {
        color: #fff;
    }
`;

export const Title = styled.h1``;

export const Text = styled.p`
    display: block;
    margin: 25px 0;
    font-size: 1.3em;
`;

// link de recuperação de senha (acréscimo da spec 012, FR-021a)
export const RecoveryLink = styled.button`
    display: block;
    margin-top: 18px;
    padding: 0;
    border: none;
    background: transparent;
    color: #666;
    font-size: 14px;
    text-decoration: underline;
    cursor: pointer;

    ${mobile} {
        align-self: center;
    }
`;

export const Image = styled.img`
    display: block;
    width: 500px;
    margin: auto;

    ${mobile} {
        width: 80%;
    }
`;
