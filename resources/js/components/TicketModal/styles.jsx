import styled from 'styled-components';
import { mobile } from '../../theme/tokens';

// modals/ticket do sistema antigo; centralizado com 80% da altura da tela no desktop e tela cheia no
// mobile (pedido do responsável); os palpites ocupam o espaço que sobra
export const Container = styled.div`
    width: 40%;
    height: 80vh;
    height: 80dvh;
    background: #fff;
    display: flex;
    flex-direction: column;
    position: fixed;
    z-index: 50;
    top: 50%;
    left: 50%;
    margin-left: -20%;
    transform: translateY(${({ $visivel }) => ($visivel ? '-50%' : '-40%')});
    transition-duration: 0.5s;
    opacity: ${({ $visivel }) => ($visivel ? 1 : 0)};
    visibility: ${({ $visivel }) => ($visivel ? 'visible' : 'hidden')};
    color: #333;

    ${mobile} {
        width: 100%;
        margin-left: -50%;
        height: 100vh;
        height: 100dvh;
        top: 0;
        transform: translateY(${({ $visivel }) => ($visivel ? '0' : '10%')});
        z-index: 150 !important;
    }
`;

export const Header = styled.header`
    display: flex;
    justify-content: space-between;
    align-items: center;
    height: 40px;
    padding: 10px;
    background: #222;
`;

export const Title = styled.span`
    color: #fff;
`;

export const Icons = styled.div`
    display: flex;
    justify-content: flex-end;
`;

// ícones contornados do cabeçalho, como no sistema antigo
export const Icon = styled.i.attrs(() => ({ className: 'material-icons' }))`
    user-select: none;
    padding: 5px;
    border-radius: 0.3em;
    font-size: 1em;
    cursor: pointer;
    border: solid 2px ${({ $cor }) => $cor};
    margin-left: 15px;
    color: ${({ $cor }) => $cor};
`;

export const Data = styled.div`
    flex: 1;
    min-height: 0;
    display: flex;
    flex-direction: column;
    padding: 10px;
    overflow-y: auto;
    overflow-x: hidden;

    &::-webkit-scrollbar {
        width: 3px;
        background-color: #fff;
    }

    &::-webkit-scrollbar-thumb {
        background-color: #000;
    }
`;

export const Row = styled.p`
    display: flex;
    justify-content: space-between;
    padding: 3px 0;
    margin: 0;
    font-size: 0.9em;
`;

export const Label = styled.strong`
    font-weight: 500;
`;

export const Status = styled.span`
    border-radius: 0.3em;
    padding: 5px 10px !important;
    text-align: center;
    font-size: 0.7em;
    color: #fff;
    background-color: ${({ $situacao }) => ({ Vencedor: 'green', Perdedor: 'red' })[$situacao] ?? '#222'};
`;

export const Hunches = styled.div`
    border: solid thin #ccc;
    margin: 10px 0;
    padding: 5px 10px;
    /* ocupa o espaço livre do modal; em telas muito baixas, Data rola */
    flex: 1;
    min-height: 150px;
    overflow-x: hidden;
    overflow-y: auto;
    -webkit-overflow-scrolling: touch;

    &::-webkit-scrollbar-track {
        background-color: #999;
    }

    &::-webkit-scrollbar {
        width: 7px;
    }

    &::-webkit-scrollbar-thumb {
        background-color: #666;
    }
`;

export const Hunch = styled.div`
    padding: 5px 0;
    opacity: ${({ $cancelado }) => ($cancelado ? 0.2 : 1)};

    & + div {
        border-top: solid thin #ccc;
    }
`;

export const Teams = styled.strong`
    display: flex;
    justify-content: center;
    margin: 3px auto;
    padding: 5px 0;
`;
