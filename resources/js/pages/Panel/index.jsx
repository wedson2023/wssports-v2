import { useEffect, useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';

import Spinner from '../../components/Spinner';
import useAdministrador from '../../hooks/useAdministrador';
import useSessao from '../../hooks/useSessao';
import api from '../../utils/api';
import { alerta_erro } from '../../utils/alerts';
import { ConnectButton, Page } from './styles';

// tela do Admin (spec 007, US8): só o botão que abre a autorização do Mercado Pago em nova aba;
// a página de retorno (GatewayReturn) mostra o resultado e fecha a aba
export default function Panel() {
    const { nome_sistema } = usePage().props;
    const { sessao } = useSessao();
    const administrador = useAdministrador();
    const [abrindo, definir_abrindo] = useState(false);

    // quem não é Admin volta para a tela principal
    useEffect(() => {
        if (administrador === false) router.visit('/');
    }, [administrador]);

    const vincular = async () => {
        // a aba abre no clique, antes da resposta, para o navegador não bloquear o pop-up
        const aba = window.open('', '_blank');
        definir_abrindo(true);

        try {
            const { data } = await api.post('/configuracoes/gateway/mercado-pago/autorizar', {}, {
                headers: { Authorization: `Bearer ${sessao.token}` },
            });

            if (aba) aba.location.href = data.url;
            else window.location.href = data.url;
        } catch (erro) {
            aba?.close();
            alerta_erro(erro);
        } finally {
            definir_abrindo(false);
        }
    };

    return (
        <Page>
            <Head title={nome_sistema} />
            {administrador && (
                <ConnectButton type="button" onClick={vincular} disabled={abrindo} aria-busy={abrindo}>
                    {abrindo
                        ? <Spinner tamanho={20} />
                        : <i className="material-icons" aria-hidden="true">link</i>}
                    <span>{abrindo ? 'Abrindo o Mercado Pago...' : 'Vincular ao Mercado Pago'}</span>
                </ConnectButton>
            )}
        </Page>
    );
}
