import RuleItem, { RuleLine } from '../RuleItem';
import RulesBlock from '../RulesBlock';
import { regras_mercados } from '../../utils/market_rules';
import { Label } from './styles';

// bloco "Regras de apostas": o que cada cotação de cada mercado precisa para ganhar, com os
// textos fixos do sistema antigo (spec 006, FR-023a)
export default function MarketRules() {
    return (
        <RulesBlock titulo="Regras de apostas">
            {regras_mercados.map(({ titulo, itens }) => (
                <RuleItem key={titulo} titulo={titulo}>
                    {itens.map(({ rotulo, texto, espaco_antes = false }) => (
                        <RuleLine key={`${rotulo}-${texto}`} $espaco_antes={espaco_antes}>
                            {rotulo && <Label>{rotulo}: </Label>}
                            <span>{texto}</span>
                        </RuleLine>
                    ))}
                </RuleItem>
            ))}
        </RulesBlock>
    );
}
