import { forwardRef, useCallback, useImperativeHandle, useState } from 'react';

import CashOutConfirm from '../CashOutConfirm';
import useEncerramento from '../../hooks/useEncerramento';
import { formatar_data_hora, formatar_hora } from '../../utils/dates';
import { cores_estado } from '../../utils/live_states';
import { centavos_para_texto, formatar_real, para_centavos } from '../../utils/money';
import {
    Card, Done, Field, Header, Message, Offer, OfferLabel, Primary, Reason, Secondary, Shortcuts, Slider, Tab, Tabs, Trend,
} from './styles';

const real_centavos = (centavos) => `R$ ${formatar_real(centavos)}`;
const real = (valor) => real_centavos(para_centavos(valor));

// prévia do parcial (FR-028): o valor apostado e o prêmio caem na proporção recebido ÷ oferta
const restante_parcial = (bilhete, recebido_centavos, oferta_centavos) => {
    const fator = (oferta_centavos - recebido_centavos) / oferta_centavos;

    return {
        valor_apostado: centavos_para_texto(Math.floor(para_centavos(bilhete.valor_em_jogo) * fator)),
        premio: centavos_para_texto(Math.floor(para_centavos(bilhete.total_a_pagar) * fator)),
    };
};

// atalhos do valor-alvo do automático (FR-030)
const atalhos_alvo = (oferta_centavos, bilhete) => [
    { rotulo: '+20%', centavos: Math.ceil(oferta_centavos * 1.2) },
    { rotulo: '+50%', centavos: Math.ceil(oferta_centavos * 1.5) },
    { rotulo: '+100%', centavos: oferta_centavos * 2 },
    { rotulo: 'Recuperar o valor apostado', centavos: para_centavos(bilhete.valor_em_jogo) },
].filter(({ centavos }) => centavos > oferta_centavos && centavos <= para_centavos(bilhete.total_a_pagar));

// texto do último pedido: em análise, encerrado ou recusado
const texto_pedido = (pedido) => {
    if (pedido.situacao === 'Em análise') return `Confirmando o encerramento${pedido.segundos_restantes ? ` (${pedido.segundos_restantes} s)` : ''}…`;
    if (pedido.situacao === 'Recusado') return pedido.motivo_recusa ?? 'O encerramento foi recusado.';

    const credito = pedido.creditar_em ? ` Entra no seu saldo às ${formatar_hora(pedido.creditar_em)}.` : '';

    return pedido.modalidade === 'Parcial'
        ? `Encerramento parcial de ${real(pedido.valor)} registrado.${credito}`
        : `Aposta encerrada por ${real(pedido.valor)}.${credito}`;
};

