import styled from 'styled-components';

import { mobile } from '../../theme/tokens';

// seções e campo de seleção iguais aos do cadastro de promoção (spec 020)
export { Group, Select, Title } from '../PromotionFormModal/styles';

// grade das opções: nome, cotação, ativo e remover; no celular, nome numa linha e o resto embaixo
const colunas = 'minmax(0, 2fr) minmax(0, 1fr) minmax(0, 1fr) 44px';

export const OptionsHead = styled.div`
    display: grid;
    grid-template-columns: ${colunas};
    gap: 10px;
    color: ${({ theme }) => theme.texto_secundario};
    font-size: 0.8em;

    ${mobile} {
        display: none;
    }
`;

export const OptionRow = styled.div`
    display: grid;
    grid-template-columns: ${colunas};
    grid-template-areas: "nome cotacao ativo remover";
    gap: 10px;
    align-items: start;

    .so_celular {
        display: none;
    }

    ${mobile} {
        grid-template-columns: minmax(0, 1fr) minmax(0, 1fr) 44px;
        grid-template-areas: "nome nome nome" "cotacao ativo remover";
        padding-bottom: 12px;
        border-bottom: solid 1px ${({ theme }) => theme.superficie_titulo};

        .so_celular {
            display: block;
        }
    }
`;

export const OptionCell = styled.div`
    display: flex;
    flex-direction: column;
    gap: 6px;
    min-width: 0;
    grid-area: ${({ $area }) => $area};
`;

export const RemoveButton = styled.button`
    grid-area: remover;
    align-self: end;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 44px;
    height: 42px;
    border: none;
    border-radius: 4px;
    background: transparent;
    color: #e53935;
    cursor: pointer;

    &:disabled {
        opacity: 0.4;
        cursor: default;
    }
`;

export const AddButton = styled.button`
    display: inline-flex;
    align-items: center;
    align-self: flex-start;
    gap: 6px;
    min-height: 40px;
    padding: 0 12px;
    border: dashed 1px ${({ theme }) => theme.principal};
    border-radius: 4px;
    background: transparent;
    color: ${({ theme }) => theme.principal};
    font: inherit;
    cursor: pointer;
`;

// opção anulada: só consulta (FR-012)
export const Badge = styled.span`
    align-self: flex-start;
    padding: 2px 6px;
    border-radius: 0.3em;
    background-color: #6d4c41;
    color: #fff;
    font-size: 0.75em;
`;
