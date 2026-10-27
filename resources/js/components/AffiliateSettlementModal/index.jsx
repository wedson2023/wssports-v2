import { useEffect, useState } from 'react';

import CashModal, {
    Button, Error, Field, GhostButton, Hint, Input, Label, Summary, SummaryRow, Textarea, Warning,
} from '../CashModal';
import Spinner from '../Spinner';
import api from '../../utils/api';
import { alerta_erro } from '../../utils/alerts';
import { formatar_valor } from '../../utils/charts';
import { erros_por_campo } from '../../utils/forms';
import { Loading, Money } from './styles';

const MAXIMO_OBSERVACAO = 500;

const negativo = (valor) => String(valor ?? '').startsWith('-');

// prestação de contas do afiliado (spec 022, FR-027 e FR-028): antes de confirmar, quantos lançamentos
// estão em aberto, o apurado e o que será pago (zero quando negativo); confirma com observação e a
// senha de quem presta. A data é a da confirmação (sem data livre, diferença em relação ao antigo)
export default function AffiliateSettlementModal({ afiliado, token, ao_fechar, ao_concluir }) {
    const aberto = Boolean(afiliado);
    const [resumo, definir_resumo] = useState(null);
    const [observacao, definir_observacao] = useState('');
    const [senha, definir_senha] = useState('');
    const [erros, definir_erros] = useState({});
    const [salvando, definir_salvando] = useState(false);

    const cabecalhos = { headers: { Authorization: `Bearer ${token}` } };

    useEffect(() => {
        if (!aberto) return undefined;

        let ativo = true;
        definir_resumo(null);
        definir_observacao('');
        definir_senha('');
        definir_erros({});

        // quantidade (meta.total) e soma (meta.soma) dos lançamentos em aberto
        api.get(`/afiliados/${afiliado.id}/lancamentos`, { params: { situacao: 'aberto', por_pagina: 1 }, ...cabecalhos })
            .then(({ data }) => ativo && definir_resumo({ quantidade: data.meta.total, apurado: data.meta.soma }))
            .catch((erro) => {
                if (!ativo) return;

                alerta_erro(erro);
                ao_fechar();
            });

        return () => {
            ativo = false;
        };
    }, [afiliado?.id]);

    const prestar = async () => {
        definir_salvando(true);
        definir_erros({});

        try {
            const { data } = await api.post(`/afiliados/${afiliado.id}/prestacoes`, { password: senha, observacao: observacao.trim() || null }, cabecalhos);
            ao_concluir(`Prestação registrada: ${formatar_valor(data.data.valor_pago)} pago.`);
        } catch (falha) {
            if (falha.response?.status === 422) definir_erros(erros_por_campo(falha));
            else alerta_erro(falha);
        } finally {
            definir_salvando(false);
        }
    };

    const pagar = resumo && negativo(resumo.apurado) ? '0.00' : resumo?.apurado;

    return (
        <CashModal
            aberto={aberto}
            icone="payments"
            titulo="Prestar contas"
            descricao="Confira o valor e confirme com a sua senha."
            ao_fechar={ao_fechar}
            rodape={(
                <>
                    <GhostButton type="button" onClick={ao_fechar}>Cancelar</GhostButton>
                    <Button type="button" disabled={salvando || !resumo || !senha} onClick={prestar}>{salvando ? 'Registrando…' : 'Prestar contas'}</Button>
                </>
            )}
        >
            {afiliado && !resumo && <Loading><Spinner tamanho={30} cor="currentColor" /></Loading>}
            {afiliado && resumo && (
                <>
                    <Summary>
                        <SummaryRow>
                            <dt>Afiliado</dt>
                            <dd>{afiliado.nome}</dd>
                        </SummaryRow>
                        <SummaryRow>
                            <dt>Lançamentos em aberto</dt>
                            <dd>{resumo.quantidade}</dd>
                        </SummaryRow>
                        <SummaryRow>
                            <dt>Valor apurado</dt>
                            <dd><Money $negativo={negativo(resumo.apurado)}>{formatar_valor(resumo.apurado)}</Money></dd>
                        </SummaryRow>
                        <SummaryRow>
                            <dt>Valor a pagar</dt>
                            <dd><Money>{formatar_valor(pagar)}</Money></dd>
                        </SummaryRow>
                        <SummaryRow>
                            <dt>Chave PIX</dt>
                            <dd>{afiliado.meio_pagamento ? `${afiliado.meio_pagamento.pix_tipo_chave} · ${afiliado.meio_pagamento.pix_chave}` : '—'}</dd>
                        </SummaryRow>
                    </Summary>
                    {negativo(resumo.apurado) && (
                        <Warning>
                            <i className="material-icons" aria-hidden="true">warning</i>
                            O saldo negativo será zerado e não passa para o próximo período.
                        </Warning>
                    )}
                    <Field>
                        <Label htmlFor="prestacao_observacao">Observação (opcional)</Label>
                        <Textarea id="prestacao_observacao" maxLength={MAXIMO_OBSERVACAO} placeholder="Ex.: Pago via Pix" value={observacao} $erro={Boolean(erros.observacao)} onChange={(evento) => definir_observacao(evento.target.value)} />
                        <Hint>{`${observacao.length}/${MAXIMO_OBSERVACAO}`}</Hint>
                        {erros.observacao && <Error role="alert">{erros.observacao}</Error>}
                    </Field>
                    <Field>
                        <Label htmlFor="prestacao_senha">Sua senha</Label>
                        <Input id="prestacao_senha" type="password" autoComplete="current-password" value={senha} $erro={Boolean(erros.password)} onChange={(evento) => definir_senha(evento.target.value)} />
                        {erros.password && <Error role="alert">{erros.password}</Error>}
                    </Field>
                </>
            )}
        </CashModal>
    );
}
