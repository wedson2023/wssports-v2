import styled, { css } from 'styled-components';
import { mobile } from '../../theme/tokens';

// Header, Icon, Wellcome e Logo de screens/credits/styles.js
export const Bar = styled.div`
    display: flex;
    align-items: center;
    justify-content: space-between;
`;

export const Icon = styled.span`
    color: ${({ theme }) => theme.principal};
    height: 45px;
    width: 45px;
    font-size: ${({ $menu }) => ($menu ? '25px' : '30px')};
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    user-select: none;

    ${({ $menu }) => $menu && css`
        opacity: 0;
        pointer-events: none;

        ${mobile} {
            opacity: 1;
            pointer-events: auto;
        }
    `}
`;

// sino e menu lado a lado, à direita (spec 010)
export const Actions = styled.div`
    display: flex;
    align-items: center;
`;

export const Welcome = styled.h4`
    color: ${({ theme }) => theme.texto_principal};
    margin-top: 5px;
    font-size: 15px;
`;

export const Logo = styled.img`
    display: block;
    margin: 10px auto;
    width: 100px;
`;
