import { formatar_data_hora } from '../../utils/dates';
import { aparencia_estado } from '../../utils/live_states';
import { esconder_escudo, url_escudo } from '../../utils/shields';
import {
    Badge, Card, Clock, Extras, Market, Meta, Odds, Score, ScoreBox, Shield, Team, TeamName, Teams,
} from './styles';

const placar = (tempo) => (tempo ? `${tempo.casa} x ${tempo.fora}` : '-');

// texto abaixo do placar: período e minuto, "começa em X min" ou o resultado final
const texto_andamento = (palpite) => {
    if (palpite.estado === 'nao_iniciado') {
        if (palpite.minutos_para_inicio === null) return formatar_data_hora(palpite.data_inicio);

        return palpite.minutos_para_inicio >= 60
            ? `começa ${formatar_data_hora(palpite.data_inicio)}`
            : `começa em ${palpite.minutos_para_inicio} min`;
    }

    if (palpite.minuto !== null && palpite.minuto !== undefined) return `${palpite.periodo} · ${palpite.minuto}'`;

    return palpite.periodo ?? '';
};

// selo provisório (jogo ao vivo) ou resultado do palpite
const texto_selo = (palpite) => {
    if (palpite.situacao === 'Cancelado') return 'Cancelado';
    if (palpite.resultado !== 'Aguardando') return palpite.resultado;

    return palpite.selo;
};

// placar do jogo em destaque, com escudos e andamento; usado também pelo bloco Criar Aposta
export function MatchScore({ palpite, gol = false }) {
    const tem_placar = palpite.placar_casa !== null && palpite.placar_casa !== undefined;

    return (
        <Teams>
            <Team>
                <Shield src={url_escudo(palpite.escudo_casa)} onError={esconder_escudo} alt="" />
                <TeamName>{palpite.time_casa}</TeamName>
            </Team>
            <ScoreBox>
                <Score $gol={gol} aria-label={tem_placar ? `Placar ${palpite.placar_casa} a ${palpite.placar_fora}` : 'Jogo não iniciado'}>
                    {tem_placar ? `${palpite.placar_casa} - ${palpite.placar_fora}` : 'x'}
                </Score>
                <Clock>{texto_andamento(palpite)}</Clock>
            </ScoreBox>
            <Team $fora>
                <Shield src={url_escudo(palpite.escudo_fora)} onError={esconder_escudo} alt="" />
                <TeamName>{palpite.time_fora}</TeamName>
            </Team>
        </Teams>
    );
}

// tempos e escanteios (só futebol), com o jogo em andamento ou encerrado
export function MatchExtras({ palpite }) {
    if (palpite.esporte !== 'FUTEBOL' || palpite.estado === 'nao_iniciado') return null;

    return (
        <Extras>
            <span>1º T: {placar(palpite.primeiro_tempo)}</span>
            <span>2º T: {placar(palpite.segundo_tempo)}</span>
            <span>Escanteios: {palpite.escanteios ?? '-'}</span>
        </Extras>
    );
}

// cartão de um palpite no bilhete em tempo real (FR-003 a FR-006)
export default function LiveHunchCard({ palpite, gol = false }) {
    const selo = texto_selo(palpite);
    const { cor, icone } = aparencia_estado(selo);
    const mercado = palpite.jogador ? `${palpite.jogador} (${palpite.jogador_tipo})` : palpite.mercado;
    const mudou = palpite.cotacao_atual !== null && palpite.cotacao_atual !== undefined && Number(palpite.cotacao_atual) !== Number(palpite.cotacao);

    return (
        <Card $cancelado={palpite.situacao === 'Cancelado'} aria-label={`${palpite.time_casa} x ${palpite.time_fora}`}>
            <Meta>
                <span>{palpite.campeonato}</span>
                <span>{formatar_data_hora(palpite.data_inicio)}</span>
            </Meta>
            <MatchScore palpite={palpite} gol={gol} />
            {gol && <span className="sr-only" role="status">Gol em {palpite.time_casa} x {palpite.time_fora}: {palpite.placar_casa} a {palpite.placar_fora}</span>}
            <Market>
                <div>
                    <strong>{mercado}</strong>
                    <Odds>
                        Cotação {Number(palpite.cotacao).toFixed(2)}
                        {mudou && <> · agora {Number(palpite.cotacao_atual).toFixed(2)}</>}
                    </Odds>
                </div>
                {selo && (
                    <Badge $cor={cor}>
                        <span className="material-icons" aria-hidden="true">{icone}</span>
                        {selo}
                    </Badge>
                )}
            </Market>
            <MatchExtras palpite={palpite} />
        </Card>
    );
}
