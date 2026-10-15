import styled, { css, keyframes } from 'styled-components';

import { cores_estado } from '../../utils/live_states';

const pulsar = keyframes`
    0% { box-shadow: 0 0 0 0 rgba(31, 164, 91, 0.6); }
    70% { box-shadow: 0 0 0 6px rgba(31, 164, 91, 0); }
    100% { box-shadow: 0 0 0 0 rgba(31, 164, 91, 0); }
`;

export const Container = styled.div`
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    color: ${({ theme }) => theme.texto_secundario};
`;

export const Dot = styled.span`
    width: 8px;
    height: 8px;
    border-radius: 50%;
    flex-shrink: 0;
    background-color: ${({ $ao_vivo }) => ($ao_vivo ? cores_estado.ganhando : cores_estado.aguardando)};

    ${({ $ao_vivo }) => $ao_vivo && css`
        animation: ${pulsar} 2s infinite;
    `}

    @media (prefers-reduced-motion: reduce) {
        animation: none;
    }
`;

export const Updated = styled.span`
    color: ${({ theme }) => theme.texto_apagado};
    font-variant-numeric: tabular-nums;
`;
