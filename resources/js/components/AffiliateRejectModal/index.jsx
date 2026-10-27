import { useEffect, useState } from 'react';

import CashModal, {
    Error, Field, GhostButton, Hint, Label, Textarea,
} from '../CashModal';
import api from '../../utils/api';
import { alerta_erro } from '../../utils/alerts';
import { erros_por_campo } from '../../utils/forms';
import { DangerButton } from './styles';

const MAXIMO_MOTIVO = 255;

// recusa do cadastro público (spec 022, FR-023, R-12), no padrão da recusa de saque: motivo opcional
// (enviado no e-mail ao afiliado), com contador e o erro do servidor no campo
export default function AffiliateRejectModal({ afiliado, token, ao_fechar, ao_concluir }) {
    const aberto = Boolean(afiliado);
    const [motivo, definir_motivo] = useState('');
    const [erros, definir_erros] = useState({});
    const [salvando, definir_salvando] = useState(false);

    useEffect(() => {
        if (!aberto) return;

        definir_motivo('');
        definir_erros({});
    }, [afiliado?.id]);

    const recusar = async () => {
        definir_salvando(true);
        definir_erros({});

        try {
            await api.post(`/afiliados/${afiliado.id}/recusar`, { motivo: motivo.trim() || null }, { headers: { Authorization: `Bearer ${token}` } });
            ao_concluir('Cadastro recusado. O afiliado recebe o aviso por e-mail.');
        } catch (falha) {
            if (falha.response?.status === 422) definir_erros(erros_por_campo(falha));
            else alerta_erro(falha);
        } finally {
            definir_salvando(false);
        }
    };

    return (
        <CashModal
            aberto={aberto}
            icone="person_off"
            titulo="Recusar cadastro"
            descricao="O afiliado recebe o motivo por e-mail e pode ser aprovado depois."
            ao_fechar={ao_fechar}
            rodape={(
                <>
                    <GhostButton type="button" onClick={ao_fechar}>Cancelar</GhostButton>
                    <DangerButton type="button" disabled={salvando} onClick={recusar}>{salvando ? 'Recusando…' : 'Recusar'}</DangerButton>
                </>
            )}
        >
            {afiliado && (
                <>
                    <Hint>{`${afiliado.nome} (${afiliado.login})`}</Hint>
                    <Field>
                        <Label htmlFor="recusar_motivo">Motivo (opcional)</Label>
                        <Textarea id="recusar_motivo" maxLength={MAXIMO_MOTIVO} value={motivo} $erro={Boolean(erros.motivo)} onChange={(evento) => definir_motivo(evento.target.value)} />
                        <Hint>{`${motivo.length}/${MAXIMO_MOTIVO}`}</Hint>
                        {erros.motivo && <Error role="alert">{erros.motivo}</Error>}
                    </Field>
                    {erros.situacao && <Error role="alert">{erros.situacao}</Error>}
                </>
            )}
        </CashModal>
    );
}
