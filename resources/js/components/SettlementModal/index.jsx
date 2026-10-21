import { useEffect, useState } from 'react';

import CashModal, {
    Button, erro_do_campo, Error, Field, Fields, GhostButton, Hint, Input, Label, ler_valor, Summary, SummaryRow, Textarea, valor_para_campo, Warning,
} from '../CashModal';
import Spinner from '../Spinner';
import api from '../../utils/api';
import { alerta_erro } from '../../utils/alerts';
import { formatar_valor } from '../../utils/charts';
import { formatar_data_hora } from '../../utils/dates';
import { agora_local } from '../../utils/periods';
import { Amount, Loading, Seller, Sellers } from './styles';

const verde = '#43a047';
const vermelho = '#e53935';

// cor do valor pelo sinal
const cor_valor = (valor) => (Number(valor) > 0 ? verde : (Number(valor) < 0 ? vermelho : undefined));

// prestação de contas (spec 017, US2, contracts/paginas §4): no vendedor, o resumo do período desde a
// última prestação, o valor recebido (a diferença fica para a próxima) e a senha; no gerente, o
// lote com o valor apurado de cada vendedor. alvo = { tipo: 'vendedor'|'gerente', id, nome }
export default function SettlementModal({ alvo, token, ao_fechar, ao_concluir }) {
    const aberto = Boolean(alvo);
    const lote = alvo?.tipo === 'gerente';
    const caminho = alvo ? `/caixa/${lote ? 'gerentes' : 'vendedores'}/${alvo.id}` : null;
    const cabecalhos = { Authorization: `Bearer ${token}` };

    const [ate, definir_ate] = useState('');
    const [resumo, definir_resumo] = useState({ dados: null, carregando: false, erro: null });
    const [recebido, definir_recebido] = useState('');
    const [observacao, definir_observacao] = useState('');
    const [passo, definir_passo] = useState('resumo');
    const [senha, definir_senha] = useState('');
    const [erros, definir_erros] = useState({});
    const [salvando, definir_salvando] = useState(false);

    // ao abrir: até agora, sem senha
    useEffect(() => {
        if (!aberto) return;

        definir_ate(agora_local());
        definir_passo('resumo');
        definir_senha('');
        definir_observacao('');
        definir_erros({});
    }, [alvo?.tipo, alvo?.id]);

    // resumo do período a cada data escolhida
    useEffect(() => {
        if (!aberto || !ate) return undefined;

        let ativo = true;
        definir_resumo((atual) => ({ ...atual, carregando: true, erro: null }));

        api.get(`${caminho}/prestacao`, { params: { ate }, headers: cabecalhos })
            .then(({ data }) => {
                if (!ativo) return;

                definir_resumo({ dados: data, caminho, carregando: false, erro: null });
                if (!lote) definir_recebido(valor_para_campo(data.valor_apurado));
            })
            .catch((erro) => ativo && definir_resumo({ dados: null, carregando: false, erro }));

        return () => {
            ativo = false;
        };
    }, [aberto, caminho, ate]);

    // o resumo só vale para o alvo que o pediu (vendedor e lote têm formatos diferentes)
    const dados = resumo.caminho === caminho ? resumo.dados : null;
    const valor_recebido = lote ? null : ler_valor(recebido, { permitir_negativo: true });
    const diferenca = !lote && dados && valor_recebido !== null ? (Number(dados.valor_apurado) - Number(valor_recebido)).toFixed(2) : '0.00';
    const sem_movimento = dados && (lote ? !dados.vendedores.some((vendedor) => vendedor.tem_movimento) : !dados.tem_movimento);

    const continuar = () => {
        if (!lote && valor_recebido === null) {
            definir_erros({ valor_recebido: 'Informe o valor recebido, com até duas casas.' });
            return;
        }

        definir_erros({});
        definir_passo('senha');
    };

    const confirmar = async () => {
        if (!senha) {
            definir_erros({ senha: 'Confirme com a sua senha.' });
            return;
        }

        definir_salvando(true);

        try {
            const { data } = await api.post(`${caminho}/prestacoes`, {
                ate, senha, observacao: observacao || null, ...(lote ? {} : { valor_recebido }),
            }, { headers: cabecalhos });

            ao_concluir(lote
                ? `Prestação de contas de ${data.prestacoes} vendedor(es) registrada${data.pulados ? `; ${data.pulados} sem movimento pulado(s)` : ''}.`
                : `Prestação de contas de ${alvo.nome} registrada.`);
        } catch (erro) {
            const status = erro.response?.status;

            if (erro_do_campo(erro, 'senha')) {
                definir_erros({ senha: erro_do_campo(erro, 'senha') });
            } else if (status === 409 || erro_do_campo(erro, 'ate')) {
                // o caixa mudou (outra prestação): volta ao resumo atualizado
                definir_erros({ ate: erro.response.data.message ?? erro_do_campo(erro, 'ate') });
                definir_passo('resumo');
                definir_ate(agora_local());
            } else {
                alerta_erro(erro);
            }
        } finally {
            definir_salvando(false);
        }
    };

    const rodape = passo === 'resumo'
        ? (
            <>
                <GhostButton type="button" onClick={ao_fechar}>Cancelar</GhostButton>
                <Button type="button" disabled={!dados || resumo.carregando || sem_movimento} onClick={continuar}>Continuar</Button>
            </>
        )
        : (
            <>
                <GhostButton type="button" onClick={() => definir_passo('resumo')}>Voltar</GhostButton>
                <Button type="button" disabled={salvando} onClick={confirmar}>
                    {salvando ? 'Registrando…' : (lote ? 'Prestar contas de todos' : 'Confirmar prestação')}
                </Button>
            </>
        );

    return (
        <CashModal
            aberto={aberto}
            icone="get_app"
            titulo={alvo ? `Prestar contas · ${alvo.nome}` : 'Prestar contas'}
            descricao={lote ? 'Dá baixa no caixa de todos os vendedores deste gerente de uma vez.' : 'Dá baixa no caixa do vendedor até a data escolhida.'}
            ao_fechar={ao_fechar}
            rodape={rodape}
        >
            {passo === 'resumo' && (
                <>
                    <Field>
                        <Label htmlFor="prestacao_ate">Até</Label>
                        <Input id="prestacao_ate" type="datetime-local" max={agora_local()} value={ate} $erro={Boolean(erros.ate)} onChange={(evento) => definir_ate(evento.target.value)} />
                        {erros.ate && <Error>{erros.ate}</Error>}
                    </Field>

                    {resumo.carregando && !dados && <Loading><Spinner tamanho={28} cor="currentColor" /></Loading>}
                    {resumo.erro && <Error>{resumo.erro.response?.data?.message ?? 'Não foi possível carregar o resumo.'}</Error>}

                    {dados && !lote && (
                        <>
                            <Hint>
                                {dados.periodo_inicio
                                    ? `Desde a última prestação, em ${formatar_data_hora(dados.periodo_inicio)}.`
                                    : 'Primeira prestação de contas deste vendedor.'}
                            </Hint>
                            <Summary>
                                <SummaryRow><dt>Saldo anterior</dt><dd>{formatar_valor(dados.saldo_anterior)}</dd></SummaryRow>
                                <SummaryRow><dt>Entradas</dt><dd>{formatar_valor(dados.entradas)}</dd></SummaryRow>
                                <SummaryRow><dt>Comissão</dt><dd>− {formatar_valor(dados.comissao)}</dd></SummaryRow>
                                <SummaryRow><dt>Saídas (prêmios e devoluções)</dt><dd>− {formatar_valor(dados.saidas)}</dd></SummaryRow>
                                <SummaryRow><dt>Suprimentos</dt><dd>{formatar_valor(dados.suprimentos)}</dd></SummaryRow>
                                <SummaryRow><dt>Retiradas</dt><dd>− {formatar_valor(dados.retiradas)}</dd></SummaryRow>
                                {Number(dados.ajustes) !== 0 && (
                                    <SummaryRow title="Cancelamentos e correções de resultado de apostas já prestadas">
                                        <dt>Ajustes de apostas anteriores</dt><dd>{formatar_valor(dados.ajustes)}</dd>
                                    </SummaryRow>
                                )}
                                <SummaryRow $destaque $cor={cor_valor(dados.valor_apurado)}>
                                    <dt>Valor apurado</dt><dd>{formatar_valor(dados.valor_apurado)}</dd>
                                </SummaryRow>
                            </Summary>
                            {Number(dados.valor_apurado) < 0 && <Hint>A banca deve ao vendedor.</Hint>}
                            {dados.apostas_em_aberto.quantidade > 0 && (
                                <Warning>
                                    <i className="material-icons" aria-hidden="true">schedule</i>
                                    {`Apostas em aberto: ${dados.apostas_em_aberto.quantidade} (${formatar_valor(dados.apostas_em_aberto.valor)}) — o resultado delas entra na próxima prestação.`}
                                </Warning>
                            )}
                            {sem_movimento && <Warning><i className="material-icons" aria-hidden="true">info</i>Nenhum movimento a prestar no período.</Warning>}
                            <Fields>
                                <Field>
                                    <Label htmlFor="prestacao_recebido">Valor recebido (R$)</Label>
                                    <Input id="prestacao_recebido" inputMode="decimal" value={recebido} $erro={Boolean(erros.valor_recebido)} onChange={(evento) => definir_recebido(evento.target.value)} />
                                    {erros.valor_recebido && <Error>{erros.valor_recebido}</Error>}
                                </Field>
                                <Field $inteiro>
                                    <Label htmlFor="prestacao_observacao">Observação (opcional)</Label>
                                    <Textarea id="prestacao_observacao" maxLength={500} value={observacao} onChange={(evento) => definir_observacao(evento.target.value)} />
                                </Field>
                            </Fields>
                            {Number(diferenca) !== 0 && (
                                <Warning $tom="#f9a825">
                                    <i className="material-icons" aria-hidden="true">warning</i>
                                    {`Fica para a próxima prestação: ${formatar_valor(diferenca)}`}
                                </Warning>
                            )}
                        </>
                    )}

                    {dados && lote && (
                        <>
                            <Sellers>
                                {dados.vendedores.map((vendedor) => (
                                    <Seller key={vendedor.id} $apagado={!vendedor.tem_movimento}>
                                        <span>{vendedor.nome}</span>
                                        <Amount $cor={cor_valor(vendedor.valor_apurado)}>
                                            {vendedor.tem_movimento ? formatar_valor(vendedor.valor_apurado) : 'sem movimento'}
                                        </Amount>
                                    </Seller>
                                ))}
                            </Sellers>
                            <Summary>
                                <SummaryRow $destaque $cor={cor_valor(dados.total)}><dt>Total</dt><dd>{formatar_valor(dados.total)}</dd></SummaryRow>
                            </Summary>
                            {dados.pulados > 0 && <Hint>{`${dados.pulados} sem movimento serão pulados.`}</Hint>}
                            {sem_movimento && <Warning><i className="material-icons" aria-hidden="true">info</i>Nenhum movimento a prestar no período.</Warning>}
                            <Field>
                                <Label htmlFor="prestacao_lote_observacao">Observação (opcional)</Label>
                                <Textarea id="prestacao_lote_observacao" maxLength={500} value={observacao} onChange={(evento) => definir_observacao(evento.target.value)} />
                            </Field>
                        </>
                    )}
                </>
            )}

            {passo === 'senha' && (
                <>
                    <Summary>
                        <SummaryRow $destaque>
                            <dt>{lote ? 'Total do lote' : 'Valor recebido'}</dt>
                            <dd>{formatar_valor(lote ? dados.total : valor_recebido)}</dd>
                        </SummaryRow>
                    </Summary>
                    <Field>
                        <Label htmlFor="prestacao_senha">Confirme com a sua senha</Label>
                        <Input
                            id="prestacao_senha"
                            type="password"
                            autoComplete="current-password"
                            autoFocus
                            value={senha}
                            $erro={Boolean(erros.senha)}
                            onChange={(evento) => definir_senha(evento.target.value)}
                            onKeyDown={(evento) => evento.key === 'Enter' && confirmar()}
                        />
                        {erros.senha && <Error>{erros.senha}</Error>}
                    </Field>
                </>
            )}
        </CashModal>
    );
}
