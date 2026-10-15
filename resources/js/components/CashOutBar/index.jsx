import { formatar_real, para_centavos } from '../../utils/money';
import { Bar, Button, Label, Value } from './styles';

// barra fixa no rodapé do celular com a oferta e o botão "Encerrar aposta" (FR-013), só para o dono
export default function CashOutBar({ encerramento, ao_encerrar }) {
    if (!encerramento?.disponivel || encerramento.em_analise) return null;

    return (
        <Bar>
            <div>
                <Label>Encerrar agora</Label>
                <Value>R$ {formatar_real(para_centavos(encerramento.oferta))}</Value>
            </div>
            <Button type="button" onClick={ao_encerrar}>Encerrar aposta</Button>
        </Bar>
    );
}
