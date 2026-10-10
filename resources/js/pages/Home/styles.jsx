import styled from 'styled-components';
import { mobile } from '../../theme/tokens';

// Content de main/styles.js: as três colunas ocupam a altura visível menos cabeçalho e barra de
// esportes (FR-003b)
export const Content = styled.div`
    display: flex;
    justify-content: space-between;

    @media screen and (min-width: 901px) {
        & > div {
            height: calc(100vh - ${({ $com_barra }) => ($com_barra ? '118px' : '60px')});
            height: calc(100dvh - ${({ $com_barra }) => ($com_barra ? '118px' : '60px')});
        }
    }

    ${mobile} {
        & > div.lista_jogos {
            height: calc(100vh - ${({ $com_barra }) => ($com_barra ? '175px' : '105px')});
            height: calc(100dvh - ${({ $com_barra }) => ($com_barra ? '175px' : '105px')});
        }
    }
`;
