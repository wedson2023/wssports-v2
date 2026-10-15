import { Fragment } from 'react';
import { router } from '@inertiajs/react';

import Backdrop from '../Backdrop';
import useTelaMobile from '../../hooks/useTelaMobile';
import useVoltarFecha from '../../hooks/useVoltarFecha';
import { agrupar_palpites_comprovante } from '../../utils/bet_builder';
import { formatar_data_hora } from '../../utils/dates';
import { formatar_real, para_centavos } from '../../utils/money';
import { compartilhar_texto } from '../../utils/share';
import { useTheme } from 'styled-components';
import { Container, Data, Header, Hunch, Hunches, Icon, Icons, Label, Row, Status, Teams, Title } from './styles';

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

const linha_palpite = (palpite) => (
    <Hunch $cancelado={palpite.situacao === 'Cancelado'}>
        <Teams>{palpite.time_casa} x {palpite.time_fora}</Teams>
        <Row><Label>Horário:</Label><span>{formatar_data_hora(palpite.data_inicio)}</span></Row>
        <Row><Label>Campeonato:</Label><span>{palpite.campeonato}</span></Row>
        <Row><Label>Palpite:</Label><span>{texto_palpite(palpite)}</span></Row>
        <Row><Label>Cotação:</Label><span>{Number(palpite.cotacao).toFixed(2)}</span></Row>
        <Row><Label>Tipo:</Label><span>{palpite.esporte}</span></Row>
        {palpite.periodo && linhas_andamento(palpite)}
        <Row>
            <Label>Situação:</Label>
            <Status $situacao={situacao_palpite(palpite)}>{situacao_palpite(palpite)}</Status>
        </Row>
    </Hunch>
);

const texto_palpite = (palpite) => (palpite.jogador ? `${palpite.jogador} (${palpite.jogador_tipo})` : palpite.mercado);

// bloco Criar Aposta (spec 008): jogo, horário, campeonato e tipo uma vez só; "Palpite: Criar
// Aposta"; uma linha por palpite
// (mercado e cotação), a cotação do bloco (rótulo "Cotação") e a situação do bloco
const linha_bloco = (bloco, palpites) => {
    const [primeiro] = palpites;

    return (
        <Hunch $cancelado={bloco.situacao === 'Anulado'}>
            <Teams>{primeiro.time_casa} x {primeiro.time_fora}</Teams>
            <Row><Label>Horário:</Label><span>{formatar_data_hora(primeiro.data_inicio)}</span></Row>
            <Row><Label>Campeonato:</Label><span>{primeiro.campeonato}</span></Row>
            <Row><Label>Palpite:</Label><span>Criar Aposta</span></Row>
            {palpites.map((palpite) => (
                <Row key={palpite.id}>
                    <Label>{`${texto_palpite(palpite)}${palpite.situacao === 'Cancelado' ? ' (cancelado)' : ''}:`}</Label>
                    <span>{Number(palpite.cotacao).toFixed(2)}</span>
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

// comprovante do bilhete consultado pelo código (FR-044, data-model.md seção 4)
export default function TicketModal({ comprovante, ao_fechar }) {
    const e_mobile = useTelaMobile();
    const tema = useTheme();
    const visivel = Boolean(comprovante);
    useVoltarFecha(visivel, ao_fechar);

    // o link leva ao bilhete em tempo real, como no sistema antigo (spec 010, FR-015)
    const link_bilhete = () => `${window.location.origin}/bilhete/${comprovante.codigo}`;
    const compartilhar = () => compartilhar_texto(`*ACOMPANHE SEU BILHETE:* \n\n ${link_bilhete()}`, e_mobile);
    const acompanhar = () => router.visit(`/bilhete/${comprovante.codigo}`);

    return (
        <>
            <Container $visivel={visivel}>
                <Header>
                    <Title>Conferir Bilhete</Title>
                    <Icons>
                        <Icon $cor="green" title="Clique para compartilhar bilhete" onClick={compartilhar}>share</Icon>
                        {comprovante && comprovante.situacao !== 'Pendente' && (
                            <Icon $cor="green" title="Clique para acompanhar esse bilhete em tempo real" onClick={acompanhar}>desktop_mac</Icon>
                        )}
                        <Icon $cor={tema.principal} title="Clique para fechar o bilhete" onClick={ao_fechar}>close</Icon>
                    </Icons>
                </Header>
                {comprovante && (
                    <Data>
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
                                    {bloco ? linha_bloco(bloco, palpites) : linha_palpite(palpites[0])}
                                </Fragment>
                            ))}
                        </Hunches>
                        <Row><Label>Qtde. Jogos:</Label><span>{comprovante.quantidade_jogos ?? comprovante.palpites.length}</span></Row>
                        <Row><Label>Cotação Total:</Label><span>{Number(comprovante.cotacao_total).toFixed(2)}</span></Row>
                        <Row><Label>Valor:</Label><span>{real(comprovante.valor)}</span></Row>
                        <Row><Label>Prêmio:</Label><span>{real(comprovante.premio)}</span></Row>
                        {para_centavos(comprovante.valor_acrescido) > 0 && (
                            <Row><Label>Valor acrescido:</Label><span>{real(comprovante.valor_acrescido)}</span></Row>
                        )}
                    </Data>
                )}
            </Container>
            <Backdrop visivel={visivel} ao_fechar={ao_fechar} />
        </>
    );
}
