import RuleItem from '../RuleItem';
import RulesBlock from '../RulesBlock';
import { formatar_real, para_centavos } from '../../utils/money';
import { Paragraph } from './styles';

const real = (valor) => `R$ ${formatar_real(para_centavos(valor))}`;
const percentual = (valor) => `${Number(valor).toLocaleString('pt-BR')}%`;

// bloco "Encerramento de aposta" das regras (spec 010, FR-039), com os valores atuais da banca;
// sem o encerramento ligado, não aparece
export default function CashOutRules({ encerramento }) {
    if (!encerramento) return null;

    const modalidades = ['total (a aposta inteira)'];
    if (encerramento.parcial) modalidades.push(`parcial (recebe uma parte e o restante continua valendo, até ${encerramento.limite_parciais} vezes por aposta)`);
    if (encerramento.automatico) modalidades.push('automático (a aposta é encerrada sozinha quando a oferta alcança o valor-alvo escolhido)');

    return (
        <RulesBlock titulo="Encerramento de aposta">
            <RuleItem titulo="Quem pode encerrar">
                <Paragraph>
                    Só o cliente com login, dono da aposta, pode encerrá-la. Apostas feitas sem login (código), apostas de
                    vendedores, apostas com Criar Aposta e apostas pagas com o saldo de promoção de esportes não podem ser
                    encerradas. O encerramento pode ser {modalidades.join(', ')}.
                </Paragraph>
            </RuleItem>
            <RuleItem titulo="Quando a oferta aparece">
                <Paragraph>
                    A oferta aparece no bilhete em tempo real enquanto a aposta estiver ativa e sem resultado, nenhum
                    palpite estiver perdido e todos os palpites em aberto tiverem cotação no momento. Jogo travado,
                    mercado suspenso ou jogo aguardando a apuração tiram a oferta até a situação normalizar.
                    {encerramento.jogos_nao_iniciados
                        ? ' A aposta pode ser encerrada desde a criação, antes do início dos jogos.'
                        : ' A oferta só aparece depois que o primeiro jogo da aposta começar.'}
                </Paragraph>
            </RuleItem>
            <RuleItem titulo="Como o valor é calculado">
                <Paragraph>
                    A oferta é o prêmio da aposta dividido pelas cotações atuais dos palpites em aberto, menos a margem
                    da casa de {percentual(encerramento.margem_pre_jogo)} quando nenhum jogo em aberto começou e de{' '}
                    {percentual(encerramento.margem_ao_vivo)} quando algum jogo está ao vivo. O valor acrescido por
                    promoção não entra no cálculo. A oferta nunca é maior que o prêmio e só aparece a partir de{' '}
                    {real(encerramento.valor_minimo)} ou {percentual(encerramento.percentual_minimo)} do valor apostado,
                    o que for maior.
                </Paragraph>
            </RuleItem>
            <RuleItem titulo="Confirmação e crédito">
                <Paragraph>
                    A oferta vale por {encerramento.segundos_validade} segundos na confirmação. Com jogo ao vivo, o
                    pedido aguarda o tempo de confirmação do ao vivo e é recusado se houver lance ou se a oferta mudar
                    fora da sua preferência de alterações. O valor encerrado entra no saldo {encerramento.minutos_janela > 0
                        ? `${encerramento.minutos_janela} minuto(s) depois do encerramento`
                        : 'logo depois do encerramento'}.
                </Paragraph>
            </RuleItem>
            <RuleItem titulo="Quando o encerramento é desfeito">
                <Paragraph>
                    Se ficar comprovado que um palpite em aberto no momento do encerramento já estava perdido antes dele
                    (resultado lançado com atraso), o encerramento é desfeito: o valor não é creditado ou, se já foi, é
                    estornado. Outras correções de resultado não alteram o valor encerrado.
                </Paragraph>
            </RuleItem>
        </RulesBlock>
    );
}
