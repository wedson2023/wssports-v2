import { formatar_ajuste } from '../../utils/quotes';
import { Badge } from './styles';

// selo das regras de um campeonato, jogo ou regra ativa (spec 016, FR-013): "sem ajuste" cinza;
// uma regra "+3% · Clientes"; várias "2 regras · −5% a +3%"; unidade "" para valor do jogo
export default function RuleBadge({ resumo, unidade = '%' }) {
    if (!resumo || !resumo.quantidade) return <Badge $vazio>sem ajuste</Badge>;

    const faixa = resumo.minimo === resumo.maximo
        ? formatar_ajuste(resumo.minimo, unidade)
        : `${formatar_ajuste(resumo.minimo, unidade)} a ${formatar_ajuste(resumo.maximo, unidade)}`;

    const texto = resumo.quantidade === 1 && resumo.alvos?.length === 1
        ? `${faixa} · ${resumo.alvos[0]}`
        : `${resumo.quantidade} regras · ${faixa}`;

    return <Badge title={texto}>{texto}</Badge>;
}
