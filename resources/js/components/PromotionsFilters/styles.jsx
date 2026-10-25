import styled from 'styled-components';

import { mobile } from '../../theme/tokens';
import { Panel as PanelBase } from '../ReceivedBonusFilters/styles';

// mesma grade de campos e botões do filtro dos bônus recebidos; chips do filtro de clientes
export { Actions, Cell, ClearButton, Select } from '../ReceivedBonusFilters/styles';
export { Chip, Chips } from '../ClientsFilters/styles';

// situação, modalidade e os botões na primeira linha; embaixo, categoria e afiliado, cada um na
// linha toda
export const Panel = styled(PanelBase)`
    grid-template-columns: minmax(0, 1fr) minmax(0, 1fr) minmax(0, 1fr) auto;
    grid-template-areas:
        "situacao_promocao modalidade . acoes"
        "categoria categoria categoria categoria"
        "afiliado afiliado afiliado afiliado";
`;

// chips do afiliado e, em "De um afiliado", a busca ao lado
export const AffiliateRow = styled.div`
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px 12px;

    ${mobile} {
        flex-direction: column;
        align-items: stretch;
    }
`;

export const AffiliateSearch = styled.div`
    flex: 0 1 380px;
    min-width: 0;
`;
