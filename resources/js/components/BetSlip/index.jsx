import BetSlipActions from '../BetSlipActions';
import BetSlipForm from '../BetSlipForm';
import BetSlipItem from '../BetSlipItem';
import EmptyMessage from '../EmptyMessage';
import PanelHeader from '../PanelHeader';
import useCupom from '../../hooks/useCupom';
import { Container, List } from './styles';

// coluna "Cupom" no desktop e painel sobre a tela no mobile (FR-035, FR-036)
export default function BetSlip({ aberto, ao_fechar, ao_finalizar, ao_abrir_detalhes }) {
    const { cupom, remover_palpite } = useCupom();

    return (
        <Container $aberto={aberto}>
            <PanelHeader titulo="Cupom" ao_fechar={ao_fechar} />
            <List>
                {cupom.palpites.length ? (
                    cupom.palpites.map((palpite) => (
                        <BetSlipItem
                            key={`${palpite.tipo}-${palpite.confronto_id}`}
                            palpite={palpite}
                            ao_remover={() => remover_palpite(palpite.tipo, palpite.confronto_id)}
                            ao_abrir_detalhes={() => ao_abrir_detalhes(palpite)}
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
