import styled from 'styled-components';

import { Panel as PanelBase } from '../ReceivedBonusFilters/styles';

// mesma grade de campos, seleção e botões do filtro dos bônus recebidos
export { Actions, Cell, ClearButton, Select } from '../ReceivedBonusFilters/styles';

// numa linha: situação e os botões
export const Panel = styled(PanelBase)`
    grid-template-columns: minmax(0, 260px) 1fr auto;
    grid-template-areas: "situacao . acoes";
`;
