import styled from 'styled-components';

// Header de screens/rules/styles.js: bordas esquerda e inferior de 2px na cor do tema
export const Header = styled.header`
    border-bottom: solid 2px ${({ theme }) => theme.principal};
    border-left: solid 2px ${({ theme }) => theme.principal};
    padding: 5px;
    margin-bottom: 15px;
    margin-top: 25px;
    font-size: 0.9em;
    font-weight: 500;
    text-transform: uppercase;
    display: flex;
    align-items: center;

    i {
        color: ${({ theme }) => theme.principal};
        margin-right: 10px;
        font-size: 1.3em;
    }
`;

// linha "RÓTULO: texto" dos mercados; espaco_antes reproduz os <br /> do antigo
export const RuleLine = styled.p`
    font-size: 0.9em;
    margin-bottom: 16px;
    margin-top: ${({ $espaco_antes }) => ($espaco_antes ? '28px' : 0)};
`;
