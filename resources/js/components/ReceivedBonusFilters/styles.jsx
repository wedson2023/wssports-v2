import styled from 'styled-components';

import { Field } from '../CashModal';
import { Actions as ActionsBase, Panel as PanelBase } from '../ClientsFilters/styles';

// botão e campo de seleção iguais aos do filtro de clientes e do cadastro de promoção
export { ClearButton } from '../ClientsFilters/styles';
export { Select } from '../PromotionFormModal/styles';

// numa linha: promoção (mais larga), modalidade, período e os botões; no Personalizado, as datas
// embaixo, sob modalidade e período; o erro na largura toda
export const Panel = styled(PanelBase)`
    grid-template-columns: minmax(0, 2fr) minmax(0, 1fr) minmax(0, 1fr) auto;
    grid-template-areas: ${({ $personalizado }) => ($personalizado
        ? '"promocao modalidade periodo acoes" ". de ate ."'
        : '"promocao modalidade periodo acoes"')};
    align-items: end;

    > [role='alert'] {
        grid-column: 1 / -1;
    }
`;

// cada campo na sua área da grade (no modal do celular, um abaixo do outro)
export const Cell = styled(Field)`
    grid-area: ${({ $area }) => $area};
`;

// Limpar e Aplicar na mesma linha dos campos
export const Actions = styled(ActionsBase)`
    grid-area: acoes;
`;
