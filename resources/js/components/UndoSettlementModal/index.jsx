import { useEffect, useState } from 'react';

import CashModal, {
    Button, erro_do_campo, Error, Field, GhostButton, Input, Label, Summary, SummaryRow, Textarea, Warning,
} from '../CashModal';
import api from '../../utils/api';
import { alerta_erro } from '../../utils/alerts';
import { formatar_valor } from '../../utils/charts';
import { formatar_data_hora } from '../../utils/dates';
import { Counter } from './styles';

const LIMITE_MOTIVO = 500;
const PERIGO = '#e53935';

// desfazer a última prestação de contas (spec 017, FR-022a): resumo da prestação, motivo obrigatório
// e senha; os lançamentos dela voltam a ficar abertos
export default function UndoSettlementModal({ prestacao, token, ao_fechar, ao_concluir }) {
    const aberto = Boolean(prestacao);
    const [motivo, definir_motivo] = useState('');
    const [senha, definir_senha] = useState('');
    const [erros, definir_erros] = useState({});
    const [salvando, definir_salvando] = useState(false);

    useEffect(() => {
        definir_motivo('');
        definir_senha('');
        definir_erros({});
    }, [prestacao?.id]);

    const desfazer = async () => {
        const faltando = {
            ...(motivo.trim() ? {} : { motivo: 'Informe o motivo para desfazer a prestação.' }),
            ...(senha ? {} : { senha: 'Confirme com a sua senha.' }),
        };

        if (Object.keys(faltando).length) {
            definir_erros(faltando);
            return;
        }

        definir_salvando(true);

        try {
            await api.post(`/caixa/prestacoes/${prestacao.id}/desfazer`, { motivo: motivo.trim(), senha }, { headers: { Authorization: `Bearer ${token}` } });
            ao_concluir('Prestação de contas desfeita.');
        } catch (erro) {
            const do_campo = Object.fromEntries(['motivo', 'senha'].map((campo) => [campo, erro_do_campo(erro, campo)]).filter(([, mensagem]) => mensagem));

            if (Object.keys(do_campo).length) definir_erros(do_campo);
            else alerta_erro(erro);
        } finally {
            definir_salvando(false);
        }
    };

    return (
        <CashModal
            aberto={aberto}
            icone="undo"
            titulo="Desfazer prestação"
            descricao="Só a última prestação do vendedor pode ser desfeita; os lançamentos dela voltam a ficar abertos."
            ao_fechar={ao_fechar}
            largura={480}
            rodape={(
                <>
                    <GhostButton type="button" onClick={ao_fechar}>Cancelar</GhostButton>
                    <Button type="button" $tom={PERIGO} disabled={salvando} onClick={desfazer}>{salvando ? 'Desfazendo…' : 'Desfazer'}</Button>
                </>
            )}
        >
            {prestacao && (
                <>
                    <Summary>
                        <SummaryRow>
                            <dt>Período</dt>
                            <dd>{`${prestacao.periodo_inicio ? formatar_data_hora(prestacao.periodo_inicio) : 'início'} a ${formatar_data_hora(prestacao.periodo_fim)}`}</dd>
                        </SummaryRow>
                        <SummaryRow><dt>Valor apurado</dt><dd>{formatar_valor(prestacao.valor_apurado)}</dd></SummaryRow>
                        <SummaryRow><dt>Valor recebido</dt><dd>{formatar_valor(prestacao.valor_recebido)}</dd></SummaryRow>
                    </Summary>
                    <Warning $tom={PERIGO}>
                        <i className="material-icons" aria-hidden="true">warning</i>
                        O saldo de caixa do vendedor volta a contar desde a prestação anterior.
                    </Warning>
                    <Field>
                        <Label htmlFor="desfazer_motivo">Motivo</Label>
                        <Textarea id="desfazer_motivo" maxLength={LIMITE_MOTIVO} value={motivo} $erro={Boolean(erros.motivo)} onChange={(evento) => definir_motivo(evento.target.value)} />
                        <Counter>{motivo.length}/{LIMITE_MOTIVO}</Counter>
                        {erros.motivo && <Error>{erros.motivo}</Error>}
                    </Field>
                    <Field>
                        <Label htmlFor="desfazer_senha">Confirme com a sua senha</Label>
                        <Input id="desfazer_senha" type="password" autoComplete="current-password" value={senha} $erro={Boolean(erros.senha)} onChange={(evento) => definir_senha(evento.target.value)} />
                        {erros.senha && <Error>{erros.senha}</Error>}
                    </Field>
                </>
            )}
        </CashModal>
    );
}
