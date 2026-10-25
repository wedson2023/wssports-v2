import styled from 'styled-components';

// aviso de seção só de leitura (ex.: limite de notificações para o Supervisor)
export const Notice = styled.p`
    margin: 0;
    padding: 10px 12px;
    border-left: solid 3px ${({ theme }) => theme.principal};
    border-radius: 4px;
    background-color: ${({ theme }) => theme.superficie_barra};
    color: ${({ theme }) => theme.texto_secundario};
    font-size: 0.85em;
`;
