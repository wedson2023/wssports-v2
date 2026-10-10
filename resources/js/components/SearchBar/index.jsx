import { Container, Field, Icon, Input } from './styles';

// busca por time e conferência de bilhete pelo código; as duas rodam no Enter (FR-027, FR-044)
export default function SearchBar({ ao_buscar_time, ao_limpar_busca, ao_buscar_codigo }) {
    const ao_teclar_time = (evento) => {
        const texto = evento.currentTarget.value.trim();

        if (evento.key === 'Enter' && texto) ao_buscar_time(texto);
    };

    const ao_mudar_time = (evento) => {
        if (evento.currentTarget.value === '') ao_limpar_busca();
    };

    const ao_teclar_codigo = (evento) => {
        const codigo = evento.currentTarget.value.trim();

        if (evento.key === 'Enter' && codigo) ao_buscar_codigo(codigo);
    };

    return (
        <Container>
            <Field>
                <Icon>search</Icon>
                <Input type="search" placeholder="Digite o nome do time." onKeyUp={ao_teclar_time} onChange={ao_mudar_time} />
            </Field>
            <Field>
                <Icon>receipt</Icon>
                <Input type="search" placeholder="Digite o código aqui." onKeyUp={ao_teclar_codigo} />
            </Field>
        </Container>
    );
}
