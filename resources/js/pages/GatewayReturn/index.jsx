import { Head, usePage } from '@inertiajs/react';

import { Button, Card, Icon, Logo, Message, Page } from './styles';

// retorno da autorização do Mercado Pago (spec 007, US8): o painel abriu o fluxo em nova aba
export default function GatewayReturn({ sucesso, mensagem }) {
    const { tema, nome_sistema } = usePage().props;

    return (
        <Page>
            <Head title={nome_sistema} />
            <Card>
                <Logo src={tema.logo} alt={nome_sistema} />
                <Icon className="material-icons" $sucesso={sucesso} aria-hidden="true">{sucesso ? 'check_circle' : 'error'}</Icon>
                <Message>{mensagem}</Message>
                <Button type="button" onClick={() => window.close()}>Fechar</Button>
            </Card>
        </Page>
    );
}
