import styled from 'styled-components';
import { mobile } from '../../theme/tokens';

// Matches de main/styles.js: coluna central que rola (banner, busca, abas, jogos e rodapé); a
// classe permite à página ajustar a altura no mobile (era a classe "events" do antigo)
export const Container = styled.div.attrs(() => ({ className: 'lista_jogos' }))`
    flex: 1;
    display: flex;
    flex-direction: column;
    overflow-x: hidden;
    overflow-y: auto;
    -webkit-overflow-scrolling: touch;
    background-color: ${({ theme }) => theme.fundo_lista};

    &::-webkit-scrollbar-track {
        background-color: #cccccc;
    }

    &::-webkit-scrollbar {
        width: 6px;
    }

    &::-webkit-scrollbar-thumb {
        background-color: #666;
    }
`;

// ocupa ao menos a altura visível da lista: ao trocar de filtro ou carregar, o rodapé fica abaixo
// da tela em vez de subir para junto do carregamento
export const Games = styled.div`
    flex: 1 0 auto;
    min-height: 100%;
`;

// indicador de carregamento enquanto a lista troca de filtro ou busca a próxima página
export const LoadingArea = styled.div`
    display: flex;
    justify-content: center;
    margin: 35px auto;
`;

// espaço livre no fim da lista para o botão do WhatsApp não cobrir o último jogo (FR-003c)
export const WhatsAppSpace = styled.div`
    display: none;

    ${mobile} {
        display: block;
        min-height: 85px;
    }
`;
