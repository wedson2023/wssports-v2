import Backdrop from '../Backdrop';
import useTelaMobile from '../../hooks/useTelaMobile';
import useVoltarFecha from '../../hooks/useVoltarFecha';
import { formatar_data_hora } from '../../utils/dates';
import { formatar_real, para_centavos } from '../../utils/money';
import { compartilhar_texto } from '../../utils/share';
import { useTheme } from 'styled-components';
import { Container, Data, Header, Hunch, Hunches, Icon, Icons, Label, Row, Status, Teams, Title } from './styles';

const real = (valor) => `R$ ${formatar_real(para_centavos(valor))}`;

// situação exibida: o resultado quando já apurado, senão a situação da aposta
const situacao_exibida = (comprovante) => (comprovante.resultado && comprovante.resultado !== 'Aguardando' ? comprovante.resultado : comprovante.situacao);

// comprovante do bilhete consultado pelo código (FR-044, data-model.md seção 4)
export default function TicketModal({ comprovante, ao_fechar }) {
    const e_mobile = useTelaMobile();
    const tema = useTheme();
    const visivel = Boolean(comprovante);
    useVoltarFecha(visivel, ao_fechar);

    const compartilhar = () => compartilhar_texto(`*ACOMPANHE SEU BILHETE:* \n\n CÓDIGO: ${comprovante.codigo}`, e_mobile);

    return (
        <>
            <Container $visivel={visivel}>
                <Header>
                    <Title>Conferir Bilhete</Title>
                    <Icons>
                        <Icon $cor="green" title="Clique para compartilhar bilhete" onClick={compartilhar}>share</Icon>
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
                            {comprovante.palpites.map((palpite) => (
                                <Hunch key={palpite.id} $cancelado={palpite.situacao === 'Cancelado'}>
                                    <Teams>{palpite.time_casa} x {palpite.time_fora}</Teams>
                                    <Row><Label>Horário:</Label><span>{formatar_data_hora(palpite.data_inicio)}</span></Row>
                                    <Row><Label>Campeonato:</Label><span>{palpite.campeonato}</span></Row>
                                    <Row><Label>Palpite:</Label><span>{palpite.jogador ? `${palpite.jogador} (${palpite.jogador_tipo})` : palpite.mercado}</span></Row>
                                    <Row><Label>Cotação:</Label><span>{Number(palpite.cotacao).toFixed(2)}</span></Row>
                                    <Row><Label>Tipo:</Label><span>{palpite.esporte}</span></Row>
                                    {palpite.placar_casa !== null && (
                                        <Row><Label>Placar:</Label><span>{`${palpite.placar_casa ?? 0} x ${palpite.placar_fora ?? 0}`}</span></Row>
                                    )}
                                    <Row>
                                        <Label>Situação:</Label>
                                        <Status $situacao={palpite.situacao}>{palpite.situacao}</Status>
                                    </Row>
                                </Hunch>
                            ))}
                        </Hunches>
                        <Row><Label>Qtde. Jogos:</Label><span>{comprovante.palpites.length}</span></Row>
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
