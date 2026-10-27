import styled from 'styled-components';

import { GhostButton } from '../CashModal';
import { mobile } from '../../theme/tokens';

// mesmas seções do cadastro de promoção (spec 020)
export { Group, Title } from '../PromotionFormModal/styles';

// cada gol numa linha: jogador (mais largo), minuto, tempo e remover; no celular, um abaixo do outro
export const GoalRow = styled.div`
    display: grid;
    grid-template-columns: minmax(0, 2fr) minmax(0, 1fr) minmax(0, 1fr) auto;
    align-items: end;
    gap: 10px;

    ${mobile} {
        grid-template-columns: minmax(0, 1fr) auto;
    }
`;

export const Remove = styled.button`
    display: flex;
    align-items: center;
    justify-content: center;
    width: 42px;
    height: 42px;
    border: none;
    border-radius: 4px;
    background: transparent;
    color: #e53935;
    cursor: pointer;
`;

export const SmallButton = styled(GhostButton)`
    display: inline-flex;
    align-items: center;
    align-self: flex-start;
    gap: 6px;
`;
