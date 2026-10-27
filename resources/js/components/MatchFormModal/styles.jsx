import styled from 'styled-components';

import { mobile } from '../../theme/tokens';

// seções e campo de seleção iguais aos do cadastro de promoção (spec 020); remover igual ao do placar
export { Group, Select, Title } from '../PromotionFormModal/styles';
export { Remove } from '../MatchResultModal/styles';

// cotações em grade de 3 colunas no computador e 2 no celular
export const Quotes = styled.div`
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 12px;

    ${mobile} {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
`;

// campo da cotação com o botão de remover ao lado
export const Quote = styled.div`
    display: flex;
    align-items: flex-end;
    gap: 4px;
    min-width: 0;

    > :first-child {
        flex: 1;
    }
`;
