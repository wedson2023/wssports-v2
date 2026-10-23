import { useEffect, useState } from 'react';

import CashModal, { Button, erro_do_campo, Error, Field, GhostButton, Label, Summary, SummaryRow, Textarea } from '../CashModal';
import api from '../../utils/api';
import { alerta_erro } from '../../utils/alerts';
import { formatar_valor } from '../../utils/charts';
import { Counter } from './styles';

const LIMITE_MOTIVO = 255;

// recusar um saque pendente (spec 019, FR-026; regras da spec 007): motivo obrigatório, que o
// cliente vê; o valor volta para o saldo dele
export default function WithdrawalDecisionModal({ saque, token, ao_fechar, ao_concluir }) {
    const aberto = Boolean(saque);
    const [motivo, definir_motivo] = useState('');
    const [erro, definir_erro] = useState(null);
    const [salvando, definir_salvando] = useState(false);

    useEffect(() => {
        if (!aberto) return;

        definir_motivo('');
        definir_erro(null);
    }, [saque?.id]);

    const recusar = async () => {
        if (!motivo.trim()) {
            definir_erro('Informe o motivo da recusa.');
            return;
        }

        definir_salvando(true);

        try {
            await api.post(`/saques/${saque.id}/recusar`, { motivo: motivo.trim() }, { headers: { Authorization: `Bearer ${token}` } });
            ao_concluir('Saque recusado; o valor voltou para o saldo do cliente.');
        } catch (falha) {
            const mensagem = erro_do_campo(falha, 'motivo');

            if (mensagem) definir_erro(mensagem);
            else alerta_erro(falha);

            // saque já decidido por outra pessoa (422 sem erro no motivo): fecha e atualiza a fila
            if (falha.response?.status === 422 && !mensagem) ao_concluir(null);
        } finally {
            definir_salvando(false);
        }
    };

    return (
        <CashModal
            aberto={aberto}
            icone="block"
            titulo="Recusar saque"
            descricao="O valor volta para o saldo do cliente."
            ao_fechar={ao_fechar}
            rodape={(
                <>
                    <GhostButton type="button" onClick={ao_fechar}>Cancelar</GhostButton>
                    <Button type="button" disabled={salvando} onClick={recusar}>{salvando ? 'Recusando…' : 'Recusar'}</Button>
                </>
            )}
        >
            {saque && (
                <Summary>
                    <SummaryRow>
                        <dt>Cliente</dt>
                        <dd>{saque.cliente?.nome ?? '—'}</dd>
                    </SummaryRow>
                    <SummaryRow>
                        <dt>Valor</dt>
                        <dd>{formatar_valor(saque.valor)}</dd>
                    </SummaryRow>
                </Summary>
            )}
            <Field>
                <Label htmlFor="saque_motivo">Motivo</Label>
                <Textarea id="saque_motivo" maxLength={LIMITE_MOTIVO} value={motivo} onChange={(evento) => definir_motivo(evento.target.value)} />
                <Counter>{motivo.length}/{LIMITE_MOTIVO}</Counter>
                {erro && <Error role="alert">{erro}</Error>}
            </Field>
        </CashModal>
    );
}
