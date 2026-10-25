import { useCallback, useEffect, useRef, useState } from 'react';
import { usePage } from '@inertiajs/react';
import Swal from 'sweetalert2';

import Spinner from '../Spinner';
import useSessao from '../../hooks/useSessao';
import api from '../../utils/api';
import { alerta_erro, confirmar } from '../../utils/alerts';
import { Actions, Button, Chip, Data, Details, Loading, Note, QrArea, Steps } from './styles';

// consulta da situação enquanto o QR está na tela (SC-006: "Conectado" em até 5 s)
const INTERVALO_SITUACAO = 3000;

// o QR da Evolution expira em cerca de 40 s: pede um novo antes disso (research R-12)
const INTERVALO_QR = 30000;

const cores_situacao = { Conectado: '#2e7d32', 'Aguardando leitura': '#1e88e5', Desconectado: '#616161' };

// 5511977776666 → +55 (11) 97777-6666
const formatar_numero = (numero) => {
    const partes = /^(\d{2})(\d{2})(\d{4,5})(\d{4})$/.exec(numero ?? '');

    return partes ? `+${partes[1]} (${partes[2]}) ${partes[3]}-${partes[4]}` : numero;
};

// robô do WhatsApp na aba Integrações (spec 021, FR-023 a FR-027): situação vinda da Evolution API,
// conectar lendo o QR code (renovado sozinho enquanto não for lido) e desconectar; só o Admin age.
// As consultas param ao conectar, ao sair da aba ou ao fechar a tela
export default function WhatsAppBotCard() {
    const { tema } = usePage().props;
    const { sessao } = useSessao();
    const token = sessao?.token;
    const [dados, definir_dados] = useState(null);
    const [qrcode, definir_qrcode] = useState(null);
    const [enviando, definir_enviando] = useState(false);
    const consultas = useRef([]);

    const autorizacao = useCallback(() => ({ headers: { Authorization: `Bearer ${token}` } }), [token]);

    const parar = useCallback(() => {
        consultas.current.forEach((intervalo) => window.clearInterval(intervalo));
        consultas.current = [];
    }, []);

    // nas consultas repetidas (silenciosa), uma falha passageira não abre alerta
    const carregar = useCallback(async (silenciosa = false) => {
        try {
            const { data } = await api.get('/configuracoes/robo-whatsapp', autorizacao());
            definir_dados(data.data);

            return data.data;
        } catch (falha) {
            if (!silenciosa) alerta_erro(falha);

            return null;
        }
    }, [autorizacao]);

    useEffect(() => {
        if (token) carregar();

        return parar;
    }, [token]);

    const pedir_qr = async () => {
        const { data } = await api.post('/configuracoes/robo-whatsapp/conectar', {}, autorizacao());

        if (data.data.situacao === 'Conectado') {
            parar();
            definir_qrcode(null);
            await carregar();

            return false;
        }

        definir_qrcode(data.data.qrcode);
        definir_dados((atuais) => ({ ...atuais, situacao: data.data.situacao }));

        return true;
    };

    const conectar = async () => {
        definir_enviando(true);

        try {
            if (!(await pedir_qr())) return;

            parar();
            consultas.current = [
                window.setInterval(async () => {
                    const atual = await carregar(true);

                    if (atual?.situacao === 'Conectado') {
                        parar();
                        definir_qrcode(null);
                        Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Robô conectado.', showConfirmButton: false, timer: 2500 });
                    }
                }, INTERVALO_SITUACAO),
                window.setInterval(() => pedir_qr().catch(parar), INTERVALO_QR),
            ];
        } catch (falha) {
            alerta_erro(falha);
        } finally {
            definir_enviando(false);
        }
    };

    const desconectar = async () => {
        if (!(await confirmar('Desconectar o robô do WhatsApp? As mensagens automáticas param até conectar de novo.', tema.temas))) return;

        definir_enviando(true);
        parar();
        definir_qrcode(null);

        try {
            await api.delete('/configuracoes/robo-whatsapp', autorizacao());
            Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Robô desconectado.', showConfirmButton: false, timer: 2500 });
        } catch (falha) {
            alerta_erro(falha);
        } finally {
            definir_enviando(false);
            carregar();
        }
    };

    if (!dados) return <Loading><Spinner tamanho={28} cor={tema.temas} /></Loading>;

    const situacao = dados.disponivel === false ? 'Indisponível' : dados.situacao;
    const conectado = dados.situacao === 'Conectado';

    return (
        <>
            <Details>
                <Chip $cor={situacao === 'Indisponível' ? '#e53935' : (cores_situacao[situacao] ?? '#616161')}>{situacao}</Chip>
                {conectado && dados.numero && <Data><span>Número</span>{formatar_numero(dados.numero)}</Data>}
                {conectado && dados.perfil && <Data><span>Perfil</span>{dados.perfil}</Data>}
            </Details>
            {dados.disponivel === false && <Note role="status">{dados.mensagem}</Note>}

            {qrcode && (
                <QrArea>
                    <img src={qrcode} alt="QR code para conectar o WhatsApp da banca" />
                    <Steps>
                        <li>Abra o WhatsApp no celular da banca.</li>
                        <li>Toque em <strong>Aparelhos conectados</strong> e depois em <strong>Conectar aparelho</strong>.</li>
                        <li>Aponte a câmera para este QR code. Ele é renovado sozinho até ser lido.</li>
                    </Steps>
                </QrArea>
            )}

            {dados.pode_alterar ? (
                <Actions>
                    {!conectado && !qrcode && (
                        <Button type="button" disabled={enviando} onClick={conectar}>
                            <i className="material-icons" aria-hidden="true">qr_code_2</i>
                            Conectar
                        </Button>
                    )}
                    {(conectado || qrcode) && (
                        <Button type="button" $contorno disabled={enviando} onClick={desconectar}>
                            <i className="material-icons" aria-hidden="true">link_off</i>
                            Desconectar
                        </Button>
                    )}
                </Actions>
            ) : (
                <Note>Só o administrador pode alterar.</Note>
            )}
        </>
    );
}
