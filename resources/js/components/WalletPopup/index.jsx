import { useEffect, useState } from 'react';

import api from '../../utils/api';
import { alerta_erro } from '../../utils/alerts';
import { formatar_real, para_centavos } from '../../utils/money';
import { Balance, BalanceLast, BalanceText, BalanceValue, Button, Container, Icon, Layer, Text } from './styles';

const reais = (valor) => (valor === null || valor === undefined ? '--,--' : `R$ ${formatar_real(para_centavos(String(valor)))}`);

// balão da Carteira do cassino (screens/casino/modal/balance): saldo real e bônus de cassino com a
// meta de rollover, Depositar e Sacar (o saque do v2, R-26); recarrega o saldo a cada abertura
export default function WalletPopup({ aberto, recarregar, ao_fechar, ao_depositar, ao_sacar }) {
    const [saldos, definir_saldos] = useState(null);

    useEffect(() => {
        if (!aberto) return;

        api.get('/area-cliente/minha-conta')
            .then(({ data }) => definir_saldos(data.data.saldos))
            .catch((erro) => {
                ao_fechar();
                alerta_erro(erro);
            });
    }, [aberto, recarregar, ao_fechar]);

    if (!aberto) return null;

    return (
        <>
            <Layer onClick={ao_fechar} />
            <Container>
                <Balance>
                    <BalanceText>Saldo</BalanceText>
                    <BalanceValue>{reais(saldos?.saldo?.valor)}</BalanceValue>
                    <BalanceLast>Meta a cumprir {reais(saldos?.saldo?.meta)}</BalanceLast>
                </Balance>
                <Balance>
                    <BalanceText>Bônus</BalanceText>
                    <BalanceValue>{reais(saldos?.bonus_cassino?.valor)}</BalanceValue>
                    <BalanceLast>Meta a cumprir {reais(saldos?.bonus_cassino?.meta)}</BalanceLast>
                </Balance>
                <Button onClick={ao_depositar}>
                    <Icon>account_balance_wallet</Icon>
                    <Text>Depositar</Text>
                </Button>
                <Button onClick={() => ao_sacar(saldos?.saldo?.valor)}>
                    <Icon>download</Icon>
                    <Text>Sacar</Text>
                </Button>
            </Container>
        </>
    );
}
