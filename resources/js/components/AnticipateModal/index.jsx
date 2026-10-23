import { useEffect, useState } from 'react';

import CashModal, { Button, erro_do_campo, Error, Field, GhostButton, Input, Label, Summary, SummaryRow, Warning } from '../CashModal';
import Spinner from '../Spinner';
import api from '../../utils/api';
import { alerta_erro } from '../../utils/alerts';
import { formatar_valor } from '../../utils/charts';
import { Loading } from './styles';

// antecipar pendentes (spec 019, FR-019, contracts/paginas §5; o "subir créditos" do antigo): mostra
// os prêmios e encerramentos na janela de segurança e, com a senha, credita na hora
export default function AnticipateModal({ cliente, token, ao_fechar, ao_concluir }) {
    const aberto = Boolean(cliente);
    const [pendentes, definir_pendentes] = useState(null);
    const [senha, definir_senha] = useState('');
    const [erro, definir_erro] = useState(null);
    const [salvando, definir_salvando] = useState(false);
    const autorizacao = { headers: { Authorization: `Bearer ${token}` } };

    useEffect(() => {
        if (!aberto) return undefined;

        let ativo = true;
        definir_pendentes(null);
        definir_senha('');
        definir_erro(null);

        api.get(`/clientes/${cliente.id}/pendentes`, autorizacao)
            .then(({ data }) => ativo && definir_pendentes(data))
            .catch((falha) => {
                if (!ativo) return;

                alerta_erro(falha);
                ao_fechar();
            });

        return () => {
            ativo = false;
        };
    }, [cliente?.id]);

    const sem_pendentes = pendentes && Number(pendentes.total) === 0;

    const antecipar = async () => {
        definir_salvando(true);
        definir_erro(null);

        try {
            const { data } = await api.post(`/clientes/${cliente.id}/antecipar`, { senha }, autorizacao);
            ao_concluir(`${formatar_valor(data.creditado)} creditados. Saldo atual: ${formatar_valor(data.saldo)}.`);
        } catch (falha) {
            if (falha.response?.status === 422) definir_erro(erro_do_campo(falha, 'senha') ?? erro_do_campo(falha, 'cliente') ?? falha.response.data.message);
            else alerta_erro(falha);
        } finally {
            definir_salvando(false);
        }
    };

    return (
        <CashModal
            aberto={aberto}
            icone="arrow_circle_up"
            titulo={cliente ? `Antecipar pendentes · ${cliente.nome}` : 'Antecipar pendentes'}
            descricao="Libera agora os prêmios e encerramentos que estão na janela de segurança."
            ao_fechar={ao_fechar}
            rodape={sem_pendentes ? <Button type="button" onClick={ao_fechar}>Fechar</Button> : (
                <>
                    <GhostButton type="button" onClick={ao_fechar}>Cancelar</GhostButton>
                    <Button type="button" disabled={!pendentes || salvando || !senha} onClick={antecipar}>{salvando ? 'Antecipando…' : 'Antecipar'}</Button>
                </>
            )}
        >
            {!pendentes && <Loading><Spinner tamanho={30} cor="currentColor" /></Loading>}
            {sem_pendentes && <p>Nenhum prêmio ou encerramento pendente.</p>}
            {pendentes && !sem_pendentes && (
                <>
                    <Summary>
                        <SummaryRow>
                            <dt>{`Prêmios (${pendentes.premios.quantidade})`}</dt>
                            <dd>{formatar_valor(pendentes.premios.valor)}</dd>
                        </SummaryRow>
                        <SummaryRow>
                            <dt>{`Encerramentos (${pendentes.encerramentos.quantidade})`}</dt>
                            <dd>{formatar_valor(pendentes.encerramentos.valor)}</dd>
                        </SummaryRow>
                        <SummaryRow>
                            <dt>Total</dt>
                            <dd>{formatar_valor(pendentes.total)}</dd>
                        </SummaryRow>
                    </Summary>
                    <Warning>
                        <i className="material-icons" aria-hidden="true">warning</i>
                        Ao antecipar, o tempo de segurança para análise das apostas é dispensado e o valor vai para o saldo do cliente agora.
                    </Warning>
                    <Field>
                        <Label htmlFor="antecipar_senha">Sua senha</Label>
                        <Input id="antecipar_senha" type="password" autoComplete="current-password" value={senha} onChange={(evento) => definir_senha(evento.target.value)} />
                    </Field>
                </>
            )}
            {erro && <Error role="alert">{erro}</Error>}
        </CashModal>
    );
}
