import { useEffect, useState } from 'react';

import CashModal, {
    Button, erro_do_campo, Error, Field, GhostButton, Hint, Input, Label, Summary, SummaryRow, Textarea, Warning,
} from '../CashModal';
import api from '../../utils/api';
import { alerta_erro } from '../../utils/alerts';
import { formatar_valor } from '../../utils/charts';
import { DangerButton } from './styles';

const MAXIMO_MOTIVO = 255;

// estornar a promoção inteira (spec 020, FR-026, contracts/paginas §6; o antigo "reverter bônus"):
// mostra quantos receberam e quanto foi creditado e pede motivo e senha; o estorno roda em segundo
// plano e a lista acompanha o progresso
export default function PromotionReversalModal({ promocao, token, ao_fechar, ao_concluir }) {
    const aberto = Boolean(promocao);
    const [motivo, definir_motivo] = useState('');
    const [senha, definir_senha] = useState('');
    const [erros, definir_erros] = useState({});
    const [salvando, definir_salvando] = useState(false);

    useEffect(() => {
        if (!aberto) return;

        definir_motivo('');
        definir_senha('');
        definir_erros({});
    }, [promocao?.id]);

    const estornar = async () => {
        definir_salvando(true);
        definir_erros({});

        try {
            await api.post(`/clientes-promocoes/${promocao.id}/estornar`, { motivo: motivo.trim(), senha }, { headers: { Authorization: `Bearer ${token}` } });
            ao_concluir('Estorno iniciado. Acompanhe o progresso na lista.');
        } catch (falha) {
            if (falha.response?.status === 422) {
                definir_erros({
                    motivo: erro_do_campo(falha, 'motivo'),
                    senha: erro_do_campo(falha, 'senha'),
                    geral: erro_do_campo(falha, 'promocao'),
                });
            } else {
                alerta_erro(falha);
            }
        } finally {
            definir_salvando(false);
        }
    };

    return (
        <CashModal
            aberto={aberto}
            icone="undo"
            titulo="Estornar promoção"
            descricao="O bônus será retirado de todos que o receberam e a promoção será desativada."
            ao_fechar={ao_fechar}
            rodape={(
                <>
                    <GhostButton type="button" onClick={ao_fechar}>Cancelar</GhostButton>
                    <DangerButton type="button" disabled={salvando || !motivo.trim() || !senha} onClick={estornar}>{salvando ? 'Estornando…' : 'Estornar'}</DangerButton>
                </>
            )}
        >
            {promocao && (
                <>
                    <Summary>
                        <SummaryRow>
                            <dt>Promoção</dt>
                            <dd>{promocao.nome}</dd>
                        </SummaryRow>
                        <SummaryRow>
                            <dt>Clientes que receberam</dt>
                            <dd>{promocao.uso?.clientes ?? '—'}</dd>
                        </SummaryRow>
                        <SummaryRow>
                            <dt>Valor creditado</dt>
                            <dd>{promocao.uso ? formatar_valor(promocao.uso.creditado) : '—'}</dd>
                        </SummaryRow>
                    </Summary>
                    <Warning>
                        <i className="material-icons" aria-hidden="true">warning</i>
                        Sai do saldo de bônus de cada cliente o que ainda não foi convertido. Bônus convertidos, expirados ou excluídos não são mexidos. As apostas não são alteradas.
                    </Warning>
                    <Field>
                        <Label htmlFor="estorno_promocao_motivo">Motivo</Label>
                        <Textarea id="estorno_promocao_motivo" maxLength={MAXIMO_MOTIVO} value={motivo} $erro={Boolean(erros.motivo)} onChange={(evento) => definir_motivo(evento.target.value)} />
                        <Hint>{`${motivo.length}/${MAXIMO_MOTIVO}`}</Hint>
                        {erros.motivo && <Error role="alert">{erros.motivo}</Error>}
                    </Field>
                    <Field>
                        <Label htmlFor="estorno_promocao_senha">Sua senha</Label>
                        <Input id="estorno_promocao_senha" type="password" autoComplete="current-password" value={senha} $erro={Boolean(erros.senha)} onChange={(evento) => definir_senha(evento.target.value)} />
                        {erros.senha && <Error role="alert">{erros.senha}</Error>}
                    </Field>
                    {erros.geral && <Error role="alert">{erros.geral}</Error>}
                </>
            )}
        </CashModal>
    );
}
