import { Bottom, Container, Icon, Matches, Odd, Option, Top } from './styles';

// nomes cortados em 13 caracteres, como no cupom do sistema antigo
const curto = (texto) => (texto ?? '').substring(0, 13);

// palpite do cupom: times, remover, "mais opções", mercado e cotação
export default function BetSlipItem({ palpite, ao_remover, ao_abrir_detalhes }) {
    return (
        <Container>
            <Top>
                <Matches>
                    <strong>{curto(palpite.time_casa)}</strong>
                    <strong>{curto(palpite.time_fora)}</strong>
                </Matches>
                <Icon onClick={ao_remover} title="Clique para remover esse palpite da aposta.">delete</Icon>
            </Top>
            <Bottom>
                <Icon $tamanho="17px" onClick={ao_abrir_detalhes} title="Clique para abrir mais opções">launch</Icon>
                <Option>{palpite.mercado}</Option>
                <Odd>{Number(palpite.cotacao).toFixed(2)}</Odd>
            </Bottom>
        </Container>
    );
}
