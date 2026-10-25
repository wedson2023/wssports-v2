import styled from 'styled-components';
import { mobile } from '../../theme/tokens';

export { Error, Retry } from '../../pages/PanelBets/styles';

// cartão de uma seção da tela Configurações; na grade, ocupa a altura da linha
export const Card = styled.section`
    display: flex;
    flex-direction: column;
    height: 100%;
    border: solid 1px ${({ theme }) => theme.superficie_titulo};
    border-radius: 8px;
    background-color: ${({ theme }) => theme.superficie_pais};
    overflow: hidden;
`;

// cabeçalho: ícone na cor do tema (sem fundo), título e a frase do que a seção controla; na
// sanfona, é o botão que abre e fecha
export const Header = styled.header`
    display: flex;
    align-items: center;
    gap: 12px;
    width: 100%;
    padding: 14px 18px;
    border: none;
    border-bottom: solid 1px ${({ $aberta, theme }) => ($aberta ? theme.superficie_titulo : 'transparent')};
    background: transparent;
    color: inherit;
    font: inherit;
    text-align: left;
    cursor: ${({ as }) => (as === 'button' ? 'pointer' : 'default')};

    > i {
        color: ${({ theme }) => theme.principal};
        font-size: 26px;
    }

    ${mobile} {
        padding: 14px 12px;
    }
`;

export const Texts = styled.div`
    display: flex;
    flex: 1;
    flex-direction: column;
    gap: 2px;
    min-width: 0;
`;

export const Title = styled.h2`
    margin: 0;
    color: ${({ theme }) => theme.texto_principal};
    font-size: 1em;
    font-weight: 500;
`;

export const Description = styled.p`
    margin: 0;
    color: ${({ theme }) => theme.texto_secundario};
    font-size: 0.82em;
`;

// seta da sanfona
export const Arrow = styled.i`
    align-self: center;
    color: ${({ theme }) => theme.texto_apagado};
    transform: rotate(${({ $aberta }) => ($aberta ? '180deg' : '0deg')});
    transition: transform 0.2s;
`;

export const Content = styled.div`
    display: flex;
    flex: 1;
    flex-direction: column;
    gap: 16px;
    padding: 18px;

    ${mobile} {
        padding: 14px 12px;
    }
`;

export const Loading = styled.div`
    display: flex;
    justify-content: center;
    padding: 24px 0;
`;
