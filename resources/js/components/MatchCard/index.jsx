import OddGroup from '../OddGroup';
import { formatar_hora } from '../../utils/dates';
import { Clock, Cron, Match, Period, PeriodText, Score, Shield, Team, Teams, Time, Item } from './styles';

// jogo que começa em menos de 60 minutos: horário na cor do tema, como contagem "00:MM"
const minutos_perto = 60;

// o provedor envia o número do escudo (ou "escudo" quando o time não tem um próprio); a imagem
// vem do mesmo servidor usado pelo sistema antigo
const url_escudo = (escudo) => (/^https?:\/\//.test(escudo ?? '') ? escudo : `https://api.oddbrasil.com/img/mini/m_${escudo || 'escudo'}.png`);

const esconder_imagem = (evento) => {
    evento.currentTarget.style.visibility = 'hidden';
};

// card do jogo: times, horário (ou placar e período no ao vivo) e cotações (FR-021, FR-022)
export default function MatchCard({ confronto, ao_vivo, codigo_selecionado, variacoes, ao_escolher, ao_abrir_detalhes }) {
    const perto = !ao_vivo && confronto.minutos_para_inicio < minutos_perto;

    return (
        <Item>
            <Match>
                <Teams>
                    <Team>
                        <Shield src={url_escudo(confronto.escudo_casa)} onError={esconder_imagem} alt="" />
                        <strong>{confronto.time_casa}</strong>
                    </Team>
                    <Team>
                        <Shield src={url_escudo(confronto.escudo_fora)} onError={esconder_imagem} alt="" />
                        <strong>{confronto.time_fora}</strong>
                    </Team>
                    {ao_vivo && (
                        <Period>
                            <i className="material-icons">access_time</i>
                            <PeriodText>{`${confronto.situacao} - ${confronto.minuto} minuto(s)`}</PeriodText>
                        </Period>
                    )}
                </Teams>

                {ao_vivo ? (
                    <Score title="Placar no momento">{`${confronto.placar_casa} x ${confronto.placar_fora}`}</Score>
                ) : (
                    <Time $perto={perto}>
                        <Clock>access_time</Clock>
                        <Cron>
                            {perto ? `00:${String(Math.max(confronto.minutos_para_inicio, 0)).padStart(2, '0')}` : formatar_hora(confronto.data_inicio)}
                        </Cron>
                    </Time>
                )}
            </Match>
            <OddGroup
                confronto={confronto}
                codigo_selecionado={codigo_selecionado}
                variacoes={variacoes}
                ao_escolher={ao_escolher}
                ao_abrir_detalhes={ao_abrir_detalhes}
            />
        </Item>
    );
}
