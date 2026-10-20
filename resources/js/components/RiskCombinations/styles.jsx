import styled, { css, keyframes } from 'styled-components';

const piscar = keyframes`
    0% { background-color: rgba(249, 168, 37, 0.45); }
    100% { background-color: transparent; }
`;

// Table, Tr, Th e Td de screens/adm/dashboard/styles.js; rolagem horizontal só dentro da seção
export const Scroll = styled.div`
    width: 100%;
    overflow-x: auto;
`;

export const Table = styled.div`
    display: flex;
    flex-direction: column;
    min-width: 720px;
`;

export const Row = styled.div`
    display: grid;
    grid-template-columns: 2fr 1.5fr 70px 90px 1.4fr 40px;
    align-items: center;
    gap: 8px;
    padding: 8px 6px;
    border-bottom: solid 1px ${({ theme }) => theme.superficie_titulo};
    font-size: 0.85em;
    font-weight: ${({ $cabecalho }) => ($cabecalho ? 500 : 400)};

    ${({ $nova }) => $nova && css`
        animation: ${piscar} 60s ease-out;
        border-left: solid 4px #f9a825;
    `}
`;

export const HeadCell = styled.span`
    text-transform: uppercase;
    font-size: 0.85em;
    text-align: ${({ $estreita, $icone }) => ($estreita || $icone ? 'center' : 'left')};
`;

export const Cell = styled.div`
    display: flex;
    flex-direction: column;
    gap: 4px;
    min-width: 0;
    text-align: ${({ $estreita, $icone }) => ($estreita || $icone ? 'center' : 'left')};
    align-items: ${({ $icone }) => ($icone ? 'center' : 'stretch')};
`;

export const Lines = styled.div`
    display: flex;
    flex-direction: column;
    gap: 2px;
`;

export const Bar = styled.div`
    width: 100%;
    height: 6px;
    border-radius: 3px;
    background-color: ${({ theme }) => theme.superficie_titulo};
`;

export const BarFill = styled.div`
    height: 100%;
    border-radius: 3px;
    background-color: #c62828;
`;

export const Eye = styled.i.attrs(() => ({ className: 'material-icons', role: 'button' }))`
    color: ${({ theme }) => theme.principal};
    cursor: pointer;
`;
