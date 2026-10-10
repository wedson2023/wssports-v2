import useCampoValor from '../../hooks/useCampoValor';
import useCupom from '../../hooks/useCupom';
import { formatar_real } from '../../utils/money';
import { CheckButton, CheckText, Container, Count, IconImage, Input, Label, TextValue } from './styles';

// barra de resumo do mobile: valor, retorno possível e "Conferir", que abre o cupom (FR-036)
export default function BetSlipSummary({ ao_conferir }) {
    const { cupom, definir_valor, total_centavos } = useCupom();
    const { texto, ao_mudar } = useCampoValor(cupom.valor_centavos, definir_valor);

    return (
        <Container>
            <Label title="Valor da aposta.">
                <IconImage src="/images/dollar.png" alt="" />
                <Input type="number" placeholder="0.00" min="0.00" max="10000.00" step="0.01" value={texto} onChange={ao_mudar} />
            </Label>
            <Label title="Retorno possível.">
                <IconImage src="/images/trofeu.png" alt="" />
                <TextValue>{formatar_real(total_centavos)}</TextValue>
            </Label>
            <CheckButton type="button" onClick={ao_conferir} title="Clique para finalizar sua aposta.">
                <CheckText>Conferir</CheckText>
                <Count>{cupom.palpites.length}</Count>
            </CheckButton>
        </Container>
    );
}
