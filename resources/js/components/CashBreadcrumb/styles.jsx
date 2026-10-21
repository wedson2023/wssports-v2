import styled from 'styled-components';
import { mobile } from '../../theme/tokens';

export const Container = styled.nav`
    min-width: 0;

    ol {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 2px;
        margin: 0;
        padding: 0;
        list-style: none;
    }

    li {
        display: flex;
        align-items: center;
        min-width: 0;
    }

    ${mobile} {
        ol {
            display: none;
        }
    }
`;

export const Part = styled.button`
    max-width: 220px;
    padding: 6px 4px;
    border: none;
    background: none;
    color: ${({ theme }) => theme.principal};
    font: inherit;
    font-size: 0.9em;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    cursor: pointer;

    &:hover {
        text-decoration: underline;
    }
`;

export const Current = styled.span`
    max-width: 260px;
    padding: 6px 4px;
    overflow: hidden;
    color: ${({ theme }) => theme.texto_principal};
    font-size: 0.9em;
    font-weight: bold;
    text-overflow: ellipsis;
    white-space: nowrap;
`;

export const Separator = styled.i.attrs(() => ({ className: 'material-icons' }))`
    color: ${({ theme }) => theme.texto_apagado};
    font-size: 18px;
`;

// só no celular: volta um nível
export const Back = styled.button`
    display: none;

    ${mobile} {
        display: flex;
        align-items: center;
        gap: 4px;
        width: 100%;
        min-height: 44px;
        padding: 0 4px;
        border: none;
        background: none;
        color: ${({ theme }) => theme.principal};
        font: inherit;
        font-size: 0.9em;
        cursor: pointer;

        ${Current} {
            margin-left: 6px;
            max-width: none;
            flex: 1;
            text-align: left;
        }
    }
`;
