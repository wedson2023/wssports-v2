import ChampionshipHeader from '../ChampionshipHeader';
import EmptyMessage from '../EmptyMessage';
import { useTheme } from 'styled-components';

import MatchCard from '../MatchCard';
import Spinner from '../Spinner';
import { Container, Games, LoadingArea, WhatsAppSpace } from './styles';

// distância (px) do fim da lista em que a próxima página é pedida (handleScroll do antigo)
const distancia_fim = 300;

// coluna central: conteúdo do topo, jogos por campeonato e rodapé; pede mais jogos perto do fim
export default function MatchList({
    ref,
    topo,
    carregando = false,
    tem_mais = false,
    rodape,
    campeonatos,
    ao_vivo,
    selecao_do_jogo,
    variacoes = {},
    ao_escolher,
    ao_abrir_detalhes,
    ao_chegar_perto_do_fim,
}) {
    const tema = useTheme();

    const ao_rolar = (evento) => {
        const { scrollHeight, scrollTop, clientHeight } = evento.currentTarget;

        if (scrollHeight - (scrollTop + distancia_fim) <= clientHeight) ao_chegar_perto_do_fim();
    };

    return (
        <Container ref={ref} onScroll={ao_rolar}>
            {topo}
            <Games>
                {carregando && (
                    <LoadingArea role="status" aria-label="Carregando jogos">
                        <Spinner tamanho={40} cor={tema.principal} />
                    </LoadingArea>
                )}
                {!carregando && (campeonatos.length ? (
                    campeonatos.map((campeonato) => (
                        <div key={campeonato.id}>
                            <ChampionshipHeader nome={campeonato.nome} data_inicio={campeonato.confrontos[0]?.data_inicio} />
                            {campeonato.confrontos.map((confronto) => (
                                <MatchCard
                                    key={confronto.id}
                                    confronto={confronto}
                                    ao_vivo={ao_vivo}
                                    codigo_selecionado={selecao_do_jogo(confronto)}
                                    variacoes={variacoes[confronto.id]}
                                    ao_escolher={(cotacao) => ao_escolher(confronto, campeonato, cotacao)}
                                    ao_abrir_detalhes={() => ao_abrir_detalhes(confronto)}
                                />
                            ))}
                        </div>
                    ))
                ) : (
                    <EmptyMessage>Nenhum jogo encontrado.</EmptyMessage>
                ))}
                {/* fim da rolagem com mais páginas: o carregamento aparece enquanto a próxima chega */}
                {!carregando && tem_mais && campeonatos.length > 0 && (
                    <LoadingArea role="status" aria-label="Carregando mais jogos">
                        <Spinner tamanho={40} cor={tema.principal} />
                    </LoadingArea>
                )}
            </Games>
            {rodape}
            <WhatsAppSpace />
        </Container>
    );
}
