import styled from 'styled-components';
import { mobile } from '../../theme/tokens';

// vermelho do risco (mesmo da barra de prêmio na tabela da Combinação de risco)
const vermelho = '#c62828';

// modals/Combination do antigo: centralizado no desktop, tela cheia no celular
export const Container = styled.div`
    position: fixed;
    top: 50%;
    left: 50%;
    z-index: 46;
    display: flex;
    flex-direction: column;
    width: 520px;
    max-height: 85vh;
    max-height: 85dvh;
    border-radius: 6px;
    overflow: hidden;
    background: #fff;
    color: #333;
    box-shadow: 0 12px 40px rgba(0, 0, 0, 0.4);
    transform: translate(-50%, ${({ $visivel }) => ($visivel ? '-50%' : '-45%')});
    transition-duration: 0.25s;
    opacity: ${({ $visivel }) => ($visivel ? 1 : 0)};
    visibility: ${({ $visivel }) => ($visivel ? 'visible' : 'hidden')};

    ${mobile} {
        width: 100%;
        height: 100dvh;
        max-height: 100dvh;
        border-radius: 0;
    }
`;

export const Header = styled.header`
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px 16px;
    background: #222;
    color: #fff;
`;

export const HeaderIcon = styled.i.attrs(() => ({ className: 'material-icons', 'aria-hidden': true }))`
    display: flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background-color: rgba(249, 168, 37, 0.18);
    color: #f9a825;
    font-size: 22px;
`;

export const HeaderText = styled.div`
    flex: 1;
    min-width: 0;
`;

export const Title = styled.h3`
    margin: 0;
    font-size: 1.05em;
    font-weight: 500;
`;

export const Subtitle = styled.span`
    color: #aaa;
    font-size: 0.8em;
`;

export const Close = styled.i.attrs(() => ({ className: 'material-icons', role: 'button' }))`
    padding: 4px;
    border-radius: 50%;
    color: #ddd;
    cursor: pointer;

    &:hover {
        background-color: rgba(255, 255, 255, 0.12);
        color: #fff;
    }
`;

export const Body = styled.div`
    display: flex;
    flex-direction: column;
    gap: 18px;
    padding: 16px;
    overflow-y: auto;
`;

// resumo do risco: bilhetes, apostado e o prêmio em risco em destaque
export const Stats = styled.div`
    display: grid;
    grid-template-columns: 1fr 1fr 1.3fr;
    gap: 8px;

    ${mobile} {
        grid-template-columns: 1fr 1fr;

        & > :last-child {
            grid-column: 1 / -1;
        }
    }
`;

export const Stat = styled.div`
    display: flex;
    flex-direction: column;
    gap: 4px;
    padding: 10px 12px;
    border-radius: 6px;
    background-color: ${({ $risco }) => ($risco ? vermelho : '#f4f4f4')};
    color: ${({ $risco }) => ($risco ? '#fff' : '#333')};
`;

export const StatLabel = styled.span`
    font-size: 0.75em;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    opacity: 0.8;
`;

export const StatValue = styled.strong`
    font-size: 1.2em;
    font-weight: 500;
    white-space: nowrap;
`;

export const Section = styled.section`
    display: flex;
    flex-direction: column;
    gap: 6px;
`;

export const SectionTitle = styled.h4`
    margin: 0 0 2px;
    color: #777;
    font-size: 0.75em;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.4px;
`;

// palpite em comum: número, jogo e a opção numa etiqueta
export const Game = styled.div`
    display: grid;
    grid-template-columns: 24px 1fr auto;
    align-items: center;
    gap: 10px;
    padding: 8px 10px;
    border-radius: 6px;
    background-color: #f8f8f8;
    font-size: 0.9em;
`;

export const GameIndex = styled.span`
    display: flex;
    align-items: center;
    justify-content: center;
    width: 22px;
    height: 22px;
    border-radius: 50%;
    background-color: #e4e4e4;
    color: #555;
    font-size: 0.8em;
    font-weight: 500;
`;

export const GameName = styled.span`
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
`;

export const Option = styled.span`
    padding: 3px 10px;
    border-radius: 12px;
    background-color: ${({ theme }) => theme.principal};
    color: #fff;
    font-size: 0.8em;
    font-weight: 500;
    white-space: nowrap;
`;

// bilhete: cartão inteiro clicável que abre o bilhete completo
export const Ticket = styled.button`
    display: grid;
    grid-template-columns: 36px 1fr auto auto;
    align-items: center;
    gap: 12px;
    width: 100%;
    padding: 10px 12px;
    border: solid 1px #e6e6e6;
    border-radius: 6px;
    background: #fff;
    color: #333;
    font: inherit;
    text-align: left;
    cursor: pointer;
    transition: border-color 0.2s, box-shadow 0.2s;

    &:hover {
        border-color: ${({ theme }) => theme.principal};
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    }

    ${mobile} {
        grid-template-columns: 36px 1fr auto;

        & > :nth-child(3) {
            grid-column: 2 / 3;
        }
    }
`;

export const TicketIcon = styled.i.attrs(() => ({ className: 'material-icons', 'aria-hidden': true }))`
    display: flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    border-radius: 6px;
    background-color: #f1f1f1;
    color: ${({ theme }) => theme.principal};
`;

export const TicketInfo = styled.div`
    display: flex;
    flex-direction: column;
    gap: 2px;
    min-width: 0;
`;

export const TicketCode = styled.strong`
    font-family: 'Roboto Mono', monospace;
    font-weight: 500;
    letter-spacing: 1px;
`;

export const TicketPeople = styled.span`
    color: #777;
    font-size: 0.8em;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
`;

export const TicketValues = styled.div`
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 2px;
    color: #777;
    font-size: 0.78em;
    white-space: nowrap;

    strong {
        color: #333;
        font-weight: 500;
    }

    ${mobile} {
        align-items: flex-start;
    }
`;

export const TicketAction = styled.span`
    display: flex;
    align-items: center;
    color: ${({ theme }) => theme.principal};
    font-size: 0.8em;
    font-weight: 500;
    white-space: nowrap;

    i {
        font-size: 20px;
    }

    ${mobile} {
        grid-row: 1 / 3;
        grid-column: 3;
    }
`;
