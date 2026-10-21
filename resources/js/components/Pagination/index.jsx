import { Button, Container } from './styles';

// números visíveis ao redor da página atual
const VISIVEIS = 5;

// paginação das listas do painel (Paginator do antigo); some quando há uma página só
export default function Pagination({ pagina, ultima, ao_trocar }) {
    if (!ultima || ultima <= 1) return null;

    const inicio = Math.max(1, Math.min(pagina - Math.floor(VISIVEIS / 2), ultima - VISIVEIS + 1));
    const numeros = Array.from({ length: Math.min(VISIVEIS, ultima) }, (_, indice) => inicio + indice);

    return (
        <Container aria-label="Paginação">
            <Button type="button" disabled={pagina <= 1} onClick={() => ao_trocar(pagina - 1)} title="Anterior">
                <i className="material-icons" aria-hidden="true">chevron_left</i>
            </Button>
            {numeros.map((numero) => (
                <Button key={numero} type="button" $atual={numero === pagina} aria-current={numero === pagina ? 'page' : undefined} onClick={() => ao_trocar(numero)}>
                    {numero}
                </Button>
            ))}
            <Button type="button" disabled={pagina >= ultima} onClick={() => ao_trocar(pagina + 1)} title="Próxima">
                <i className="material-icons" aria-hidden="true">chevron_right</i>
            </Button>
        </Container>
    );
}
