import styled from 'styled-components';
import { mobile } from '../../theme/tokens';

// mesmo visual do cartão do Mercado Pago: situação, dados, botões e nota
export { Actions, Button, Chip, Data, Details, Loading, Note } from '../MercadoPagoCard/styles';

// QR code com as instruções ao lado (embaixo, no celular)
export const QrArea = styled.div`
    display: flex;
    align-items: center;
    gap: 20px;

    img {
        width: 264px;
        height: 264px;
        padding: 8px;
        border-radius: 6px;
        background-color: #fff;
    }

    ${mobile} {
        flex-direction: column;

        img {
            width: min(264px, 100%);
            height: auto;
        }
    }
`;

export const Steps = styled.ol`
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin: 0;
    padding-left: 20px;
    color: ${({ theme }) => theme.texto_principal};
    font-size: 0.9em;
`;
