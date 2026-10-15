import styled from 'styled-components';

export const Card = styled.section`
    background-color: ${({ theme }) => theme.superficie_titulo};
    border-radius: 10px;
    padding: 14px;
    display: flex;
    flex-direction: column;
    gap: 12px;
    color: ${({ theme }) => theme.texto_principal};
`;

export const Top = styled.div`
    display: flex;
    align-items: center;
    justify-content: space-between;
    min-height: 28px;
`;

export const Code = styled.h1`
    font-size: 14px;
    font-weight: 500;
    letter-spacing: 1px;
    color: ${({ theme }) => theme.texto_secundario};
`;

export const Header = styled.div`
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 10px;
`;

export const PrizeLabel = styled.span`
    display: block;
    font-size: 12px;
    color: ${({ theme }) => theme.texto_secundario};
`;

export const Prize = styled.strong`
    display: block;
    font-size: 28px;
    font-weight: 700;
    font-variant-numeric: tabular-nums;
    color: ${({ theme }) => theme.texto_principal};
`;

export const Status = styled.span`
    flex-shrink: 0;
    padding: 4px 10px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 500;
    color: #fff;
    background-color: ${({ $cor, theme }) => $cor ?? theme.texto_apagado};
`;

export const Data = styled.dl`
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px 12px;
`;

export const Item = styled.div`
    min-width: 0;
`;

export const Label = styled.dt`
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: ${({ theme }) => theme.texto_apagado};
`;

export const Value = styled.dd`
    font-size: 14px;
    font-variant-numeric: tabular-nums;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;

    small {
        font-size: 11px;
        color: ${({ theme }) => theme.texto_apagado};
    }
`;
