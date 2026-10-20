import Backdrop from '../Backdrop';
import useVoltarFecha from '../../hooks/useVoltarFecha';
import { formatar_valor } from '../../utils/charts';
import {
    Body, Close, Container, Game, GameIndex, GameName, Header, HeaderIcon, HeaderText, Option, Section, SectionTitle, Stat,
    StatLabel, Stats, StatValue, Subtitle, Ticket, TicketAction, TicketCode, TicketIcon, TicketInfo, TicketPeople, TicketValues, Title,
} from './styles';

// detalhe de uma combinação de risco (modals/Combination do sistema antigo, com CLIENTE, VALOR e
// PRÊMIO de cada bilhete): resumo do risco, palpites em comum e os bilhetes, cada um abrindo o
// bilhete completo pelo ao_ver_bilhete da página
export default function CombinationModal({ combinacao, ao_fechar, ao_ver_bilhete }) {
    const visivel = Boolean(combinacao);
    useVoltarFecha(visivel, ao_fechar);

    return (
        <>
            <Backdrop visivel={visivel} ao_fechar={ao_fechar} camada={45} />
            <Container $visivel={visivel} role="dialog" aria-label="Combinação de risco">
                <Header>
                    <HeaderIcon>warning</HeaderIcon>
                    <HeaderText>
                        <Title>Combinação de risco</Title>
                        <Subtitle>Bilhetes abertos com os mesmos jogos e opções</Subtitle>
                    </HeaderText>
                    <Close title="Fechar" onClick={ao_fechar}>close</Close>
                </Header>
                {combinacao && (
                    <Body>
                        <Stats>
                            <Stat>
                                <StatLabel>Bilhetes</StatLabel>
                                <StatValue>{combinacao.quantidade_bilhetes}</StatValue>
                            </Stat>
                            <Stat>
                                <StatLabel>Apostado</StatLabel>
                                <StatValue>{formatar_valor(combinacao.valor_apostado)}</StatValue>
                            </Stat>
                            <Stat $risco>
                                <StatLabel>Prêmio em risco</StatLabel>
                                <StatValue>{formatar_valor(combinacao.premio)}</StatValue>
                            </Stat>
                        </Stats>

                        <Section>
                            <SectionTitle>Palpites em comum ({combinacao.jogos.length})</SectionTitle>
                            {combinacao.jogos.map((jogo, indice) => (
                                <Game key={`${jogo}-${indice}`}>
                                    <GameIndex>{indice + 1}</GameIndex>
                                    <GameName title={jogo}>{jogo}</GameName>
                                    <Option>{combinacao.opcoes[indice]}</Option>
                                </Game>
                            ))}
                        </Section>

                        <Section>
                            <SectionTitle>Bilhetes ({combinacao.bilhetes.length})</SectionTitle>
                            {combinacao.bilhetes.map((bilhete) => (
                                <Ticket key={bilhete.codigo} type="button" title="Ver bilhete completo" onClick={() => ao_ver_bilhete(bilhete.codigo)}>
                                    <TicketIcon>confirmation_number</TicketIcon>
                                    <TicketInfo>
                                        <TicketCode>{bilhete.codigo}</TicketCode>
                                        <TicketPeople>
                                            {bilhete.apostador}
                                            {bilhete.vendedor && ` · vendedor ${bilhete.vendedor}`}
                                        </TicketPeople>
                                    </TicketInfo>
                                    <TicketValues>
                                        <span>Apostado <strong>{formatar_valor(bilhete.valor)}</strong></span>
                                        <span>Prêmio <strong>{formatar_valor(bilhete.premio)}</strong></span>
                                    </TicketValues>
                                    <TicketAction>
                                        Ver bilhete
                                        <i className="material-icons" aria-hidden="true">chevron_right</i>
                                    </TicketAction>
                                </Ticket>
                            ))}
                        </Section>
                    </Body>
                )}
            </Container>
        </>
    );
}
