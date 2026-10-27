import styled from 'styled-components';

import { Panel as PanelBase } from '../ReceivedBonusFilters/styles';

// mesma grade de campos, seleção e botões do filtro dos bônus recebidos
export { Actions, Cell, ClearButton, Select } from '../ReceivedBonusFilters/styles';

// numa linha: situação, gerente, sem gerente, período e os botões; no Personalizado, as datas
// embaixo; campos que não se aplicam (aba ou função) deixam a área vazia
export const Panel = styled(PanelBase)`
    grid-template-columns: repeat(4, minmax(0, 1fr)) auto;
    grid-template-areas: ${({ $personalizado }) => ($personalizado
        ? '"situacao gerente sem_gerente periodo acoes" ". . de ate ."'
        : '"situacao gerente sem_gerente periodo acoes"')};
`;
