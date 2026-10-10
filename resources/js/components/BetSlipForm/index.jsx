import QuickValues from '../QuickValues';
import useCampoValor from '../../hooks/useCampoValor';
import useCupom from '../../hooks/useCupom';
import useSessao from '../../hooks/useSessao';
import { formatar_real } from '../../utils/money';
import { Container, Icon, IconImage, Input, Label, Row, TextValue } from './styles';

// formulário do cupom: nome, valor, retorno possível, cotação total, "vendedor paga" e valores
// rápidos (FR-035, FR-037, FR-037a); recebe os botões Limpar e Finalizar no fim, como no antigo
export default function BetSlipForm({ children }) {
    const { cupom, definir_nome, definir_valor, cotacao_total, total_centavos, vendedor_paga_centavos } = useCupom();
    const { texto, ao_mudar } = useCampoValor(cupom.valor_centavos, definir_valor);
    const { sessao } = useSessao();
    // cliente logado aposta no próprio nome, como o "Cadastro Online" do sistema antigo
    const nome_da_conta = sessao?.tipo === 'cliente' ? (sessao.nome ?? '') : null;

    return (
        <Container>
            <Row>
                <Label>
                    <Icon>person</Icon>
                    <Input
                        type="text"
                        placeholder="Nome apostador"
                        value={nome_da_conta ?? cupom.nome}
                        disabled={nome_da_conta !== null}
                        onChange={(evento) => definir_nome(evento.currentTarget.value)}
                    />
                </Label>
            </Row>
            <Row>
                <Label title="Valor da aposta.">
                    <IconImage src="/images/dollar.png" alt="" />
                    <Input type="number" placeholder="0.00" min="0.00" max="10000.00" step="0.01" value={texto} onChange={ao_mudar} />
                </Label>
                <Label title="Retorno possível.">
                    <IconImage src="/images/trofeu.png" alt="" />
                    <TextValue>{formatar_real(total_centavos)}</TextValue>
                </Label>
            </Row>
            <Row>
                <Label title="Multiplicador cotação.">
                    <IconImage src="/images/cotacao.png" alt="" />
                    <TextValue>{cotacao_total}</TextValue>
                </Label>
                <Label title="Vendedor paga.">
                    <IconImage src="/images/vendedor_paga.png" alt="" />
                    <TextValue>{formatar_real(vendedor_paga_centavos)}</TextValue>
                </Label>
            </Row>
            <QuickValues ao_escolher={definir_valor} />
            {children}
        </Container>
    );
}
