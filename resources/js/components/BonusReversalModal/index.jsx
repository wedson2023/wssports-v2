import { useEffect, useState } from 'react';

import CashModal, {
    erro_do_campo, Error, Field, GhostButton, Hint, Input, Label, Summary, SummaryRow, Textarea,
} from '../CashModal';
import api from '../../utils/api';
import { alerta_erro } from '../../utils/alerts';
import { formatar_valor } from '../../utils/charts';
import { centavos_para_texto, para_centavos } from '../../utils/money';
import { DangerButton } from './styles';

const MAXIMO_MOTIVO = 255;

// estornar o bônus de um cliente só (spec 020, FR-027, contracts/paginas §9): mostra o que será
// retirado (o menor entre o saldo de bônus atual e o valor do bônus) e pede motivo e senha
export default function BonusReversalModal({ bonus, token, ao_fechar, ao_concluir }) {
    const aberto = Boolean(bonus);
    const [motivo, definir_motivo] = useState('');
    const [senha, definir_senha] = useState('');
    const [erros, definir_erros] = useState({});
    const [salvando, definir_salvando] = useState(false);

    useEffect(() => {
        if (!aberto) return;

        definir_motivo('');
        definir_senha('');
        definir_erros({});
    }, [bonus?.id]);

    // mesma regra do servidor, só para mostrar; o servidor recalcula com o saldo da hora
    const a_retirar = bonus
        ? Math.max(0, Math.min(para_centavos(bonus.saldo_promocional), para_centavos(bonus.valor)))
        : 0;

    const estornar = async () => {
        definir_salvando(true);
        definir_erros({});

        try {
            await api.post(`/clientes-bonus/${bonus.id}/estornar`, { motivo: motivo.trim(), senha }, { headers: { Authorization: `Bearer ${token}` } });
            ao_concluir('Bônus estornado.');
        } catch (falha) {
            if (falha.response?.status === 422) {
                definir_erros({
                    motivo: erro_do_campo(falha, 'motivo'),
                    senha: erro_do_campo(falha, 'senha'),
                    geral: erro_do_campo(falha, 'bonus'),
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
            titulo="Estornar bônus"
            descricao="O bônus será retirado só deste cliente."
            ao_fechar={ao_fechar}
            rodape={(
                <>
                    <GhostButton type="button" onClick={ao_fechar}>Cancelar</GhostButton>
                    <DangerButton type="button" disabled={salvando || !motivo.trim() || !senha} onClick={estornar}>{salvando ? 'Estornando…' : 'Estornar'}</DangerButton>
                </>
            )}
        >
            {bonus && (
                <>
                    <Summary>
                        <SummaryRow>
                            <dt>Cliente</dt>
                            <dd>{`${bonus.cliente.nome} (ID ${bonus.cliente.id})`}</dd>
                        </SummaryRow>
                        <SummaryRow>
                            <dt>Promoção</dt>
                            <dd>{bonus.promocao.nome}</dd>
                        </SummaryRow>
                        <SummaryRow>
                            <dt>Valor do bônus</dt>
                            <dd>{formatar_valor(bonus.valor)}</dd>
                        </SummaryRow>
                        <SummaryRow>
                            <dt>Será retirado</dt>
                            <dd>{formatar_valor(centavos_para_texto(a_retirar))}</dd>
                        </SummaryRow>
                    </Summary>
                    <Field>
                        <Label htmlFor="estorno_bonus_motivo">Motivo</Label>
                        <Textarea id="estorno_bonus_motivo" maxLength={MAXIMO_MOTIVO} value={motivo} $erro={Boolean(erros.motivo)} onChange={(evento) => definir_motivo(evento.target.value)} />
                        <Hint>{`${motivo.length}/${MAXIMO_MOTIVO}`}</Hint>
                        {erros.motivo && <Error role="alert">{erros.motivo}</Error>}
                    </Field>
                    <Field>
                        <Label htmlFor="estorno_bonus_senha">Sua senha</Label>
                        <Input id="estorno_bonus_senha" type="password" autoComplete="current-password" value={senha} $erro={Boolean(erros.senha)} onChange={(evento) => definir_senha(evento.target.value)} />
                        {erros.senha && <Error role="alert">{erros.senha}</Error>}
                    </Field>
                    {erros.geral && <Error role="alert">{erros.geral}</Error>}
                </>
            )}
        </CashModal>
    );
}
