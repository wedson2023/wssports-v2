import { formatar_valor } from '../../utils/charts';
import { Badge, Card, Icon, Label, Value, ValueRow } from './styles';

// cartão de valor da Dashboard, no lugar do Item do sistema antigo (ícone na cor do tema, rótulo e
// valor); "em breve" marca dado provisório (fake), o cadeado mostra se o limite está liberado e o
// destaque (Saldo líquido) mostra o valor em verde ou vermelho conforme o sinal
export default function StatCard({ icone, rotulo, valor, titulo = '', dinheiro = true, destaque = false, em_breve = false, cadeado = null }) {
    const icone_exibido = cadeado ? (cadeado === 'aberto' ? 'lock_open' : 'lock') : icone;
    const texto = dinheiro ? formatar_valor(valor) : String(valor ?? 0);
    const negativo = dinheiro && String(valor ?? '').startsWith('-');

    return (
        <Card $destaque={destaque} title={titulo}>
            <Icon $destaque={destaque}>{icone_exibido}</Icon>
            <Label title={rotulo}>{rotulo}</Label>
            <ValueRow>
                <Value $negativo={negativo} $destaque={destaque}>{texto}</Value>
                {em_breve && <Badge>em breve</Badge>}
            </ValueRow>
        </Card>
    );
}
