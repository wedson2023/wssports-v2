import { useEffect, useState } from 'react';

import CashModal, {
    Button, Error, Field, Fields, GhostButton, Input, Label, Summary, SummaryRow, Warning,
} from '../CashModal';
import { tipos_ganho } from '../../hooks/useAfiliadosPainel';
import api from '../../utils/api';
import { alerta_erro } from '../../utils/alerts';
import { erros_por_campo } from '../../utils/forms';
import { texto_ganho } from '../AffiliatesTable';
import { Select } from './styles';

// troca da regra de ganho (spec 022, FR-019): a vigente é encerrada agora e a nova vale para os
// próximos lançamentos; os já gravados não mudam
export default function AffiliateRuleModal({ afiliado, token, ao_fechar, ao_concluir }) {
    const aberto = Boolean(afiliado);
    const [dados, definir_dados] = useState({ tipo_ganho: '', percentual: '' });
    const [erros, definir_erros] = useState({});
    const [salvando, definir_salvando] = useState(false);

    useEffect(() => {
        if (!aberto) return;

        definir_erros({});
        definir_dados({
            tipo_ganho: afiliado.comissao?.tipo_ganho ?? '',
            percentual: afiliado.comissao ? String(Number(afiliado.comissao.percentual)).replace('.', ',') : '',
        });
    }, [afiliado?.id]);

    const trocar = async () => {
        definir_salvando(true);
        definir_erros({});

        try {
            await api.put(`/afiliados/${afiliado.id}/comissao`, {
                tipo_ganho: dados.tipo_ganho,
                percentual: dados.percentual.trim().replace(',', '.'),
            }, { headers: { Authorization: `Bearer ${token}` } });
            ao_concluir('Regra de ganho trocada.');
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
            icone="percent"
            titulo="Trocar regra de ganho"
            descricao="A nova regra vale a partir de agora para os próximos lançamentos."
            ao_fechar={ao_fechar}
            rodape={(
                <>
                    <GhostButton type="button" onClick={ao_fechar}>Cancelar</GhostButton>
                    <Button type="button" disabled={salvando || !dados.tipo_ganho || !dados.percentual.trim()} onClick={trocar}>{salvando ? 'Trocando…' : 'Trocar regra'}</Button>
                </>
            )}
        >
            {afiliado && (
                <>
                    <Summary>
                        <SummaryRow>
                            <dt>Afiliado</dt>
                            <dd>{afiliado.nome}</dd>
                        </SummaryRow>
                        <SummaryRow>
                            <dt>Regra vigente</dt>
                            <dd>{texto_ganho(afiliado)}</dd>
                        </SummaryRow>
                    </Summary>
                    <Fields>
                        <Field>
                            <Label htmlFor="regra_tipo_ganho">Tipo de ganho</Label>
                            <Select id="regra_tipo_ganho" value={dados.tipo_ganho} $erro={Boolean(erros.tipo_ganho)} onChange={(evento) => definir_dados((atual) => ({ ...atual, tipo_ganho: evento.target.value }))}>
                                <option value="">Selecione</option>
                                {tipos_ganho.map((tipo) => <option key={tipo} value={tipo}>{tipo}</option>)}
                            </Select>
                            {erros.tipo_ganho && <Error role="alert">{erros.tipo_ganho}</Error>}
                        </Field>
                        <Field>
                            <Label htmlFor="regra_percentual">Percentual (%)</Label>
                            <Input id="regra_percentual" inputMode="decimal" placeholder="De 0 a 100" value={dados.percentual} $erro={Boolean(erros.percentual)} onChange={(evento) => definir_dados((atual) => ({ ...atual, percentual: evento.target.value }))} />
                            {erros.percentual && <Error role="alert">{erros.percentual}</Error>}
                        </Field>
                    </Fields>
                    <Warning>
                        <i className="material-icons" aria-hidden="true">info</i>
                        Os lançamentos já gravados continuam com a regra antiga; não é preciso zerar o saldo antes.
                    </Warning>
                </>
            )}
        </CashModal>
    );
}
