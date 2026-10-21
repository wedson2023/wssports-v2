import styled from 'styled-components';
import { mobile } from '../../theme/tokens';

const fundo = ({ theme }) => (theme.modo === 'escuro' ? '#1b1b1b' : '#ffffff');
const borda_campo = ({ theme }) => (theme.modo === 'escuro' ? '#3a3a3a' : '#a8a8a8');
const borda = ({ theme }) => (theme.modo === 'escuro' ? '#3a3a3a' : '#e2e2e2');

export const Picker = styled.div`
    position: relative;
    flex: 1;
    min-width: 240px;

    ${mobile} {
        flex: 1 1 100%;
        min-width: 0;
    }
`;

// campo com o público escolhido; abre a lista
export const Current = styled.button`
    display: flex;
    align-items: center;
    gap: 8px;
    width: 100%;
    min-height: 44px;
    padding: 0 12px;
    border: solid 1px ${borda_campo};
    border-radius: 6px;
    background-color: ${fundo};
    color: ${({ theme }) => theme.texto_principal};
    font: inherit;
    text-align: left;
    cursor: pointer;

    i:first-child {
        color: ${({ theme }) => theme.principal};
    }

    span {
        flex: 1;
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
`;

export const Dropdown = styled.div`
    position: absolute;
    top: calc(100% + 4px);
    right: 0;
    left: 0;
    z-index: 20;
    display: flex;
    flex-direction: column;
    max-height: min(420px, 60dvh);
    overflow-y: auto;
    border: solid 1px ${borda_campo};
    border-radius: 8px;
    background-color: ${fundo};
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.4);
`;

export const SearchBox = styled.label`
    position: sticky;
    top: 0;
    z-index: 1;
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 0 12px;
    border-bottom: solid 1px ${borda};
    background-color: ${fundo};
    color: ${({ theme }) => theme.texto_secundario};
`;

export const Search = styled.input`
    flex: 1;
    min-width: 0;
    padding: 12px 0;
    border: none;
    background: transparent;
    color: ${({ theme }) => theme.texto_principal};
    font: inherit;

    &:focus {
        outline: none;
    }
`;

export const Section = styled.div`
    display: flex;
    flex-direction: column;
    padding: 6px 0;
    border-bottom: solid 1px ${borda};

    > small {
        padding: 4px 12px;
        color: ${({ theme }) => theme.texto_apagado};
        font-size: 0.72em;
        font-weight: 500;
        text-transform: uppercase;
    }
`;

export const Option = styled.button`
    display: flex;
    align-items: center;
    gap: 10px;
    min-height: 44px;
    padding: 6px 12px;
    border: none;
    border-left: solid 3px ${({ theme, $ativo }) => ($ativo ? theme.principal : 'transparent')};
    background: transparent;
    color: ${({ theme }) => theme.texto_principal};
    font: inherit;
    font-size: 0.88em;
    text-align: left;
    cursor: pointer;

    i {
        color: ${({ theme }) => theme.texto_secundario};
        font-size: 20px;
    }

    span {
        display: flex;
        flex-direction: column;
        min-width: 0;
    }

    small {
        overflow: hidden;
        color: ${({ theme }) => theme.texto_apagado};
        font-size: 0.85em;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    &:hover {
        background-color: ${({ theme }) => (theme.modo === 'escuro' ? '#262626' : '#f3f3f3')};
    }
`;

export const Empty = styled.span`
    padding: 10px 12px;
    color: ${({ theme }) => theme.texto_apagado};
    font-size: 0.82em;
`;
