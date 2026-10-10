import styled from 'styled-components';
import { mobile } from '../../theme/tokens';

// section.regras de screens/rules/styles.js, na largura de leitura aprovada na spec 005; branca
// nos dois modos, como no sistema antigo
export const Container = styled.section`
    max-width: 760px;
    margin: 0 auto 25px;
    padding: 15px 24px;
    background: #fff;
    border-radius: 12px;
    color: #333;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);

    p {
        font-size: 0.9em;
        line-height: 1.6;
        margin-bottom: 16px;
    }

    ${mobile} {
        margin: 0 12px 20px;
        padding: 12px 16px;
    }
`;

export const Title = styled.h6`
    display: block;
    text-align: center;
    color: #333;
    font-size: 11px;
    font-weight: 500;
    text-transform: uppercase;
    margin-bottom: 10px;
`;
