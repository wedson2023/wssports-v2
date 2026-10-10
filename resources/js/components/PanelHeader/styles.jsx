import styled from 'styled-components';
import { mobile } from '../../theme/tokens';

// Header de main/styles.js (títulos "Menu" e "Cupom")
export const Container = styled.div`
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 10px;
    background-color: ${({ theme }) => theme.superficie_titulo};
    text-align: center;
    color: ${({ theme }) => theme.texto_principal};

    i {
        display: none;
    }

    ${mobile} {
        margin-bottom: 0;
        justify-content: space-between;

        i {
            display: block;
            float: right;
            padding-left: 10px;
        }

        i:hover {
            opacity: 0.8;
        }
    }
`;

export const Title = styled.span`
    font-size: 13px;
`;

export const CloseIcon = styled.i.attrs(() => ({ className: 'material-icons' }))`
    user-select: none;
    color: ${({ theme }) => theme.principal};
    font-size: 25px;
    cursor: pointer;
`;
