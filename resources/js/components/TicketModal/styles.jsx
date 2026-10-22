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

    /* no painel (spec 018) o bilhete fica maior, para os palpites terem mais espaço de rolagem */
    ${({ $painel }) => $painel && `
        width: min(640px, calc(100vw - 32px));
        height: 92vh;
        height: 92dvh;
        margin-left: calc(min(640px, calc(100vw - 32px)) / -2);
    `}

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
    /* Encerrada (spec 010): mesma cor do bilhete em tempo real */
    background-color: ${({ $situacao }) => ({ Vencedor: 'green', Perdedor: 'red', Encerrada: '#7c5cd6' })[$situacao] ?? '#222'};
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

// palpite cancelado apagado; o botão de restaurar (painel) continua visível
// palpite cancelado apagado; o rodapé com o botão de restaurar (painel) continua visível
export const Hunch = styled.div`
    padding: 5px 0;

    > *:not(footer) {
        opacity: ${({ $cancelado }) => ($cancelado ? 0.2 : 1)};
    }

    & + div {
        border-top: solid thin #ccc;
    }
`;

// rodapé do palpite (painel, spec 018): a ação fica alinhada à direita, abaixo das informações
export const HunchActions = styled.footer`
    display: flex;
    justify-content: flex-end;
    padding-top: 4px;
`;

// cancelar (vermelho) ou restaurar (verde) o palpite: ícone com texto, sem caixa em volta; compacto
// (só o ícone) ao lado da cotação, dentro do bloco Criar Aposta
export const HunchAction = styled.button`
    display: inline-flex;
    align-items: center;
    gap: 4px;
    min-height: ${({ $compacto }) => ($compacto ? '24px' : '32px')};
    margin-left: ${({ $compacto }) => ($compacto ? '6px' : '0')};
    padding: ${({ $compacto }) => ($compacto ? '0' : '0 8px')};
    border: none;
    border-radius: 4px;
    background: transparent;
    color: ${({ $restaurar }) => ($restaurar ? '#2e7d32' : '#c62828')};
    font: inherit;
    font-size: 0.8em;
    font-weight: 500;
    vertical-align: middle;
    cursor: pointer;

    i {
        font-size: 18px;
    }

    &:hover:not(:disabled) {
        background-color: ${({ $restaurar }) => ($restaurar ? '#e8f5e9' : '#ffebee')};
    }

    &:disabled {
        opacity: 0.5;
        cursor: default;
    }
`;

// ícones de ação do bilhete no painel, logo abaixo do ModalHeader
export const PanelTools = styled.div`
    flex-shrink: 0;
    display: flex;
    justify-content: flex-end;
    padding: 8px 10px;
    border-bottom: solid thin #ccc;
`;

// faixa da aposta cancelada (painel)
export const Banner = styled.p`
    margin: 0 0 8px;
    padding: 8px 10px;
    border-radius: 0.3em;
    background-color: #eee;
    color: #555;
    font-size: 0.85em;
    text-align: center;
`;

// rodapé com "Cancelar aposta" ou "Restaurar aposta" (painel); não encolhe
export const Footer = styled.footer`
    flex-shrink: 0;
    display: flex;
    justify-content: flex-end;
    padding: 10px;
    border-top: solid thin #ccc;
`;

export const CancelButton = styled.button`
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    min-height: 40px;
    padding: 0 16px;
    border: none;
    border-radius: 4px;
    background-color: ${({ $restaurar }) => ($restaurar ? '#2e7d32' : '#c62828')};
    color: #fff;
    font: inherit;
    font-weight: bold;
    cursor: pointer;

    &:disabled {
        opacity: 0.6;
        cursor: default;
    }

    ${mobile} {
        width: 100%;
    }
`;

export const Teams = styled.strong`
    display: flex;
    justify-content: center;
    margin: 3px auto;
    padding: 5px 0;
`;
