import { Chip, Container } from './styles';

// etiquetas dos filtros ativos da lista de apostas (spec 018, contracts/paginas §3); tocar no ✕
// remove o filtro. etiquetas = [{ chave, texto }]
export default function ActiveFilters({ etiquetas, ao_remover }) {
    if (!etiquetas.length) return null;

    return (
        <Container aria-label="Filtros ativos">
            {etiquetas.map((etiqueta) => (
                <Chip key={etiqueta.chave}>
                    <span>{etiqueta.texto}</span>
                    <button type="button" title={`Remover o filtro ${etiqueta.texto}`} aria-label={`Remover o filtro ${etiqueta.texto}`} onClick={() => ao_remover(etiqueta.chave)}>
                        <i className="material-icons" aria-hidden="true">close</i>
                    </button>
                </Chip>
            ))}
        </Container>
    );
}
