import useVoltarFecha from '../../hooks/useVoltarFecha';
import { formatar_real, para_centavos } from '../../utils/money';
import { Container, Content, Header, Icon, Item, Message, Screen, Text, Title } from './styles';

const data_curta = (iso) => (iso ? new Date(iso).toLocaleDateString('pt-BR', { timeZone: 'America/Sao_Paulo' }) : 'o fim da campanha');

// "valor" de cada promoção como no modal/bonus antigo: fixo em reais ou percentual do depósito
const valor_da_promocao = (promocao) => (promocao.tipo_ganho === 'Fixo'
    ? `R$ ${formatar_real(para_centavos(promocao.valor))}`
    : `${Number(promocao.valor)}% no valor do depósito`);

const como_resgatar = (promocao) => (promocao.categoria === 'Indicação'
    ? `indicar ${promocao.quantidade_indicacoes ?? 5} amigos`
    : promocao.categoria === 'Primeiro depósito' ? 'realizar um primeiro depósito' : 'realizar um depósito');

// modal Promoções do cassino (screens/casino/modal/bonus); o voltar do aparelho fecha
export default function PromotionsModal({ aberto, promocoes, ao_fechar }) {
    useVoltarFecha(aberto, ao_fechar);

    return (
        <>
            <Container $aberto={aberto}>
                <Header>
                    <Title>Promoções</Title>
                    <Icon $colorido onClick={ao_fechar}>close</Icon>
                </Header>
                <Content>
                    {promocoes.length ? promocoes.map((promocao) => (
                        <Item key={promocao.id}>
                            <Icon>confirmation_number</Icon>
                            <Text>
                                Bônus para {promocao.modalidade.toLowerCase()} disponível para você no valor de {valor_da_promocao(promocao)}, para resgatar esse valor basta {como_resgatar(promocao)} até {data_curta(promocao.data_fim)}.
                            </Text>
                        </Item>
                    )) : <Message>Nenhuma promoção encontrada.</Message>}
                </Content>
            </Container>
            <Screen $aberto={aberto} onClick={ao_fechar} />
        </>
    );
}
