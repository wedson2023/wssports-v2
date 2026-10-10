import RuleItem, { RuleLine } from '../RuleItem';
import RulesBlock from '../RulesBlock';
import { formatar_real, para_centavos } from '../../utils/money';
import { Label } from './styles';

const real = (valor) => `R$ ${formatar_real(para_centavos(valor))}`;

// bloco "Limites de aposta" com a configuração de quem está vendo: visitante, cliente ou vendedor
// (spec 006, FR-024)
export default function BetLimits({ limites }) {
    if (!limites) return null;

    const linhas = [
        ['Valor mínimo da aposta', real(limites.valor_minimo_aposta)],
        ['Valor máximo da aposta', real(limites.valor_maximo_aposta)],
        ['Prêmio máximo', real(limites.premio_maximo)],
        ['Multiplicador máximo do prêmio', `${limites.multiplicador}x o valor apostado`],
        ['Quantidade mínima de palpites', limites.quantidade_minima_opcoes],
        ['Quantidade máxima de palpites', limites.quantidade_maxima_opcoes],
        ['Período de jogos', `até ${limites.periodo_jogos.toLowerCase()}`],
    ];

    return (
        <RulesBlock titulo="Limites de aposta">
            <RuleItem titulo="Seus limites">
                {linhas.map(([rotulo, valor]) => (
                    <RuleLine key={rotulo}>
                        <Label>{rotulo}: </Label>
                        <span>{valor}</span>
                    </RuleLine>
                ))}
            </RuleItem>
        </RulesBlock>
    );
}
