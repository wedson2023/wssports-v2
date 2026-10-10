import { Message } from './styles';

// mensagem de lista vazia (ex.: "Nenhum jogo selecionado")
export default function EmptyMessage({ children }) {
    return <Message>{children}</Message>;
}
