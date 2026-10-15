import styled from 'styled-components';

export const Bar = styled.div`
    display: flex;
    gap: 3px;
    height: 8px;
`;

export const Segment = styled.span`
    flex: 1;
    border-radius: 4px;
    background-color: ${({ $cor }) => $cor};
    transition: background-color 0.3s;

    @media (prefers-reduced-motion: reduce) {
        transition: none;
    }
`;

export const Legend = styled.ul`
    display: flex;
    flex-wrap: wrap;
    gap: 4px 12px;
    margin-top: 8px;
    font-size: 12px;
    color: ${({ theme }) => theme.texto_secundario};
`;

export const LegendItem = styled.li`
    display: inline-flex;
    align-items: center;
    gap: 5px;
`;

export const Swatch = styled.span`
    width: 8px;
    height: 8px;
    border-radius: 2px;
    background-color: ${({ $cor }) => $cor};
`;
