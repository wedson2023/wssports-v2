import { Fragment } from 'react';
import { router } from '@inertiajs/react';

import Backdrop from '../Backdrop';
import ModalHeader from '../ModalHeader';
import useTelaMobile from '../../hooks/useTelaMobile';
import useVoltarFecha from '../../hooks/useVoltarFecha';
import { agrupar_palpites_comprovante } from '../../utils/bet_builder';
import { formatar_data_hora } from '../../utils/dates';
import { formatar_real, para_centavos } from '../../utils/money';
import { compartilhar_texto } from '../../utils/share';
import { useTheme } from 'styled-components';
import {
    Banner, CancelButton, Container, Data, Footer, Header, Hunch, HunchAction, HunchActions, Hunches, Icon, Icons, Label, PanelTools, Row, Status, Teams, Title,
} from './styles';

const real = (valor) => `R$ ${formatar_real(para_centavos(valor))}`;

// situação exibida: o resultado quando já apurado, senão a situação da aposta
const situacao_exibida = (comprovante) => (comprovante.resultado && comprovante.resultado !== 'Aguardando' ? comprovante.resultado : comprovante.situacao);

// situação do palpite: cancelado, o resultado quando já apurado, o período do jogo em andamento ou
// a situação (como no sistema antigo)
const situacao_palpite = (palpite) => {
    if (palpite.situacao === 'Cancelado') {
        return palpite.situacao;
    }

    if (palpite.resultado && palpite.resultado !== 'Aguardando') {
        return palpite.resultado;
    }

    return palpite.periodo ?? palpite.situacao;
};

// motivo do palpite cancelado: o automático (jogo cancelado, empate anula) ou cancelado por um usuário
const motivo_cancelamento = (palpite) => palpite.motivo_cancelamento ?? 'Cancelado no bilhete';

const placar = (casa, fora) => `${casa ?? 0} x ${fora ?? 0}`;

// placar, gols por tempo, escanteios e período: só com o jogo em andamento ou com resultado
const linhas_andamento = (palpite) => (
    <>
        {palpite.placar_casa !== null && (
            <Row><Label>Placar:</Label><span>{placar(palpite.placar_casa, palpite.placar_fora)}</span></Row>
        )}
        {palpite.primeiro_tempo && (
            <Row><Label>1º Tempo:</Label><span>{placar(palpite.primeiro_tempo.casa, palpite.primeiro_tempo.fora)}</span></Row>
        )}
        {palpite.segundo_tempo && (
            <Row><Label>2º Tempo:</Label><span>{placar(palpite.segundo_tempo.casa, palpite.segundo_tempo.fora)}</span></Row>
        )}
        {palpite.esporte === 'FUTEBOL' && (
            <Row><Label>Escanteios:</Label><span>{palpite.escanteios ?? '-'}</span></Row>
        )}
        <Row>
            <Label>Período:</Label>
            <span>{palpite.minuto !== null ? `${palpite.periodo} - ${palpite.minuto} minuto(s)` : palpite.periodo}</span>
        </Row>
    </>
);

const texto_palpite = (palpite) => (palpite.jogador ? `${palpite.jogador} (${palpite.jogador_tipo})` : palpite.mercado);

// cancelar ou restaurar o palpite (painel, spec 018), só para quem pode editar o bilhete: botão de
// texto no rodapé do palpite; compacto (só o ícone) ao lado da cotação, dentro do bloco Criar Aposta
const acao_palpite = (palpite, edicao, compacto = false) => {
    if (!edicao) return null;

    const cancelado = palpite.situacao === 'Cancelado';
    const texto = cancelado ? 'Restaurar palpite' : 'Cancelar palpite';

    return (
        <HunchAction
            type="button"
            $restaurar={cancelado}
            $compacto={compacto}
            title={texto}
            aria-label={`${texto} ${texto_palpite(palpite)}`}
            disabled={edicao.ocupado}
            onClick={() => (cancelado ? edicao.ao_restaurar(palpite) : edicao.ao_cancelar(palpite))}
        >
            <i className="material-icons" aria-hidden="true">{cancelado ? 'restore' : 'delete_outline'}</i>
            {!compacto && texto}
        </HunchAction>
    );
};

