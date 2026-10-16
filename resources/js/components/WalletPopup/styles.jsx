import styled from 'styled-components';

// camada transparente que fecha o balão ao tocar fora (a "wall" do antigo)
export const Layer = styled.div`
    position: fixed;
    inset: 0;
    z-index: 50;
`;

// balão acima do item Carteira da barra inferior (3º de 4 itens: centro em 62,5% da largura)
export const Container = styled.div`
    background-color: #000;
    position: fixed;
    z-index: 100;
    width: 300px;
    left: clamp(8px, calc(62.5% - 150px), calc(100% - 308px));
    bottom: 90px;
    padding: 15px 15px 5% 15px;
    display: grid;
    grid-gap: 15px;
    grid-template-columns: calc(50% - 7px) calc(50% - 7px);
    clip-path: polygon(0 0, 100% 0, 100% 95%, 55% 95%, 50% 100%, 45% 95%, 0 95%);
    border-radius: 0.5em;
`;

export const Balance = styled.div`
    display: flex;
    flex-direction: column;
    width: 100%;
`;

export const BalanceText = styled.p`
    margin: 0 auto 5px auto;
    font-weight: 500;
    color: #fff;
`;

export const BalanceValue = styled.h3`
    font-size: 1em;
    width: 100%;
    background-color: green;
    display: block;
    margin: auto;
    color: #fff;
    padding: 5px;
    text-align: center;
    border-radius: 5px;
    margin-bottom: 5px;
`;

export const BalanceLast = styled.span`
    color: #fff;
    font-size: 12px;
    text-align: center;
    display: block;
    width: 70%;
    margin: auto;
`;

export const Button = styled.a`
    display: flex;
    align-items: center;
    justify-content: flex-start;
    background-color: #666;
    color: #fff;
    width: 100%;
    cursor: pointer;
    margin: auto;
    transition-duration: 0.5s;

    &:hover {
        background-color: #111;
    }
`;

export const Icon = styled.i.attrs(() => ({ className: 'material-icons' }))`
    color: #fff;
    font-size: 1.3em;
    padding: 7px;
    cursor: pointer;
    background-color: #999;
    margin-right: 10px;
    border-radius: 0.3em 0 0 0.3em;
`;

export const Text = styled.span``;
