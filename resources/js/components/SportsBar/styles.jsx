import styled from 'styled-components';
import { mobile } from '../../theme/tokens';

// ContainerSports de main/styles.js
export const Container = styled.div`
    width: 100%;
    padding: 8px 10px;
    overflow: auto;
    background-color: ${({ theme }) => theme.superficie_barra};

    ${mobile} {
        padding: 12px 10px;
    }
`;

export const Slide = styled.div`
    display: flex;
    width: 100%;
`;

// ItemSports: no mobile cada item ocupa 1/5 da largura útil (FR-012, R-17)
export const Item = styled.div`
    display: flex;
    flex-direction: column;
    align-items: center;
    flex: 1;
    cursor: pointer;
    transition-duration: 0.3s;

    i, strong {
        color: ${({ $ativo, theme }) => ($ativo ? theme.principal : theme.texto_secundario)};
    }

    &:hover {
        transform: scale(1.1);
    }

    &:hover i, &:hover strong {
        color: ${({ theme }) => theme.principal};
    }

    ${mobile} {
        flex: none;
        width: calc((100vw - 20px) / 5);
        padding-bottom: 5px;
        border-bottom: solid 2px ${({ $ativo, theme }) => ($ativo ? theme.principal : 'transparent')};
    }
`;

export const Icon = styled.i.attrs(() => ({ className: 'material-icons' }))`
    margin-bottom: 5px;
`;

export const Title = styled.strong`
    font-size: 0.75em;
    max-width: 15ch;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
`;
