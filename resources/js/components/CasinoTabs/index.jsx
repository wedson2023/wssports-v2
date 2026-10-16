import { Container, Icon, Tab, Text } from './styles';

// barra inferior do cassino (Tabs de screens/casino)
export default function CasinoTabs({ ao_limpar, ao_promocoes, ao_carteira, ao_minha_conta }) {
    return (
        <Container>
            <Tab onClick={ao_limpar}>
                <Icon>cleaning_services</Icon>
                <Text>Limpar</Text>
            </Tab>
            <Tab onClick={ao_promocoes}>
                <Icon>redeem</Icon>
                <Text>Promoções</Text>
            </Tab>
            <Tab onClick={ao_carteira}>
                <Icon>account_balance_wallet</Icon>
                <Text>Carteira</Text>
            </Tab>
            <Tab onClick={ao_minha_conta}>
                <Icon>account_circle</Icon>
                <Text>Minha conta</Text>
            </Tab>
        </Container>
    );
}