const linha_palpite = (palpite, edicao) => (
    <Hunch $cancelado={palpite.situacao === 'Cancelado'}>
        <Teams>{palpite.time_casa} x {palpite.time_fora}</Teams>
        <Row><Label>Horário:</Label><span>{formatar_data_hora(palpite.data_inicio)}</span></Row>
        <Row><Label>Campeonato:</Label><span>{palpite.campeonato}</span></Row>
        <Row><Label>Palpite:</Label><span>{texto_palpite(palpite)}</span></Row>
        <Row><Label>Cotação:</Label><span>{Number(palpite.cotacao).toFixed(2)}</span></Row>
        {/* palpite especial numa vencedora empatada (spec 024): a cotação dividida vale no prêmio */}
        {palpite.cotacao_empate && (
            <Row>
                <Label>Dividida por empate:</Label>
                <span>{`${Number(palpite.cotacao_empate).toFixed(2)} (original ${Number(palpite.cotacao).toFixed(2)})`}</span>
            </Row>
        )}
        <Row><Label>Tipo:</Label><span>{palpite.esporte}</span></Row>
        {palpite.periodo && linhas_andamento(palpite)}
        <Row>
            <Label>Situação:</Label>
            <Status $situacao={situacao_palpite(palpite)}>{situacao_palpite(palpite)}</Status>
        </Row>
        {palpite.situacao === 'Cancelado' && <Row><Label>Motivo:</Label><span>{motivo_cancelamento(palpite)}</span></Row>}
        {edicao && <HunchActions>{acao_palpite(palpite, edicao)}</HunchActions>}
    </Hunch>
);

// bloco Criar Aposta (spec 008): jogo, horário, campeonato e tipo uma vez só; "Palpite: Criar
// Aposta"; uma linha por palpite
// (mercado e cotação), a cotação do bloco (rótulo "Cotação") e a situação do bloco
const linha_bloco = (bloco, palpites, edicao) => {
    const [primeiro] = palpites;

    return (
        <Hunch $cancelado={bloco.situacao === 'Anulado'}>
            <Teams>{primeiro.time_casa} x {primeiro.time_fora}</Teams>
            <Row><Label>Horário:</Label><span>{formatar_data_hora(primeiro.data_inicio)}</span></Row>
            <Row><Label>Campeonato:</Label><span>{primeiro.campeonato}</span></Row>
            <Row><Label>Palpite:</Label><span>Criar Aposta</span></Row>
            {palpites.map((palpite) => (
                <Row key={palpite.id}>
                    <Label>
                        {`${texto_palpite(palpite)}${palpite.situacao === 'Cancelado' ? ` (cancelado: ${motivo_cancelamento(palpite)})` : ''}:`}
                    </Label>
                    <span>
                        {Number(palpite.cotacao).toFixed(2)}
                        {acao_palpite(palpite, edicao, true)}
                    </span>
                </Row>
            ))}
            <Row><Label>Cotação:</Label><span>{Number(bloco.cotacao).toFixed(2)}</span></Row>
            <Row><Label>Tipo:</Label><span>{primeiro.esporte}</span></Row>
            <Row>
                <Label>Situação:</Label>
                <Status $situacao={bloco.situacao}>{bloco.situacao}</Status>
            </Row>
        </Hunch>
    );
};

