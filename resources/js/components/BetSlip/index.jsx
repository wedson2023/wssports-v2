import BetSlipActions from '../BetSlipActions';
import BetSlipForm from '../BetSlipForm';
import BetSlipItem from '../BetSlipItem';
import EmptyMessage from '../EmptyMessage';
import PanelHeader from '../PanelHeader';
import useCupom from '../../hooks/useCupom';
import { cotacao_da_perna } from '../../utils/bet_builder';
import { Container, List } from './styles';

// coluna "Cupom" no desktop e painel sobre a tela no mobile (FR-035, FR-036); os palpites do mesmo
// jogo do pré-jogo aparecem juntos, como um bloco Criar Aposta (spec 008)
export default function BetSlip({ aberto, ao_fechar, ao_finalizar, ao_abrir_detalhes }) {
    const { pernas, desconto_criar_aposta, remover_palpite } = useCupom();

    return (
        <Container $aberto={aberto}>
            <PanelHeader titulo="Cupom" ao_fechar={ao_fechar} />
            <List>
                {pernas.length ? (
                    pernas.map((perna) => (
                        <BetSlipItem
                            key={`${perna.tipo}-${perna.confronto_id}`}
                            palpites={perna.palpites}
                            cotacao_bloco={perna.bloco ? cotacao_da_perna(perna, desconto_criar_aposta) : null}
                            ao_remover={remover_palpite}
                            ao_abrir_detalhes={() => ao_abrir_detalhes(perna.palpites[0])}
                        />
                    ))
                ) : (
                    <EmptyMessage>Nenhum jogo selecionado</EmptyMessage>
                )}
            </List>
            <BetSlipForm>
                <BetSlipActions ao_finalizar={ao_finalizar} />
            </BetSlipForm>
        </Container>
    );
}
