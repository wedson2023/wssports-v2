import styled from 'styled-components';
import { mobile } from '../../theme/tokens';

// EventChampionships de matches/styles.js
export const Container = styled.header`
    background-color: ${({ theme }) => theme.superficie_titulo};
    padding: 10px;
    color: ${({ theme }) => theme.texto_principal};
    font-weight: 500;
    display: flex;
    justify-content: space-between;
    align-items: center;

    ${mobile} {
        font-size: 12px;
    }
`;