// comprovante do bilhete consultado pelo código (FR-044, data-model.md seção 4). No painel (spec 018),
// `painel` traz a data e o autor do cancelamento e as ações permitidas ({ cancelada_em,
// cancelada_por, acoes }); com ele, o cabeçalho é o ModalHeader do painel, o rodapé mostra comissão,
// forma de pagamento e "Vendedor paga", e aparecem imprimir 2ª via, cancelar ou restaurar a aposta e
// cancelar ou restaurar palpites. Sem `painel`, o bilhete do site e da Dashboard fica como era
export default function TicketModal({
    comprovante, ao_fechar, painel = null, ocupado = false, ao_imprimir = null, ao_cancelar_aposta = null, ao_restaurar_aposta = null, ao_cancelar_palpite = null,
    ao_restaurar_palpite = null,
}) {
    const e_mobile = useTelaMobile();
    const tema = useTheme();
    const visivel = Boolean(comprovante);
    useVoltarFecha(visivel, ao_fechar);

    const acoes = painel?.acoes ?? [];
    const edicao = acoes.includes('editar_palpites') && ao_cancelar_palpite
        ? { ocupado, ao_cancelar: ao_cancelar_palpite, ao_restaurar: ao_restaurar_palpite }
        : null;

    // o link leva ao bilhete em tempo real, como no sistema antigo (spec 010, FR-015)
    const link_bilhete = () => `${window.location.origin}/bilhete/${comprovante.codigo}`;
    const compartilhar = () => compartilhar_texto(`*ACOMPANHE SEU BILHETE:* \n\n ${link_bilhete()}`, e_mobile);
    const acompanhar = () => router.visit(`/bilhete/${comprovante.codigo}`);
    const pode_acompanhar = comprovante && comprovante.situacao !== 'Pendente';
    const vendedor_paga = comprovante?.premio_liquido && para_centavos(comprovante.premio_liquido) !== para_centavos(comprovante.total_a_pagar);

    return (
        <>
            <Container $visivel={visivel} $painel={Boolean(painel)}>
                {painel ? (
                    <>
                        <ModalHeader
                            icone="receipt"
                            titulo={comprovante ? `Bilhete ${comprovante.codigo.toUpperCase()}` : 'Bilhete'}
                            descricao="Confira os palpites, o andamento dos jogos e os valores da aposta."
                            ao_fechar={ao_fechar}
                        />
                        <PanelTools>
                            <Icon $cor="green" title="Clique para compartilhar bilhete" onClick={compartilhar}>share</Icon>
                            {pode_acompanhar && <Icon $cor="green" title="Clique para acompanhar esse bilhete em tempo real" onClick={acompanhar}>desktop_mac</Icon>}
                            {acoes.includes('imprimir_segunda_via') && ao_imprimir && (
                                <Icon $cor={tema.principal} title="Clique para imprimir a 2ª via do bilhete" onClick={ao_imprimir}>print</Icon>
                            )}
                        </PanelTools>
                    </>
                ) : (
                    <Header>
                        <Title>Conferir Bilhete</Title>
                        <Icons>
                            <Icon $cor="green" title="Clique para compartilhar bilhete" onClick={compartilhar}>share</Icon>
                            {pode_acompanhar && (
                                <Icon $cor="green" title="Clique para acompanhar esse bilhete em tempo real" onClick={acompanhar}>desktop_mac</Icon>
                            )}
                            <Icon $cor={tema.principal} title="Clique para fechar o bilhete" onClick={ao_fechar}>close</Icon>
                        </Icons>
                    </Header>
                )}
                {comprovante && (
                    <Data>
                        {painel?.cancelada_em && (
                            <Banner>
                                {`Cancelada em ${formatar_data_hora(painel.cancelada_em)}${painel.cancelada_por ? ` por ${painel.cancelada_por}` : ''}`}
                            </Banner>
                        )}
                        <Row><Label>Código:</Label><span>{comprovante.codigo.toUpperCase()}</span></Row>
                        <Row><Label>Cliente:</Label><span>{comprovante.nome}</span></Row>
                        <Row><Label>Vendedor:</Label><span>{comprovante.vendedor ?? '-'}</span></Row>
                        <Row><Label>Horário:</Label><span>{formatar_data_hora(comprovante.criada_em)}</span></Row>
                        <Row>
                            <Label>Situação:</Label>
                            <Status $situacao={situacao_exibida(comprovante)}>{situacao_exibida(comprovante)}</Status>
                        </Row>
                        <Hunches>
                            {agrupar_palpites_comprovante(comprovante).map(({ bloco, palpites }) => (
                                <Fragment key={palpites[0].id}>
                                    {bloco ? linha_bloco(bloco, palpites, edicao) : linha_palpite(palpites[0], edicao)}
                                </Fragment>
                            ))}
                        </Hunches>
                        <Row><Label>Qtde. Jogos:</Label><span>{comprovante.quantidade_jogos ?? comprovante.palpites.length}</span></Row>
                        <Row><Label>Cotação Total:</Label><span>{Number(comprovante.cotacao_total).toFixed(2)}</span></Row>
                        {painel && comprovante.comissao !== undefined && <Row><Label>Comissão:</Label><span>{real(comprovante.comissao)}</span></Row>}
                        <Row><Label>Valor:</Label><span>{real(comprovante.valor)}</span></Row>
                        <Row><Label>Prêmio:</Label><span>{real(comprovante.premio)}</span></Row>
                        {para_centavos(comprovante.valor_acrescido) > 0 && (
                            <Row><Label>Valor acrescido:</Label><span>{real(comprovante.valor_acrescido)}</span></Row>
                        )}
                        {painel && vendedor_paga && <Row><Label>Vendedor Paga:</Label><span>{real(comprovante.premio_liquido)}</span></Row>}
                        {painel && comprovante.forma_pagamento && <Row><Label>Forma de pagamento:</Label><span>{comprovante.forma_pagamento}</span></Row>}
                    </Data>
                )}
                {comprovante && acoes.includes('cancelar') && ao_cancelar_aposta && (
                    <Footer>
                        <CancelButton type="button" disabled={ocupado} onClick={ao_cancelar_aposta}>
                            <i className="material-icons" aria-hidden="true">delete</i>
                            Cancelar aposta
                        </CancelButton>
                    </Footer>
                )}
                {comprovante && acoes.includes('restaurar') && ao_restaurar_aposta && (
                    <Footer>
                        <CancelButton type="button" $restaurar disabled={ocupado} onClick={ao_restaurar_aposta}>
                            <i className="material-icons" aria-hidden="true">restore</i>
                            Restaurar aposta
                        </CancelButton>
                    </Footer>
                )}
            </Container>
            <Backdrop visivel={visivel} ao_fechar={ao_fechar} />
        </>
    );
}
