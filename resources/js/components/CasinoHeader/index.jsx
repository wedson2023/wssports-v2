import { usePage } from '@inertiajs/react';

import { Button, Container, Icon, Logo } from './styles';

// cabeçalho do cassino (Header de screens/casino): menu, logo, "Criar Conta" (sem login) e suporte
export default function CasinoHeader({ logado, ao_abrir_menu, ao_criar_conta }) {
    const { tema, contatos } = usePage().props;

    const abrir_suporte = () => window.open(`https://api.whatsapp.com/send?phone=${contatos.whatsapp}&text=Olá`);

    return (
        <Container $logado={logado}>
            <Icon onClick={ao_abrir_menu} title="Menu">menu</Icon>
            <Logo src={tema.logo} alt="" />
            {!logado && <Button onClick={ao_criar_conta}>Criar Conta</Button>}
            <Icon onClick={abrir_suporte} title="Suporte">support_agent</Icon>
        </Container>
    );
}
