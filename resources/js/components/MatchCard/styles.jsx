import styled from 'styled-components';
import { mobile } from '../../theme/tokens';

// Item de matches/styles.js: linhas alternadas (a 1ª linha vem depois do cabeçalho do campeonato)
export const Item = styled.div`
    display: flex;
    flex-direction: row;
    justify-content: space-between;
    align-items: center;
    padding: 3px 5px;
    background: ${({ theme }) => theme.fundo_lista};

    &:nth-child(odd) {
        background: ${({ theme }) => theme.superficie_jogo};
    }

    ${mobile} {
        flex-direction: column;
        padding: 5px 7px;
    }
`;

export const Match = styled.div`
    display: flex;
    flex: 1;
    color: ${({ theme }) => theme.texto_jogo};
    align-items: center;

    ${mobile} {
        width: 100%;
        margin-bottom: 5px;
    }
`;

export const Teams = styled.div`
    flex-direction: column;
    display: flex;
    flex: 1;
`;

export const Team = styled.div`
    display: flex;
    align-items: center;
    padding: 2px 0;
`;

// escudo com tamanho fixo: se a imagem falhar, o espaço continua o mesmo
export const Shield = styled.img`
    width: 22px;
    height: 22px;
    margin-right: 15px;
`;

export const Time = styled.div`
    width: 70px;
    padding: 0 5px;
    display: flex;
    align-items: center;
    justify-content: space-around;
    font-weight: 500;

    & i, & div {
        color: ${({ $perto, theme }) => ($perto ? theme.principal : theme.texto_jogo)};
    }
`;

export const Clock = styled.i.attrs(() => ({ className: 'material-icons' }))`
    font-size: 17px;
`;

export const Cron = styled.div`
    width: 70px;
    padding: 0 5px;
    display: flex;
    align-items: center;
    justify-content: space-around;
    font-weight: 500;
`;

// ao vivo: placar e período (Placar, PeriodMatch e TextPeriod de matches/styles.js)
export const Score = styled.small`
    background: red;
    border-radius: 0.3em;
    padding: 5px 15px;
    margin: 0 15px;
    color: #fff;
    font-weight: 500;
    cursor: default;
`;

export const Period = styled.div`
    display: flex;
    align-items: center;

    i {
        margin-right: 10px;
        font-size: 22px;
        color: ${({ theme }) => theme.texto_jogo};
    }
`;

export const PeriodText = styled.span`
    font-weight: 500;
    font-size: 0.9em;
`;
