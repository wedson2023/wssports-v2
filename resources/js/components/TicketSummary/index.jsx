import TicketProgress from '../TicketProgress';
import { formatar_data_hora } from '../../utils/dates';
import { aparencia_estado, rotulo_resultado } from '../../utils/live_states';
import { formatar_real, para_centavos } from '../../utils/money';
import { Card, Code, Data, Header, Item, Label, Prize, PrizeLabel, Status, Top, Value } from './styles';

const real = (valor) => `R$ ${formatar_real(para_centavos(valor))}`;

// resumo do bilhete em tempo real (FR-002): prêmio potencial, situação, cliente, horário, valor e
// cotação total, com a barra de progresso dos palpites; indicador e ações chegam da página
export default function TicketSummary({ bilhete, indicador = null, acoes = null }) {
    const rotulo = rotulo_resultado(bilhete);
    const { cor } = aparencia_estado(bilhete.resultado === 'Aguardando' ? 'aguardando' : bilhete.resultado);
    const parcial = para_centavos(bilhete.valor_em_jogo) !== para_centavos(bilhete.valor);

    return (
        <Card>
            <Top>
                <Code>Bilhete {bilhete.codigo}</Code>
                {acoes}
            </Top>
            <Header>
                <div>
                    <PrizeLabel>{bilhete.resultado === 'Vencedor' ? 'Prêmio' : 'Prêmio potencial'}</PrizeLabel>
                    <Prize>{real(bilhete.total_a_pagar)}</Prize>
                </div>
                <Status $cor={bilhete.situacao === 'Cancelada' ? undefined : cor}>{rotulo}</Status>
            </Header>
            {indicador}
            <Data>
                <Item><Label>Cliente</Label><Value>{bilhete.cliente}</Value></Item>
                <Item><Label>Horário</Label><Value>{formatar_data_hora(bilhete.criada_em)}</Value></Item>
                <Item>
                    <Label>{parcial ? 'Valor em jogo' : 'Valor'}</Label>
                    <Value>{real(bilhete.valor_em_jogo)}{parcial && <small> de {real(bilhete.valor)}</small>}</Value>
                </Item>
                <Item><Label>Cotação total</Label><Value>{Number(bilhete.cotacao_total).toFixed(2)}</Value></Item>
            </Data>
            <TicketProgress progresso={bilhete.progresso} />
        </Card>
    );
}
