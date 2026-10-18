import styled from 'styled-components';
import { mobile } from '../../theme/tokens';

// painel do afiliado igual ao screens/affiliated do sistema antigo: fundo preto, saudação, logo,
// saldo em destaque verde e tabela do extrato
export const Container = styled.div`
    display: flex;
    min-height: 100vh;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background-color: #000;
    text-align: center;
    overflow-x: hidden;
`;

export const Loading = styled.div`
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
`;

export const Content = styled.div`
    width: 100%;
    max-width: 1100px;
    margin: 0 auto;
    padding: 15px 10px 100px;
`;

export const Header = styled.div`
    position: relative;
    display: flex;
    align-items: center;
    justify-content: space-between;
`;

export const IconButton = styled.button`
    display: flex;
    align-items: center;
    justify-content: center;
    width: 45px;
    height: 45px;
    border: none;
    background: transparent;
    color: ${({ theme }) => theme.principal};
    font-size: 30px;
    cursor: pointer;

    i {
        font-size: inherit;
    }
`;

export const Wellcome = styled.h4`
    margin-top: 5px;
    color: #fff;
`;

// menu do painel (o antigo tinha um menu comentado no mesmo lugar)
export const Menu = styled.div`
    position: absolute;
    top: 45px;
    right: 0;
    z-index: 20;
    display: ${({ $aberto }) => ($aberto ? 'flex' : 'none')};
    flex-direction: column;
    min-width: 190px;
    padding: 6px 0;
    border-radius: 6px;
    background: #222;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.5);
    text-align: left;
`;

export const MenuItem = styled.button`
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 14px;
    border: none;
    background: transparent;
    color: #fff;
    font-size: 14px;
    cursor: pointer;

    i {
        color: ${({ theme }) => theme.principal};
        font-size: 20px;
    }

    &:hover {
        background: #333;
    }
`;

export const Logo = styled.img`
    display: block;
    width: 100px;
    margin: 10px auto;
`;

export const TextBalance = styled.p`
    margin: 0 auto 5px auto;
    font-weight: 500;
    color: #fff;
`;

export const Balance = styled.h3`
    display: block;
    min-width: 120px;
    width: fit-content;
    margin: auto;
    padding: 10px;
    border-radius: 5px;
    background-color: ${({ $negativo }) => ($negativo ? '#a41f1a' : 'green')};
    color: #fff;
    font-size: 1.2em;
    text-align: center;
`;

export const LastBalance = styled.span`
    display: block;
    width: 70%;
    margin: 5px auto;
    font-size: 12px;
    color: #999;
    text-align: center;
`;

export const Message = styled.p`
    display: block;
    width: 70%;
    margin: 25px auto;
    font-size: 12px;
    color: #999;
    text-align: center;

    ${mobile} {
        width: 90%;
    }
`;

// botão "Copiar meu link" (acréscimo da spec 012)
export const CopyButton = styled.button`
    display: inline-flex;
    align-items: center;
    gap: 6px;
    margin-top: 12px;
    padding: 8px 14px;
    border: solid thin ${({ theme }) => theme.principal};
    border-radius: 5px;
    background: transparent;
    color: #fff;
    font-size: 13px;
    cursor: pointer;

    i {
        font-size: 18px;
        color: ${({ theme }) => theme.principal};
    }
`;

export const Tabs = styled.div`
    display: flex;
    justify-content: center;
    gap: 6px;
    margin: 10px 0 15px;
`;

export const Tab = styled.button`
    padding: 8px 16px;
    border: solid thin ${({ theme }) => theme.principal};
    border-radius: 5px;
    background: ${({ $ativa, theme }) => ($ativa ? theme.principal : 'transparent')};
    color: #fff;
    font-size: 13px;
    cursor: pointer;
`;
