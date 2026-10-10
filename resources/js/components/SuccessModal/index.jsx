import { useEffect, useRef, useState } from 'react';

import Backdrop from '../Backdrop';
import useTelaMobile from '../../hooks/useTelaMobile';
import useVoltarFecha from '../../hooks/useVoltarFecha';
import { formatar_dia_mes, formatar_hora } from '../../utils/dates';
import { formatar_real, para_centavos } from '../../utils/money';
import { imprimir_bilhete } from '../../utils/print';
import { compartilhar_texto } from '../../utils/share';
import {
    ActionButton, Actions, CloseButton, Code, CodeBox, Container, CopyButton, SuccessIcon,
    Subtitle, Summary, SummaryItem, TextButton, TicketNumber, Title,
} from './styles';

// tempo que o botão mostra "Copiado"
const tempo_copiado_ms = 2000;

// confirmação da aposta: código do visitante, para apresentar a um vendedor (FR-041); aposta do
// cliente (saldo) ou do vendedor (bilhete cadastrado, com Imprimir e Enviar como no sistema antigo).
// comprovante.apostador diz quem apostou. ao_imprimir vem da tela (impressão do vendedor: navegador,
// Bluetooth ou aplicativo, spec 006); sem ela, imprime pelo navegador
export default function SuccessModal({ comprovante, ao_fechar, ao_imprimir = imprimir_bilhete }) {
    const e_mobile = useTelaMobile();
    const [copiado, definir_copiado] = useState(false);
    const visivel = Boolean(comprovante);
    useVoltarFecha(visivel, ao_fechar);
    // ao fechar, mantém o último comprovante na tela enquanto o modal esmaece
    const ultimo = useRef(comprovante);
    if (comprovante) ultimo.current = comprovante;
    const dados = comprovante ?? ultimo.current;
    const codigo = dados?.codigo?.toUpperCase() ?? '';
    const link = `Por favor valide meu bilhete, obrigado! ${window.location.origin}?code=${codigo}`;
    // aposta do cliente e do vendedor já sai Ativa; a do visitante sai Pendente (código)
    const confirmada = dados?.situacao === 'Ativa';
    const do_vendedor = confirmada && dados?.apostador === 'vendedor';
    // texto do "Enviar" do vendedor, como no sistema antigo
    const texto_envio = [`*ACOMPANHE SEU BILHETE:*`, `CÓDIGO: ${codigo}`, `${window.location.origin}?code=${codigo}`, dados?.mensagem_bilhete]
        .filter(Boolean)
        .join('\n\n');
    // comissão sobre o prêmio: o vendedor paga menos que o prêmio
    const vendedor_paga = do_vendedor && dados.premio_liquido && para_centavos(dados.premio_liquido) !== para_centavos(dados.total_a_pagar);

    useEffect(() => definir_copiado(false), [codigo]);

    // Esc fecha o modal
    useEffect(() => {
        if (!visivel) return undefined;

        const ao_teclar = (evento) => evento.key === 'Escape' && ao_fechar();
        window.addEventListener('keydown', ao_teclar);

        return () => window.removeEventListener('keydown', ao_teclar);
    }, [visivel, ao_fechar]);

    const copiar = async () => {
        try {
            await navigator.clipboard.writeText(codigo);
            definir_copiado(true);
            setTimeout(() => definir_copiado(false), tempo_copiado_ms);
        } catch {
            // sem acesso à área de transferência: o código continua selecionável na tela
        }
    };

    return (
        <>
            <Container $visivel={visivel} role="dialog" aria-modal="true" aria-labelledby="titulo_sucesso">
                <CloseButton type="button" onClick={ao_fechar} aria-label="Fechar">
                    <i className="material-icons">close</i>
                </CloseButton>

                <SuccessIcon aria-hidden="true">check_circle</SuccessIcon>
                <Title id="titulo_sucesso">
                    {do_vendedor && (dados.validado ? 'Bilhete validado com sucesso!' : 'Bilhete cadastrado com sucesso!')}
                    {!do_vendedor && (confirmada ? 'Aposta confirmada!' : 'Aposta registrada!')}
                </Title>
                <Subtitle>
                    {do_vendedor && 'O bilhete já está valendo. Imprima o comprovante ou envie para o cliente.'}
                    {!do_vendedor && (confirmada
                        ? 'Sua aposta já está valendo e o valor saiu do seu saldo. Boa sorte!'
                        : 'Apresente este código a um dos nossos vendedores para validar sua aposta.')}
                </Subtitle>

                {/* visitante leva o código ao vendedor (destaque e Copiar); o cliente só vê o número
                    do bilhete, para conferir a aposta depois */}
                {confirmada ? (
                    <TicketNumber>
                        Bilhete nº <strong>{codigo}</strong>
                    </TicketNumber>
                ) : (
                    <CodeBox>
                        <Code>{codigo}</Code>
                        <CopyButton type="button" onClick={copiar} $copiado={copiado} aria-live="polite">
                            <i className="material-icons">{copiado ? 'check' : 'content_copy'}</i>
                            {copiado ? 'Copiado' : 'Copiar'}
                        </CopyButton>
                    </CodeBox>
                )}

                {dados && (
                    <Summary>
                        <SummaryItem>
                            <dt>Valor</dt>
                            <dd>R$ {formatar_real(para_centavos(dados.valor))}</dd>
                        </SummaryItem>
                        <SummaryItem>
                            <dt>Prêmio</dt>
                            <dd>R$ {formatar_real(para_centavos(dados.total_a_pagar))}</dd>
                        </SummaryItem>
                        <SummaryItem>
                            {vendedor_paga && (
                                <>
                                    <dt>Vendedor paga</dt>
                                    <dd>R$ {formatar_real(para_centavos(dados.premio_liquido))}</dd>
                                </>
                            )}
                            {!vendedor_paga && confirmada && (
                                <>
                                    <dt>Situação</dt>
                                    <dd>Ativa</dd>
                                </>
                            )}
                            {!confirmada && (
                                <>
                                    <dt>Válido até</dt>
                                    <dd>{dados.expira_em ? `${formatar_dia_mes(dados.expira_em)} ${formatar_hora(dados.expira_em)}` : '-'}</dd>
                                </>
                            )}
                        </SummaryItem>
                    </Summary>
                )}

                <Actions>
                    {!confirmada && (
                        <>
                            <ActionButton type="button" $tipo="codigo" onClick={() => compartilhar_texto(codigo, e_mobile)}>
                                <i className="material-icons">send</i>
                                {e_mobile ? 'Compartilhar código' : 'Enviar código pelo WhatsApp'}
                            </ActionButton>
                            <ActionButton type="button" $tipo="link" onClick={() => compartilhar_texto(link, e_mobile)}>
                                <i className="material-icons">link</i>
                                {e_mobile ? 'Compartilhar link' : 'Enviar link pelo WhatsApp'}
                            </ActionButton>
                        </>
                    )}
                    {do_vendedor && (
                        <>
                            <ActionButton type="button" $tipo="link" onClick={() => ao_imprimir(dados)}>
                                <i className="material-icons">print</i>
                                Imprimir
                            </ActionButton>
                            <ActionButton type="button" $tipo="codigo" onClick={() => compartilhar_texto(texto_envio, e_mobile)}>
                                <i className="material-icons">send</i>
                                {e_mobile ? 'Enviar' : 'Enviar pelo WhatsApp'}
                            </ActionButton>
                        </>
                    )}
                    {confirmada && !do_vendedor && (
                        <ActionButton type="button" $tipo="link" onClick={ao_fechar}>
                            <i className="material-icons">sports_soccer</i>
                            Continuar apostando
                        </ActionButton>
                    )}
                    {(!confirmada || do_vendedor) && <TextButton type="button" onClick={ao_fechar}>Fazer outra aposta</TextButton>}
                </Actions>
            </Container>
            <Backdrop visivel={visivel} ao_fechar={ao_fechar} />
        </>
    );
}
