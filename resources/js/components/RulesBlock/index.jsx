import { Container, Title } from './styles';

// bloco da página de regras: seção branca com o título centralizado (h6 de screens/rules do
// sistema antigo); o bloco das regras da banca não tem título, como no antigo (spec 006, FR-025)
export default function RulesBlock({ titulo = null, children }) {
    return (
        <Container aria-label={titulo ?? undefined}>
            {titulo && <Title>{titulo}</Title>}
            {children}
        </Container>
    );
}
