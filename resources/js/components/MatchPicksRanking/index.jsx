import useNomesCotacoes from '../../hooks/useNomesCotacoes';
import { formatar_valor } from '../../utils/charts';
import { Empty, Entry, EntryLine, List, Summary } from './styles';

// nome da opção como no cupom: o da cotação e, no palpite de jogador, o nome dele
export const nome_da_opcao = (nomes, palpite) => {
    const cotacao = nomes?.[palpite.codigo_cotacao] ?? palpite.codigo_cotacao;

    return palpite.jogador ? `${cotacao}: ${palpite.jogador.nome}` : cotacao;
};

const grupo = (rotulo, dados) => `${rotulo}: ${dados.apostas} · ${formatar_valor(dados.valor)} · retorno ${formatar_valor(dados.retorno)}`;

// ranking das opções apostadas no jogo (spec 023, FR-016; o antigo "destaques da modalidade"), da
// mais para a menos apostada, com simples e múltiplas separadas. opcoes = GET .../mais-apostados
export default function MatchPicksRanking({ opcoes }) {
    const nomes = useNomesCotacoes();

    if (!opcoes.length) return <Empty>Nenhuma aposta neste jogo.</Empty>;

    return (
        <>
            <Summary>Múltiplas contam inteiras em cada jogo delas.</Summary>
            <List>
                {opcoes.map((opcao, posicao) => (
                    <Entry key={`${opcao.codigo_cotacao}-${opcao.jogador?.id ?? ''}`}>
                        <EntryLine>
                            <strong>{`${posicao + 1}º · ${nome_da_opcao(nomes, opcao)}`}</strong>
                            <strong>{`${opcao.quantidade} ${opcao.quantidade === 1 ? 'aposta' : 'apostas'}`}</strong>
                        </EntryLine>
                        <EntryLine>
                            <span>{grupo('Simples', opcao.simples)}</span>
                        </EntryLine>
                        <EntryLine>
                            <span>{grupo('Múltiplas', opcao.multiplas)}</span>
                        </EntryLine>
                    </Entry>
                ))}
            </List>
        </>
    );
}
