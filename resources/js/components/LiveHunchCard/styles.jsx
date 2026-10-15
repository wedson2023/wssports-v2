import styled, { css, keyframes } from 'styled-components';

const destacar = keyframes`
    0% { transform: scale(1); }
    30% { transform: scale(1.25); }
    100% { transform: scale(1); }
`;

export const Card = styled.article`
    background-color: ${({ theme }) => theme.superficie_titulo};
    border-radius: 10px;
    padding: 12px 14px;
    display: flex;
    flex-direction: column;
    gap: 10px;
    color: ${({ theme }) => theme.texto_principal};
    opacity: ${({ $cancelado }) => ($cancelado ? 0.6 : 1)};

    .sr-only {
        position: absolute;
        width: 1px;
        height: 1px;
        overflow: hidden;
        clip: rect(0 0 0 0);
        white-space: nowrap;
    }
`;

export const Meta = styled.div`
    display: flex;
    justify-content: space-between;
    gap: 8px;
    font-size: 11px;
    color: ${({ theme }) => theme.texto_apagado};

    span:first-child {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    span:last-child {
        flex-shrink: 0;
        font-variant-numeric: tabular-nums;
    }
`;

export const Teams = styled.div`
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto minmax(0, 1fr);
    align-items: center;
    gap: 8px;
`;

export const Team = styled.div`
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 4px;
    min-width: 0;
    text-align: center;
`;

export const Shield = styled.img`
    width: 32px;
    height: 32px;
    object-fit: contain;
`;

export const TeamName = styled.span`
    font-size: 13px;
    line-height: 1.2;
    max-width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
`;

export const ScoreBox = styled.div`
    display: flex;
    flex-direction: column;
    align-items: center;
    min-width: 84px;
`;

export const Score = styled.span`
    font-size: 26px;
    font-weight: 700;
    font-variant-numeric: tabular-nums;
    color: ${({ $gol, theme }) => ($gol ? theme.principal : theme.texto_principal)};

    ${({ $gol }) => $gol && css`
        animation: ${destacar} 0.8s ease-out 2;
    `}

    @media (prefers-reduced-motion: reduce) {
        animation: none;
    }
`;

export const Clock = styled.span`
    font-size: 11px;
    color: ${({ theme }) => theme.texto_secundario};
    font-variant-numeric: tabular-nums;
    text-align: center;
`;

export const Market = styled.div`
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    padding-top: 8px;
    border-top: 1px solid ${({ theme }) => theme.superficie_barra};

    strong {
        display: block;
        font-size: 13px;
        font-weight: 500;
    }
`;

export const Odds = styled.span`
    font-size: 12px;
    color: ${({ theme }) => theme.texto_secundario};
    font-variant-numeric: tabular-nums;
`;

export const Badge = styled.span`
    flex-shrink: 0;
    display: inline-flex;
    align-items: center;
    gap: 3px;
    padding: 3px 8px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 500;
    color: #fff;
    background-color: ${({ $cor }) => $cor};

    .material-icons {
        font-size: 14px;
    }
`;

export const Extras = styled.div`
    display: flex;
    flex-wrap: wrap;
    gap: 4px 14px;
    font-size: 12px;
    color: ${({ theme }) => theme.texto_secundario};
    font-variant-numeric: tabular-nums;
`;
