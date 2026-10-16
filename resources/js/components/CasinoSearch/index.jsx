import { Container, Icon, Input } from './styles';

// busca pelo nome do jogo (screens/components/search do antigo): procura ao apertar Enter
export default function CasinoSearch({ ao_buscar }) {
    const ao_soltar_tecla = (evento) => {
        const texto = evento.target.value.trim();

        if (texto && evento.key === 'Enter') ao_buscar(texto);
    };

    return (
        <Container>
            <Icon>sports_esports</Icon>
            <Input placeholder="Digite nome do jogo" onKeyUp={ao_soltar_tecla} aria-label="Buscar jogo" />
        </Container>
    );
}
