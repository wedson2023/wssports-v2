import { formatar_dia_mes } from '../../utils/dates';
import { Container } from './styles';

// cabeçalho do campeonato na lista: nome e data do primeiro jogo (FR-020)
export default function ChampionshipHeader({ nome, data_inicio }) {
    return (
        <Container>
            <span>{nome}</span>
            {data_inicio && <time>{formatar_dia_mes(data_inicio)}</time>}
        </Container>
    );
}
