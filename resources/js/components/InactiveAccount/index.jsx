import { Button, Container, ExitButton, Text } from './styles';

// conta desativada (Pending de screens/credits): só a mensagem, "Atualizar" e "Sair"
export default function InactiveAccount({ ao_atualizar, ao_sair }) {
    return (
        <Container>
            <Text>Pagamento pendente ou conta desativada.</Text>
            <Button type="button" onClick={ao_atualizar}>Atualizar</Button>
            <ExitButton type="button" onClick={ao_sair}>Sair</ExitButton>
        </Container>
    );
}
