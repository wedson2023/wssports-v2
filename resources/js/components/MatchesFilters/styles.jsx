import styled from 'styled-components';

import { Panel as PanelBase } from '../ReceivedBonusFilters/styles';

// mesma grade de campos, seleção e botões do filtro dos bônus recebidos
export { Actions, Cell, ClearButton, Select } from '../ReceivedBonusFilters/styles';

// numa linha: dia, campeonato (mais largo), situação, só manuais e os botões; em "Escolher data",
// o campo da data embaixo do dia. Na aba Campeonatos: país, situação, favoritos, manuais e botões
export const Panel = styled(PanelBase)`
    grid-template-columns: ${({ $campeonatos }) => ($campeonatos
        ? 'repeat(4, minmax(0, 1fr)) auto'
        : 'minmax(0, 1fr) minmax(0, 2fr) minmax(0, 1fr) minmax(0, 1fr) auto')};
    grid-template-areas: ${({ $campeonatos, $com_data }) => {
        if ($campeonatos) return '"pais situacao favorito manual acoes"';

        return $com_data ? '"dia campeonato situacao manual acoes" "data . . . ."' : '"dia campeonato situacao manual acoes"';
    }};
`;
