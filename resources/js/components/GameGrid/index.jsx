import GameCard from '../GameCard';
import { Games, Message } from './styles';

// grade dos jogos filtrados (Games .filter de screens/casino)
export default function GameGrid({ jogos, aguardando, abrindo, ao_jogar, ao_demo }) {
    if (aguardando) return <Message>Carregando...</Message>;

    if (!jogos.length) return <Message>Nenhum jogo encontrado.</Message>;

    return (
        <Games>
            {jogos.map((jogo) => (
                <GameCard key={jogo.id} jogo={jogo} abrindo={abrindo} ao_jogar={ao_jogar} ao_demo={ao_demo} />
            ))}
        </Games>
    );
}
