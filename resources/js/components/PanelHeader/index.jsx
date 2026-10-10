import { CloseIcon, Container, Title } from './styles';

// título de painel; no mobile mostra o X de fechar na cor do tema
export default function PanelHeader({ titulo, ao_fechar }) {
    return (
        <Container>
            <Title>{titulo}</Title>
            <CloseIcon onClick={ao_fechar} title="Fechar">close</CloseIcon>
        </Container>
    );
}
