import styled from 'styled-components';

// não encolhe dentro de colunas flex (ex.: modal com conteúdo longo, que escondia as abas)
export const Tabs = styled.div`
    flex-shrink: 0;
    display: flex;
    gap: 4px;
    overflow-x: auto;
    border-bottom: solid 1px ${({ theme }) => theme.superficie_titulo};
    scrollbar-width: none;

    &::-webkit-scrollbar {
        display: none;
    }
`;

export const Tab = styled.button`
    display: flex;
    align-items: center;
    gap: 6px;
    flex-shrink: 0;
    padding: 10px 14px;
    border: none;
    border-bottom: solid 3px ${({ theme, $ativa }) => ($ativa ? theme.principal : 'transparent')};
    background: transparent;
    color: ${({ theme, $ativa }) => ($ativa ? theme.texto_principal : theme.texto_secundario)};
    font: inherit;
    font-size: 0.85em;
    font-weight: 500;
    text-transform: uppercase;
    cursor: pointer;

    i {
        font-size: 18px;
    }

    &:hover {
        color: ${({ theme }) => theme.texto_principal};
    }
`;

export const Count = styled.span`
    padding: 0 7px;
    border-radius: 10px;
    background-color: ${({ theme }) => theme.superficie_titulo};
    font-size: 0.85em;
`;