// bloco de encerramento do dono no bilhete (FR-023 a FR-031): oferta, abas Total, Parcial e
// Automático, confirmação com validade e acompanhamento do pedido
const CashOutPanel = forwardRef(({ bilhete, recarregar }, ref) => {
    const encerramento = bilhete.encerramento;
    const { enviando, pedido, limpar_pedido, encerrar, programar_automatico, cancelar_automatico } = useEncerramento(bilhete.codigo, recarregar);

    const [aba, definir_aba] = useState('total');
    // oferta congelada ao abrir a confirmação: { modalidade, valor, token, expira_em, ao_vivo, restante, chave }
    const [confirmacao, definir_confirmacao] = useState(null);
    const [aceitar, definir_aceitar] = useState('Somente para maior');
    const [parcial_centavos, definir_parcial_centavos] = useState(null);
    const [alvo_texto, definir_alvo_texto] = useState('');
    const [mensagem, definir_mensagem] = useState(null);

    const oferta_centavos = para_centavos(encerramento.oferta);
    const minimo_centavos = para_centavos(encerramento.minimo);
    const maximo_parcial = oferta_centavos - 1;
    const recebido_parcial = Math.min(Math.max(parcial_centavos ?? Math.floor(oferta_centavos / 2), minimo_centavos), maximo_parcial);

    // abre a confirmação com a oferta mais recente (o token vale poucos segundos)
    const abrir_confirmacao = useCallback(async (modalidade) => {
        definir_mensagem(null);
        limpar_pedido();

        const atual = (await recarregar())?.encerramento;

        if (!atual?.disponivel) {
            definir_mensagem(atual?.motivo ?? 'A oferta não está disponível no momento.');
            return;
        }

        const oferta_atual = para_centavos(atual.oferta);
        const recebido = Math.min(recebido_parcial, oferta_atual - 1);
        const parcial = modalidade === 'Parcial';

        if (parcial && recebido < para_centavos(atual.minimo)) {
            definir_mensagem('A oferta mudou e não permite mais esse encerramento parcial.');
            return;
        }

        definir_confirmacao({
            modalidade,
            valor: parcial ? centavos_para_texto(recebido) : atual.oferta,
            token: atual.token_oferta,
            expira_em: atual.expira_em,
            ao_vivo: atual.ao_vivo,
            minutos_credito: atual.minutos_credito,
            restante: parcial ? restante_parcial(bilhete, recebido, oferta_atual) : null,
            chave: crypto.randomUUID(),
        });
    }, [recarregar, limpar_pedido, recebido_parcial, bilhete]);

    useImperativeHandle(ref, () => ({ abrir_total: () => abrir_confirmacao('Total') }), [abrir_confirmacao]);

    const confirmar = async () => {
        const resposta = await encerrar({
            modalidade: confirmacao.modalidade,
            valor: confirmacao.valor,
            token_oferta: confirmacao.token,
            chave_idempotencia: confirmacao.chave,
            aceitar_alteracoes: aceitar,
        });

        definir_confirmacao(null);

        if (resposta.oferta_alterada) definir_mensagem(`${resposta.mensagem} Confira a nova oferta.`);
    };

    const expirar = useCallback(() => {
        definir_confirmacao(null);
        definir_mensagem('A validade da oferta terminou. Confira a oferta atual.');
        recarregar();
    }, [recarregar]);

    const alvo_centavos = para_centavos(alvo_texto);
    const alvo_valido = alvo_centavos > oferta_centavos && alvo_centavos <= para_centavos(bilhete.total_a_pagar);

    const programar = async () => {
        if (await programar_automatico(centavos_para_texto(alvo_centavos))) {
            definir_alvo_texto('');
            definir_mensagem(`Encerramento automático programado em ${real_centavos(alvo_centavos)}.`);
        }
    };

    const lista_registrados = encerramento.registrados.length > 0 && (
        <Message>
            {encerramento.registrados.map((registrado, indice) => (
                <span key={indice}>
                    {registrado.modalidade} de {real(registrado.valor)} · {registrado.situacao === 'Creditado' ? 'creditado' : `crédito ${formatar_data_hora(registrado.creditar_em)}`}
                </span>
            ))}
        </Message>
    );

    // aposta no estado final (encerrada ou decidida): só o que já foi encerrado
    if (encerramento.final) {
        return (
            <Card id="encerramento">
                <OfferLabel>Encerramento da aposta</OfferLabel>
                {pedido && pedido.situacao !== 'Em análise' && <Done $recusado={pedido.situacao === 'Recusado'} role="status">{texto_pedido(pedido)}</Done>}
                {lista_registrados}
            </Card>
        );
    }

    if (confirmacao) {
        return (
            <Card id="encerramento">
                <CashOutConfirm
                    modalidade={confirmacao.modalidade}
                    valor={confirmacao.valor}
                    restante={confirmacao.restante}
                    total_a_pagar={bilhete.total_a_pagar}
                    expira_em={confirmacao.expira_em}
                    minutos_credito={confirmacao.minutos_credito}
                    ao_vivo={confirmacao.ao_vivo}
                    aceitar={aceitar}
                    ao_mudar_aceitar={definir_aceitar}
                    enviando={enviando}
                    ao_confirmar={confirmar}
                    ao_cancelar={() => definir_confirmacao(null)}
                    ao_expirar={expirar}
                />
            </Card>
        );
    }

    const em_analise = pedido?.situacao === 'Em análise' || Boolean(encerramento.em_analise);

    return (
        <Card id="encerramento" aria-labelledby="titulo-encerramento">
            <Header>
                <OfferLabel id="titulo-encerramento">Encerrar aposta</OfferLabel>
                {encerramento.disponivel ? (
                    <Offer aria-live="polite">
                        {real(encerramento.oferta)}
                        {encerramento.variacao && (
                            <Trend $cor={encerramento.variacao === 'subiu' ? cores_estado.ganhando : cores_estado.perdendo}>
                                <span className="material-icons" aria-hidden="true">{encerramento.variacao === 'subiu' ? 'arrow_upward' : 'arrow_downward'}</span>
                                {encerramento.variacao}
                            </Trend>
                        )}
                    </Offer>
                ) : (
                    <Reason role="status">{encerramento.motivo ?? 'Oferta indisponível no momento.'}</Reason>
                )}
            </Header>

            {pedido && <Done $recusado={pedido.situacao === 'Recusado'} role="status">{texto_pedido(pedido)}</Done>}
            {!pedido && em_analise && <Done role="status">Confirmando o encerramento…</Done>}
            {mensagem && <Message role="status">{mensagem}</Message>}

            {encerramento.disponivel && !em_analise && (
                <>
                    <Tabs role="tablist">
                        <Tab type="button" role="tab" aria-selected={aba === 'total'} $ativa={aba === 'total'} onClick={() => definir_aba('total')}>Total</Tab>
                        <Tab type="button" role="tab" aria-selected={aba === 'parcial'} $ativa={aba === 'parcial'} onClick={() => definir_aba('parcial')}>Parcial</Tab>
                        {encerramento.automatico.disponivel && (
                            <Tab type="button" role="tab" aria-selected={aba === 'automatico'} $ativa={aba === 'automatico'} onClick={() => definir_aba('automatico')}>Automático</Tab>
                        )}
                    </Tabs>

                    {aba === 'total' && (
                        <Primary type="button" onClick={() => abrir_confirmacao('Total')} disabled={enviando}>
                            Encerrar por {real(encerramento.oferta)}
                        </Primary>
                    )}

                    {aba === 'parcial' && (encerramento.parcial.disponivel && maximo_parcial >= minimo_centavos ? (
                        <>
                            <Field>
                                <span>Receber agora</span>
                                <strong>{real_centavos(recebido_parcial)}</strong>
                            </Field>
                            <Slider
                                type="range"
                                min={minimo_centavos}
                                max={maximo_parcial}
                                step={1}
                                value={recebido_parcial}
                                onChange={(evento) => definir_parcial_centavos(Number(evento.target.value))}
                                aria-label="Valor a receber agora"
                            />
                            {(() => {
                                const restante = restante_parcial(bilhete, recebido_parcial, oferta_centavos);

                                return (
                                    <Field $apagado>
                                        <span>Continua valendo {real(restante.valor_apostado)}</span>
                                        <span>prêmio {real(restante.premio)}</span>
                                    </Field>
                                );
                            })()}
                            <Primary type="button" onClick={() => abrir_confirmacao('Parcial')} disabled={enviando}>
                                Encerrar parte por {real_centavos(recebido_parcial)}
                            </Primary>
                            <Message>Restam {encerramento.parcial.restantes} encerramento(s) parcial(is) nesta aposta.</Message>
                        </>
                    ) : (
                        <Reason>{encerramento.parcial.motivo ?? 'A oferta não permite encerramento parcial.'}</Reason>
                    ))}

                    {aba === 'automatico' && (
                        <>
                            {encerramento.automatico.ativo ? (
                                <Field>
                                    <span>Encerra sozinha em {real(encerramento.automatico.ativo.valor_alvo)}</span>
                                    <Secondary type="button" onClick={cancelar_automatico} disabled={enviando}>Desativar</Secondary>
                                </Field>
                            ) : (
                                <Message>Escolha um valor maior que a oferta atual: quando a oferta chegar nele, a aposta é encerrada inteira.</Message>
                            )}
                            <Shortcuts>
                                {atalhos_alvo(oferta_centavos, bilhete).map(({ rotulo, centavos }) => (
                                    <button key={rotulo} type="button" onClick={() => definir_alvo_texto(centavos_para_texto(centavos))}>{rotulo}</button>
                                ))}
                            </Shortcuts>
                            <Field as="label">
                                <span>Valor-alvo (R$)</span>
                                <input
                                    inputMode="decimal"
                                    value={alvo_texto.replace('.', ',')}
                                    onChange={(evento) => definir_alvo_texto(evento.target.value.replace(',', '.'))}
                                    placeholder={formatar_real(oferta_centavos + 100)}
                                />
                            </Field>
                            {alvo_texto && !alvo_valido && (
                                <Reason>O valor-alvo deve ser maior que {real(encerramento.oferta)} e no máximo {real(bilhete.total_a_pagar)}.</Reason>
                            )}
                            <Primary type="button" onClick={programar} disabled={enviando || !alvo_valido}>
                                {encerramento.automatico.ativo ? 'Alterar valor-alvo' : 'Ativar encerramento automático'}
                            </Primary>
                        </>
                    )}
                </>
            )}

            {lista_registrados}
        </Card>
    );
});

export default CashOutPanel;
