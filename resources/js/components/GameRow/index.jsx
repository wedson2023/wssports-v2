import GameCard from '../GameCard';
import { rotulo_tipo } from '../../utils/casino_types';
import { Container, Games, Link, Title } from './styles';

// linha de um tipo de jogo (Item de screens/casino) com "Veja mais", exceto em Populares
export default function GameRow({ tipo, jogos, abrindo, ao_ver_mais, ao_jogar, ao_demo }) {
    return (
        <Container>
            <Title>{rotulo_tipo(tipo)}</Title>
            {tipo !== 'populares' ? <Link onClick={() => ao_ver_mais(tipo)}>Veja mais</Link> : <span />}
            <Games>
                {jogos.map((jogo) => (
                    <GameCard key={jogo.id} jogo={jogo} abrindo={abrindo} ao_jogar={ao_jogar} ao_demo={ao_demo} />
                ))}
            </Games>
        </Container>
    );
}
