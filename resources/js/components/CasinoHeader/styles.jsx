import styled from 'styled-components';

export const Container = styled.div`
    width: 100%;
    display: grid;
    grid-template-columns: ${({ $logado }) => ($logado ? '45px auto 45px' : '45px auto 100px 45px')};
    grid-gap: 10px;
    align-items: center;
    padding: 5px 10px;
    background-color: #111;
    position: relative;
    z-index: 25;
`;

export const Icon = styled.i.attrs(() => ({ className: 'material-icons' }))`
    user-select: none;
    outline: none;
    color: #fff;
    font-size: 28px;
    padding: 5px;
    cursor: pointer;
`;

export const Logo = styled.img`
    height: 32px;
    cursor: pointer;
    margin: auto;
`;

export const Button = styled.a`
    padding: 5px;
    outline: none;
    background-color: ${({ theme }) => theme.principal};
    border-radius: 0.3em;
    border: solid thin ${({ theme }) => theme.principal};
    color: #fff;
    user-select: none;
    text-decoration: none;
    font-size: 13px;
    text-align: center;
    transition-duration: 0.3s;
    cursor: pointer;

    &:hover {
        background-color: transparent;
    }
`;
