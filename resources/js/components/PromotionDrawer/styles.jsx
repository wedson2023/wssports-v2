import styled from 'styled-components';

// mesma gaveta do detalhe do cliente (spec 019)
export {
    Actions, Body, Button, Container, Data, DataItem, Loading,
} from '../ClientDrawer/styles';

// cartões de uso, dois por linha na largura da gaveta
export const Cards = styled.div`
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
`;

// título de cada grupo de campos
export const Title = styled.h3`
    margin: 4px 0 0;
    padding-bottom: 6px;
    border-bottom: solid 1px ${({ theme }) => theme.superficie_titulo};
    color: ${({ theme }) => theme.texto_secundario};
    font-size: 0.85em;
    font-weight: 500;
    text-transform: uppercase;
`;

// barra do progresso do estorno geral, na cor do tema
export const Progress = styled.progress`
    width: 100%;
    height: 10px;
    accent-color: ${({ theme }) => theme.principal};
`;
