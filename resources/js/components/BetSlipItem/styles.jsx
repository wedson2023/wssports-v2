import styled from 'styled-components';

// Hunches, HunchesTop, HunchesMatches, HunchesBottom, HunchesOption e HunchesOdd de main/styles.js
export const Container = styled.div`
    background: ${({ theme }) => theme.superficie_barra};

    &:nth-child(even) {
        background: ${({ theme }) => theme.superficie_cupom};
    }
`;

export const Top = styled.div`
    display: flex;
    flex-direction: row;
    align-items: center;
    justify-content: space-between;
    padding: 10px;
`;

export const Matches = styled.div`
    display: flex;
    margin: 0;
    flex-direction: column;
    color: ${({ theme }) => theme.texto_principal};

    strong {
        color: ${({ theme }) => theme.texto_secundario};
        font-weight: 500;
        font-size: 13px;
    }
`;

export const Bottom = styled.div`
    display: flex;
    align-items: center;
    flex-direction: row;
    justify-content: space-between;
    padding: 5px 10px;
    border-top: thin solid ${({ theme }) => theme.superficie_cupom};
`;

export const Option = styled.strong`
    text-align: center;
    margin: 0 5px;
    display: block;
    color: ${({ theme }) => theme.texto_principal};
    font-size: 12px;
`;

export const Odd = styled.strong`
    color: ${({ theme }) => theme.texto_principal};
    font-weight: 500;
    font-size: 12px;
`;

export const Icon = styled.i.attrs(() => ({ className: 'material-icons' }))`
    user-select: none;
    color: ${({ theme }) => theme.texto_principal};
    font-size: ${({ $tamanho }) => $tamanho ?? '25px'};
    cursor: pointer;
`;
