import { formatar_real, para_centavos } from '../../utils/money';
import { Balance, BalanceLast, BalanceText, BalanceValue, Balances, Message, Negative } from './styles';

// valor da API ("-12.50") → "R$ -12,50"; sem valor, "--,--" como no antigo
const reais = (valor) => {
    if (valor === null || valor === undefined) return '--,--';

    const texto = String(valor);
    const negativo = texto.startsWith('-');

    return `R$ ${negativo ? '-' : ''}${formatar_real(para_centavos(texto.replace('-', '')))}`;
};

const cartoes = [
    { chave: 'saldo', titulo: 'Meu saldo' },
    { chave: 'bonus_esportes', titulo: 'Bônus esportes' },
    { chave: 'bonus_cassino', titulo: 'Bônus cassino' },
];

// os três saldos (Balances de screens/credits) e o aviso das apostas premiadas; saldo negativo
// por estorno em vermelho (spec 007, FR-007)
export default function BalanceCards({ saldos }) {
    return (
        <>
            <Balances>
                {cartoes.map(({ chave, titulo }) => {
                    const carteira = saldos?.[chave];

                    return (
                        <Balance key={chave}>
                            <BalanceText>{titulo}</BalanceText>
                            <BalanceValue $negativo={Boolean(carteira?.negativo)}>{reais(carteira?.valor)}</BalanceValue>
                            <BalanceLast>Saldo anterior {reais(carteira?.anterior)}</BalanceLast>
                            <BalanceLast>Meta a cumprir {reais(carteira?.meta)}</BalanceLast>
                        </Balance>
                    );
                })}
            </Balances>
            {saldos?.saldo?.negativo && <Negative>Saldo negativo por estorno de pagamento</Negative>}
            <Message>Obs.: Para apostas premiadas o novo saldo estará disponível até 24 hrs.</Message>
        </>
    );
}
