import styled from 'styled-components';
import { mobile } from '../../theme/tokens';

const fundo_cartao = ({ theme }) => (theme.modo === 'escuro' ? '#1b1b1b' : '#ffffff');
const borda = ({ theme }) => (theme.modo === 'escuro' ? '#3a3a3a' : '#e2e2e2');

// cartão da prévia: coluna fixa ao rolar no computador; faixa fixa embaixo no celular
export const Card = styled.aside`
    position: sticky;
    top: 0;
    display: flex;
    flex-direction: column;
    border: solid 1px ${borda};
    border-radius: 8px;
    background-color: ${fundo_cartao};
    color: ${({ theme }) => theme.texto_principal};
    overflow: hidden;

    ${mobile} {
        position: fixed;
        top: auto;
        right: 0;
        /* acima da barra de salvar quando ela aparece */
        bottom: ${({ $acima_da_barra }) => ($acima_da_barra ? '61px' : '0')};
        left: 0;
        z-index: 30;
        max-height: ${({ $expandida }) => ($expandida ? '70dvh' : 'auto')};
        border-radius: 12px 12px 0 0;
        border-bottom: none;
        box-shadow: 0 -6px 20px rgba(0, 0, 0, 0.35);
    }
`;

// linha resumida do celular ("Casa 2,00 → 1,80"), toca para expandir
export const Strip = styled.button`
    display: none;

    ${mobile} {
        display: flex;
        align-items: center;
        gap: 10px;
        min-height: 52px;
        padding: 0 16px;
        border: none;
        background-color: ${({ theme }) => theme.principal};
        color: #fff;
        font: inherit;
        font-weight: 500;
        text-align: left;

        span {
            flex: 1;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
    }
`;

export const Body = styled.div`
    display: flex;
    flex-direction: column;
    gap: 12px;
    padding: 14px;

    ${mobile} {
        display: ${({ $expandida }) => ($expandida ? 'flex' : 'none')};
        overflow-y: auto;
    }
`;

export const Header = styled.div`
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 8px;
`;

export const Title = styled.h4`
    display: flex;
    align-items: center;
    gap: 6px;
    margin: 0;
    font-size: 0.95em;
    font-weight: 500;

    i {
        color: ${({ theme }) => theme.principal};
        font-size: 22px;
    }
`;

export const Switch = styled.button`
    min-height: 36px;
    padding: 0 12px;
    border: solid 1px ${({ theme }) => (theme.modo === 'escuro' ? '#3a3a3a' : '#a8a8a8')};
    border-radius: 6px;
    background: transparent;
    color: ${({ theme }) => theme.texto_principal};
    font: inherit;
    font-size: 0.8em;
    cursor: pointer;
`;

export const GameList = styled.div`
    display: flex;
    flex-direction: column;
    max-height: 240px;
    overflow-y: auto;
    border: solid 1px ${borda};
    border-radius: 6px;

    > span {
        padding: 12px;
        color: ${({ theme }) => theme.texto_apagado};
        font-size: 0.85em;
    }
`;

export const GameButton = styled.button`
    display: flex;
    flex-direction: column;
    gap: 2px;
    padding: 10px 12px;
    border: none;
    border-bottom: solid 1px ${borda};
    background: transparent;
    color: ${({ theme }) => theme.texto_principal};
    font: inherit;
    font-size: 0.85em;
    text-align: left;
    cursor: pointer;

    small {
        color: ${({ theme }) => theme.texto_apagado};
    }

    &:hover {
        background-color: ${({ theme }) => (theme.modo === 'escuro' ? '#262626' : '#f3f3f3')};
    }
`;

export const Game = styled.div`
    display: flex;
    flex-direction: column;
    gap: 2px;
    font-size: 0.88em;

    small {
        color: ${({ theme }) => theme.texto_apagado};
    }
`;

export const Lines = styled.div`
    display: flex;
    flex-direction: column;
    border-top: solid 1px ${borda};
`;

export const Line = styled.div`
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 8px;
    padding: 8px 0;
    border-bottom: solid 1px ${borda};
    color: ${({ theme, $destaque }) => ($destaque ? theme.texto_principal : theme.texto_secundario)};
    font-size: 0.85em;

    span {
        display: flex;
        align-items: center;
        gap: 6px;
    }

    strong {
        color: ${({ theme }) => theme.texto_principal};
        font-weight: 500;
        text-align: right;
    }
`;

export const Pending = styled.em`
    padding: 1px 6px;
    border-radius: 8px;
    background-color: ${({ theme }) => theme.principal};
    color: #fff;
    font-size: 0.75em;
    font-style: normal;
`;

export const Result = styled.div`
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 12px;
    border-radius: 8px;
    background-color: ${({ theme }) => `${theme.principal}1f`};

    span {
        font-size: 0.85em;
    }

    strong {
        color: ${({ theme }) => theme.principal};
        font-size: 1.5em;
    }
`;

export const Warning = styled.p`
    margin: 0;
    color: ${({ theme }) => theme.texto_apagado};
    font-size: 0.8em;
`;
