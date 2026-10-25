import styled from 'styled-components';
import { mobile } from '../../theme/tokens';

// mesma base das telas do painel (specs 018 a 020): carregando
export { Loading } from '../PanelBets/styles';

// abaixo desta largura os cartões ficam numa coluna só
const estreita = '@media screen and (max-width: 1100px)';

// área da tela, na mesma largura das listas do painel
export const Section = styled.section`
    display: flex;
    flex-direction: column;
    gap: 16px;
    max-width: 1250px;
    margin: 14px auto 0;
    padding: 0 14px 24px;

    ${mobile} {
        gap: 12px;
        padding: 0 8px 32px;
    }
`;

// abas do tamanho do texto no computador; no celular, com rolagem lateral sem quebrar o rótulo
export const TabsArea = styled.div`
    min-width: 0;

    [role='tablist'] {
        flex-wrap: nowrap;
        overflow-x: auto;
        scrollbar-width: none;
    }

    [role='tab'] {
        white-space: nowrap;
    }
`;

// frase que explica o que se configura na aba
export const Intro = styled.p`
    margin: 0;
    color: ${({ theme }) => theme.texto_secundario};
    font-size: 0.88em;
`;

// cartões da aba em duas colunas (uma no celular); os da mesma linha ficam da mesma altura
export const Grid = styled.div`
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    align-items: stretch;
    gap: 16px;

    > * {
        min-width: 0;
    }

    ${estreita} {
        grid-template-columns: minmax(0, 1fr);
        gap: 12px;
    }
`;

// duas colunas de cartões empilhados, cada cartão na altura do seu conteúdo (sem esticar e sobrar
// espaço vazio); uma coluna abaixo de 1100 px
export const Columns = styled.div`
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    align-items: start;
    gap: 16px;

    > div {
        display: flex;
        flex-direction: column;
        gap: 16px;
        min-width: 0;
    }

    ${estreita} {
        grid-template-columns: minmax(0, 1fr);
        gap: 12px;

        > div {
            gap: 12px;
        }
    }
`;

// cartão que ocupa a linha toda da grade
export const Wide = styled.div`
    grid-column: 1 / -1;
    min-width: 0;
`;

// seções em coluna (aba Operação, sanfona)
export const Stack = styled.div`
    display: flex;
    flex-direction: column;
    gap: 12px;
`;
