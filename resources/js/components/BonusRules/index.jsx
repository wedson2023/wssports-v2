import RuleItem from '../RuleItem';
import RulesBlock from '../RulesBlock';
import { formatar_real, para_centavos } from '../../utils/money';
import { Paragraph } from './styles';

const real = (valor) => `R$ ${formatar_real(para_centavos(valor))}`;

const data_curta = (data) => new Intl.DateTimeFormat('pt-BR', { timeZone: 'America/Sao_Paulo' }).format(new Date(data));

// categorias com depósito: mostram o depósito máximo, como no sistema antigo
const com_deposito = ['Primeiro depósito', 'Qualquer depósito'];

// texto de uma promoção montado pelos campos dela, como o parágrafo de screens/rules do sistema
// antigo; trechos de campos vazios não aparecem (spec 006, FR-023)
const texto_promocao = (promocao) => {
    const ganho = promocao.tipo_ganho === 'Percentual'
        ? `${Number(promocao.valor).toLocaleString('pt-BR')}% do valor depositado`
        : real(promocao.valor);

    const trechos = [
        `Valor ganho nessa bonificação é de ${ganho} na modalidade ${promocao.modalidade}, é preciso que cumpra um rollover de ${promocao.rollover}x`,
        promocao.valor_maximo_conversao && `seu valor máximo a ser convertido é de ${real(promocao.valor_maximo_conversao)}`,
        promocao.data_fim && `a promoção vale até ${data_curta(promocao.data_fim)}`,
        com_deposito.includes(promocao.categoria) && promocao.valor_maximo_deposito
            && `só receberá essa bonificação em depósitos de até ${real(promocao.valor_maximo_deposito)}`,
        promocao.modalidade === 'Esportes'
            && `sobre regras de apostas, o valor mínimo para apostas é de ${real(promocao.valor_minimo_aposta)} e o valor máximo é de ${real(promocao.valor_maximo_aposta)}, `
            + `para apostas que contenham apenas um jogo é necessário que a cotação mínima seja de ${Number(promocao.odd_minima_aposta_simples).toFixed(2)}, `
            + `para apostas que contenham mais de um jogo é necessário que a cotação mínima seja de ${Number(promocao.odd_minima_aposta_multipla).toFixed(2)}`,
    ];

    return `${trechos.filter(Boolean).join(', ')}.`;
};

// bloco "Regras de bônus": um item por promoção ativa; sem promoção ativa, não aparece (FR-022)
export default function BonusRules({ promocoes }) {
    if (!promocoes.length) return null;

    return (
        <RulesBlock titulo="Regras de bônus">
            {promocoes.map((promocao) => (
                <RuleItem key={promocao.id} titulo={`${promocao.categoria}: ${promocao.nome}`}>
                    <Paragraph>{texto_promocao(promocao)}</Paragraph>
                </RuleItem>
            ))}
        </RulesBlock>
    );
}
