import styled from 'styled-components';
import { mobile } from '../../theme/tokens';

export { Actions, Button, Note, Warning } from '../MercadoPagoCard/styles';
export { Select } from '../SettingsForm/styles';

export const Title = styled.h3`
    margin: 8px 0 0;
    color: ${({ theme }) => theme.texto_principal};
    font-size: 0.95em;
    font-weight: 500;
`;

export const List = styled.div`
    display: flex;
    flex-direction: column;
    gap: 8px;
`;

// mensagem com o destinatário à esquerda e a escolha do canal à direita; embaixo, no celular
export const Row = styled.div`
    display: grid;
    grid-template-columns: minmax(0, 1fr) 160px;
    align-items: center;
    gap: 12px;
    padding: 8px 12px;
    border-radius: 4px;
    background-color: ${({ theme }) => theme.superficie_barra};

    ${mobile} {
        grid-template-columns: minmax(0, 1fr);
        gap: 6px;
    }
`;

export const Name = styled.label`
    display: flex;
    flex-direction: column;
    color: ${({ theme }) => theme.texto_principal};
    font-size: 0.9em;

    span {
        color: ${({ theme }) => theme.texto_apagado};
        font-size: 0.85em;
    }
`;
