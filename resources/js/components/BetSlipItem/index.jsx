import { Bottom, Container, Icon, Matches, Odd, Option, Top } from './styles';

// nomes cortados em 13 caracteres, como no cupom do sistema antigo
const curto = (texto) => (texto ?? '').substring(0, 13);

// jogo do cupom: times, remover, "mais opções", mercado e cotação. No bloco Criar Aposta (spec 008)
// há uma linha por palpite (remover à esquerda, mercado no meio e cotação à direita) e a linha
// "Criar Aposta" com a cotação do bloco, nos mesmos estilos; o remover do topo tira o jogo inteiro
export default function BetSlipItem({ palpites, cotacao_bloco = null, ao_remover, ao_abrir_detalhes }) {
    const [primeiro] = palpites;
    const bloco = palpites.length > 1;

    return (
        <Container>
            <Top>
                <Matches>
                    <strong>{curto(primeiro.time_casa)}</strong>
                    <strong>{curto(primeiro.time_fora)}</strong>
                </Matches>
                <Icon onClick={() => palpites.forEach(ao_remover)} title="Clique para remover esse palpite da aposta.">delete</Icon>
            </Top>
            {palpites.map((palpite) => (
                <Bottom key={`${palpite.codigo_cotacao}-${palpite.jogador_id ?? ''}-${palpite.opcao_id ?? ''}`}>
                    {/* no bloco, o remover do palpite fica no lugar do "mais opções", na cor do tema */}
                    {bloco
                        ? <Icon $tamanho="17px" $destacado onClick={() => ao_remover(palpite)} title="Clique para remover esse palpite do Criar Aposta.">close</Icon>
                        : <Icon $tamanho="17px" onClick={ao_abrir_detalhes} title="Clique para abrir mais opções">launch</Icon>}
                    <Option>{palpite.mercado}</Option>
                    <Odd>{Number(palpite.cotacao).toFixed(2)}</Odd>
                </Bottom>
            ))}
            {bloco && (
                <Bottom>
                    <Option>Criar Aposta</Option>
                    <Odd>{cotacao_bloco}</Odd>
                </Bottom>
            )}
        </Container>
    );
}
