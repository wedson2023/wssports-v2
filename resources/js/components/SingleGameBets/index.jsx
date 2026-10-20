import { formatar_valor, valor_grafico } from '../../utils/charts';
import { Bar, BarFill, Eye, Index, Name, Prize, Row } from './styles';

// Apostas por um jogo (spec 014, contracts/paginas §7): posição, apostador e prêmio com barra
// proporcional; o olho abre o bilhete pelo ao_ver_bilhete da página
export default function SingleGameBets({ apostas, ao_ver_bilhete }) {
    const maior_premio = Math.max(...apostas.map((aposta) => valor_grafico(aposta.premio)), 1);

    return apostas.map((aposta, indice) => (
        <Row key={aposta.codigo}>
            <Index>{indice + 1}º</Index>
            <Name title={aposta.apostador}>{aposta.apostador}</Name>
            <Prize>
                <span>{formatar_valor(aposta.premio)}</span>
                <Bar><BarFill style={{ width: `${(valor_grafico(aposta.premio) / maior_premio) * 100}%` }} /></Bar>
            </Prize>
            <Eye title="Ver bilhete" onClick={() => ao_ver_bilhete(aposta.codigo)}>remove_red_eye</Eye>
        </Row>
    ));
}
