import { MatchExtras, MatchScore } from '../LiveHunchCard';
import { formatar_data_hora } from '../../utils/dates';
import { aparencia_estado } from '../../utils/live_states';
import { Badge, Card, Header, List, Meta, Odd, Row, Title } from './styles';

// situação do bloco: Anulado ou o resultado combinado dos palpites dele
const situacao_bloco = (bloco, palpites) => {
    if (bloco.situacao === 'Anulado') return 'Anulado';

    const ativos = palpites.filter((palpite) => palpite.situacao !== 'Cancelado');

    if (ativos.some((palpite) => palpite.resultado === 'Perdedor')) return 'Perdedor';
    if (ativos.length && ativos.every((palpite) => palpite.resultado === 'Vencedor')) return 'Vencedor';
    if (ativos.some((palpite) => palpite.selo === 'Perdendo agora')) return 'Perdendo agora';
    if (ativos.length && ativos.every((palpite) => palpite.selo === 'Ganhando agora' || palpite.resultado === 'Vencedor')) return 'Ganhando agora';

    return null;
};

// bloco Criar Aposta agrupado pelo jogo, como no comprovante (FR-007)
export default function LiveBlockCard({ bloco, palpites, gol = false }) {
    const [primeiro] = palpites;
    const situacao = situacao_bloco(bloco, palpites);
    const { cor } = aparencia_estado(situacao === 'Anulado' ? 'Devolvido' : situacao);

    return (
        <Card>
            <Meta>
                <span>{primeiro.campeonato}</span>
                <span>{formatar_data_hora(primeiro.data_inicio)}</span>
            </Meta>
            <MatchScore palpite={primeiro} gol={gol} />
            <Header>
                <Title>Criar Aposta · cotação {Number(bloco.cotacao).toFixed(2)}</Title>
                {situacao && <Badge $cor={cor}>{situacao}</Badge>}
            </Header>
            <List>
                {palpites.map((palpite) => {
                    const estado = palpite.situacao === 'Cancelado' ? 'Cancelado' : (palpite.resultado !== 'Aguardando' ? palpite.resultado : palpite.selo);

                    return (
                        <Row key={palpite.id} $cancelado={palpite.situacao === 'Cancelado'}>
                            <span>{palpite.jogador ? `${palpite.jogador} (${palpite.jogador_tipo})` : palpite.mercado}</span>
                            <Odd $cor={estado ? aparencia_estado(estado).cor : undefined}>{Number(palpite.cotacao).toFixed(2)}</Odd>
                        </Row>
                    );
                })}
            </List>
            <MatchExtras palpite={primeiro} />
        </Card>
    );
}
