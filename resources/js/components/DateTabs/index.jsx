import { nome_dia_depois_de_amanha } from '../../utils/dates';
import { Container, Tab } from './styles';

// quantas abas o período de jogos do visitante libera (como periodo_jogos do sistema antigo)
const quantidade_por_periodo = { Hoje: 0, 'Amanhã': 2, 'Depois de amanhã': 3 };

// abas de data: Hoje, Amanhã e o dia da semana de depois de amanhã (FR-026); como no sistema
// antigo, a aba escolhida não muda de cor
export default function DateTabs({ periodo, ao_escolher }) {
    const abas = [
        { dia: 'hoje', titulo: 'Hoje', dica: 'Clique para listar todos os jogos de hoje' },
        { dia: 'amanha', titulo: 'Amanhã', dica: 'Clique para listar todos os jogos de amanhã' },
        { dia: 'depois_de_amanha', titulo: nome_dia_depois_de_amanha(), dica: 'Clique para listar todos os jogos de depois de amanhã' },
    ].slice(0, quantidade_por_periodo[periodo] ?? 3);

    return (
        <Container>
            {abas.map(({ dia, titulo, dica }) => (
                <Tab key={dia} onClick={() => ao_escolher(dia)} title={dica}>
                    {titulo}
                </Tab>
            ))}
        </Container>
    );
}
