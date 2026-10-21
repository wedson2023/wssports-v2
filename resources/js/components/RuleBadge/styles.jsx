import styled from 'styled-components';

// selo na cor do tema quando há ajuste; cinza quando não há
export const Badge = styled.span`
    display: inline-block;
    max-width: 100%;
    padding: 3px 9px;
    border-radius: 10px;
    background-color: ${({ theme, $vazio }) => {
        if ($vazio) return theme.modo === 'escuro' ? '#2b2b2b' : '#e6e6e6';
        return `${theme.principal}26`;
    }};
    color: ${({ theme, $vazio }) => ($vazio ? theme.texto_apagado : theme.texto_principal)};
    border: solid 1px ${({ theme, $vazio }) => ($vazio ? 'transparent' : `${theme.principal}80`)};
    font-size: 0.75em;
    font-weight: 500;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
`;
