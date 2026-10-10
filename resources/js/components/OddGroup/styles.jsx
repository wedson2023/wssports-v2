import styled from 'styled-components';
import { mobile } from '../../theme/tokens';

// Options e QtdeOptions de matches/styles.js
export const Container = styled.div`
    width: 420px;
    display: flex;
    align-items: center;
    justify-content: space-around;
    user-select: none;

    ${mobile} {
        width: 100%;
        justify-content: space-between;
    }
`;

export const MoreOptions = styled.small`
    display: flex;
    align-items: center;
    justify-content: center;
    color: ${({ $destacado, theme }) => ($destacado ? theme.principal : theme.texto_jogo)} !important;
    width: 15%;
    font-size: 14px;
    height: 40px;
    font-weight: bold;
    cursor: pointer;
`;
