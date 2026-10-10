import { useTheme } from 'styled-components';

import EmptyMessage from '../EmptyMessage';
import SpecialCard from '../SpecialCard';
import Spinner from '../Spinner';
import { Container, Games, LoadingArea, WhatsAppSpace } from '../MatchList/styles';

// distância (px) do fim da lista em que a próxima página é pedida, como na lista de jogos
const distancia_fim = 300;

// coluna central com as categorias especiais (spec 006, FR-014): mesmo topo, rolagem infinita,
// carregamento e rodapé da lista de jogos
export default function SpecialList({
    ref,
    topo,
    carregando = false,
    tem_mais = false,
    rodape,
    especiais,
    opcao_selecionada,
    ao_escolher,
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
                    <LoadingArea role="status" aria-label="Carregando especiais">
                        <Spinner tamanho={40} cor={tema.principal} />
                    </LoadingArea>
                )}
                {!carregando && (especiais.length ? (
                    especiais.map((especial) => (
                        <SpecialCard
                            key={especial.id}
                            especial={especial}
                            selecionada_id={opcao_selecionada(especial)}
                            ao_escolher={(opcao) => ao_escolher(especial, opcao)}
                        />
                    ))
                ) : (
                    <EmptyMessage>Nenhum jogo encontrado.</EmptyMessage>
                ))}
                {!carregando && tem_mais && especiais.length > 0 && (
                    <LoadingArea role="status" aria-label="Carregando mais especiais">
                        <Spinner tamanho={40} cor={tema.principal} />
                    </LoadingArea>
                )}
            </Games>
            {rodape}
            <WhatsAppSpace />
        </Container>
    );
}
