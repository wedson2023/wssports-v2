import styled from 'styled-components';

// mesma gaveta do detalhe do cliente (spec 019) e o mesmo título de grupo do detalhe da promoção
export {
    Actions, Body, Button, Container, Data, DataItem, Loading,
} from '../ClientDrawer/styles';
export { Title } from '../PromotionDrawer/styles';

// linha do tempo do bônus: um evento por linha, com a marca na cor do tema
export const Timeline = styled.ol`
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin: 0;
    padding: 0 0 0 14px;
    border-left: solid 2px ${({ theme }) => theme.superficie_titulo};
    list-style: none;
`;

export const Event = styled.li`
    position: relative;
    display: flex;
    flex-direction: column;
    gap: 2px;
    font-size: 0.9em;

    &::before {
        content: '';
        position: absolute;
        top: 5px;
        left: -20px;
        width: 10px;
        height: 10px;
        border-radius: 50%;
        background-color: ${({ theme }) => theme.principal};
    }

    > span {
        color: ${({ theme }) => theme.texto_secundario};
        overflow-wrap: anywhere;
    }
`;
