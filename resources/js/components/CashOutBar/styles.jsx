import styled from 'styled-components';
import { mobile } from '../../theme/tokens';

// só no celular: no desktop o bloco de encerramento já fica visível na coluna da direita
export const Bar = styled.div`
    display: none;

    ${mobile} {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        position: fixed;
        left: 0;
        right: 0;
        bottom: 0;
        z-index: 40;
        padding: 10px 16px calc(10px + env(safe-area-inset-bottom));
        background-color: ${({ theme }) => theme.superficie_barra};
        border-top: 1px solid ${({ theme }) => theme.superficie_campeonato};
        box-shadow: 0 -4px 12px rgba(0, 0, 0, 0.25);
    }
`;

export const Label = styled.span`
    display: block;
    font-size: 11px;
    color: ${({ theme }) => theme.texto_secundario};
`;

export const Value = styled.strong`
    font-size: 20px;
    font-variant-numeric: tabular-nums;
    color: ${({ theme }) => theme.texto_principal};
`;

export const Button = styled.button`
    height: 44px;
    padding: 0 18px;
    border: 0;
    border-radius: 6px;
    font-size: 15px;
    font-weight: 500;
    color: #fff;
    background-color: ${({ theme }) => theme.principal};
    cursor: pointer;
`;
