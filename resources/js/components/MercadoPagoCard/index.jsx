import { useCallback, useEffect, useState } from 'react';
import { usePage } from '@inertiajs/react';
import Swal from 'sweetalert2';

import Spinner from '../Spinner';
import useSessao from '../../hooks/useSessao';
import api from '../../utils/api';
import { alerta_erro, confirmar } from '../../utils/alerts';
import { Actions, Button, Chip, Data, Details, Loading, Note, Warning } from './styles';

// cor do chip de cada situação do vínculo
const cores_situacao = { Vinculado: '#2e7d32', 'Falha na renovação': '#f9a825', Desvinculado: '#616161' };

// "2026-10-27T16:00:00.000000Z" → "27/10/2026 13:00" no horário de Brasília
const data_hora = (data) => (data
    ? new Date(data).toLocaleString('pt-BR', { timeZone: 'America/Sao_Paulo', day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })
    : '—');

// Mercado Pago na aba Integrações (spec 021, FR-021 e FR-022): situação do vínculo da spec 007 com as
// datas; só o Admin vincula, sincroniza de novo e desvincula. Ao voltar da aba do Mercado Pago, a
// situação é recarregada
export default function MercadoPagoCard() {
    const { tema } = usePage().props;
    const { sessao } = useSessao();
    const autorizacao = { headers: { Authorization: `Bearer ${sessao?.token}` } };
    const [dados, definir_dados] = useState(null);
    const [erro, definir_erro] = useState(false);
    const [enviando, definir_enviando] = useState(false);

    const carregar = useCallback(() => {
        if (!sessao?.token) return;

        api.get('/configuracoes/gateway', { headers: { Authorization: `Bearer ${sessao.token}` } })
            .then(({ data }) => {
                definir_dados(data.data);
                definir_erro(false);
            })
            .catch(() => definir_erro(true));
    }, [sessao?.token]);

    useEffect(() => {
        carregar();

        // voltou da aba do Mercado Pago: confere se o vínculo foi concluído
        const ao_voltar = () => document.visibilityState === 'visible' && carregar();
        document.addEventListener('visibilitychange', ao_voltar);

        return () => document.removeEventListener('visibilitychange', ao_voltar);
    }, [carregar]);

    const vincular = async () => {
        // a aba abre já no clique, para o navegador não bloquear; o endereço chega depois
        const aba = window.open('', '_blank');
        definir_enviando(true);

        try {
            const { data } = await api.post('/configuracoes/gateway/mercado-pago/autorizar', {}, autorizacao);

            if (aba) aba.location.href = data.url;
            else window.location.href = data.url;
        } catch (falha) {
            aba?.close();
            alerta_erro(falha);
        } finally {
            definir_enviando(false);
        }
    };

    const desvincular = async () => {
        if (!(await confirmar('Desvincular a conta do Mercado Pago? Os depósitos e saques pelo Mercado Pago param até um novo vínculo.', tema.temas))) return;

        definir_enviando(true);

        try {
            await api.delete('/configuracoes/gateway', autorizacao);
            Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Conta desvinculada.', showConfirmButton: false, timer: 2500 });
        } catch (falha) {
            alerta_erro(falha);
        } finally {
            definir_enviando(false);
            carregar();
        }
    };

    if (erro) return <Note>Não foi possível carregar a situação do Mercado Pago.</Note>;
    if (!dados) return <Loading><Spinner tamanho={28} cor={tema.temas} /></Loading>;

    const vinculado = dados.situacao !== 'Desvinculado';

    return (
        <>
            <Details>
                <Chip $cor={cores_situacao[dados.situacao] ?? '#616161'}>{dados.situacao}</Chip>
                {vinculado && (
                    <>
                        <Data><span>Vinculado em</span>{data_hora(dados.vinculado_em)}</Data>
                        <Data><span>Expira em</span>{data_hora(dados.expira_em)}</Data>
                        <Data><span>Renovado em</span>{data_hora(dados.renovado_em)}</Data>
                    </>
                )}
            </Details>
            {dados.falha_renovacao && (
                <Warning role="status">
                    <i className="material-icons" aria-hidden="true">warning</i>
                    {dados.falha_renovacao}
                </Warning>
            )}
            {dados.pode_alterar ? (
                <Actions>
                    <Button type="button" disabled={enviando} onClick={vincular}>
                        <i className="material-icons" aria-hidden="true">{vinculado ? 'sync' : 'link'}</i>
                        {vinculado ? 'Sincronizar novamente' : 'Vincular Mercado Pago'}
                    </Button>
                    {vinculado && (
                        <Button type="button" $contorno disabled={enviando} onClick={desvincular}>
                            <i className="material-icons" aria-hidden="true">link_off</i>
                            Desvincular
                        </Button>
                    )}
                </Actions>
            ) : (
                <Note>Só o administrador pode alterar.</Note>
            )}
        </>
    );
}
