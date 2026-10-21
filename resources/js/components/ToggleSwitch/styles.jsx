import styled from 'styled-components';

export const Track = styled.button`
    position: relative;
    flex-shrink: 0;
    width: 42px;
    height: 24px;
    padding: 0;
    border: none;
    border-radius: 12px;
    background-color: ${({ theme, $ligado }) => ($ligado ? theme.principal : (theme.modo === 'escuro' ? '#555' : '#c7c7c7'))};
    opacity: ${({ $carregando }) => ($carregando ? 0.6 : 1)};
    cursor: pointer;
    transition: background-color 0.2s;

    &:disabled {
        opacity: 0.4;
        cursor: not-allowed;
    }

    &:focus-visible {
        outline: solid 2px ${({ theme }) => theme.principal};
        outline-offset: 2px;
    }
`;

export const Knob = styled.span`
    position: absolute;
    top: 3px;
    left: ${({ $ligado }) => ($ligado ? '21px' : '3px')};
    width: 18px;
    height: 18px;
    border-radius: 50%;
    background-color: #fff;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.3);
    transition: left 0.2s;
`;
