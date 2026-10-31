import { useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';

import Spinner from '../../components/Spinner';
import useSessao from '../../hooks/useSessao';
import api from '../../utils/api';
import { alerta_erro, alerta_sucesso } from '../../utils/alerts';
import { Button, Card, Icon, Logo, Message, Page, PhoneInput } from './styles';

// confirmação de e-mail do cadastro (spec 025, US8): abrir o link não confirma nada, porque leitores
// automáticos de e-mail abrem links sozinhos; só o botão confirma (FR-031). Com o link vencido, pede o
// telefone para mandar um novo
export default function ConfirmEmail({ token }) {
    const { tema, nome_sistema } = usePage().props;
    const { entrar_confirmado } = useSessao();
    const [etapa, definir_etapa] = useState(token ? 'confirmar' : 'expirado');
    const [telefone, definir_telefone] = useState('');
    const [enviando, definir_enviando] = useState(false);

    const confirmar_email = async () => {
        definir_enviando(true);

        try {
            const { data } = await api.post('/area-cliente/confirmar-email', { token });

            // cliente bloqueado no painel confirma o e-mail, mas não recebe acesso
            if (!data.token) {
                definir_etapa('confirmado');
                return;
            }

            entrar_confirmado(data);
            alerta_sucesso('E-mail confirmado! Você já está conectado.');
            router.visit('/');
        } catch (erro) {
            if (erro.response?.status === 422) definir_etapa('expirado');
            else alerta_erro(erro);
        } finally {
            definir_enviando(false);
        }
    };

    const reenviar = async (evento) => {
        evento.preventDefault();
        definir_enviando(true);

        try {
            const { data } = await api.post('/area-cliente/confirmar-email/reenviar', { ddi: '55', telefone: telefone.replace(/\D/g, '') });
            alerta_sucesso(data.message);
            definir_etapa('reenviado');
        } catch (erro) {
            alerta_erro(erro);
        } finally {
            definir_enviando(false);
        }
    };

    return (
        <Page>
            <Head title={nome_sistema} />
            <Card>
                <Logo src={tema.logo} alt={nome_sistema} />

                {etapa === 'confirmar' && (
                    <>
                        <Icon className="material-icons" $sucesso aria-hidden="true">mark_email_read</Icon>
                        <Message>Clique no botão para confirmar seu e-mail e entrar na sua conta.</Message>
                        <Button type="button" disabled={enviando} onClick={confirmar_email}>
                            {enviando ? <Spinner tamanho={18} /> : 'Confirmar e-mail'}
                        </Button>
                    </>
                )}

                {etapa === 'confirmado' && (
                    <>
                        <Icon className="material-icons" $sucesso aria-hidden="true">check_circle</Icon>
                        <Message>E-mail confirmado.</Message>
                    </>
                )}

                {etapa === 'expirado' && (
                    <form onSubmit={reenviar}>
                        <Icon className="material-icons" aria-hidden="true">error</Icon>
                        <Message>Link expirado. Informe o telefone do cadastro para receber um novo link.</Message>
                        <PhoneInput
                            type="tel"
                            inputMode="numeric"
                            autoComplete="tel-national"
                            aria-label="Telefone com DDD"
                            placeholder="Telefone com DDD"
                            value={telefone}
                            onChange={(evento) => definir_telefone(evento.currentTarget.value)}
                            required
                        />
                        <Button type="submit" disabled={enviando}>
                            {enviando ? <Spinner tamanho={18} /> : 'Enviar novo link'}
                        </Button>
                    </form>
                )}

                {etapa === 'reenviado' && (
                    <>
                        <Icon className="material-icons" $sucesso aria-hidden="true">forward_to_inbox</Icon>
                        <Message>Se houver cadastro pendente, o novo link chega no seu e-mail.</Message>
                    </>
                )}
            </Card>
        </Page>
    );
}
