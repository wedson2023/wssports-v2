import { formatar_data_hora } from '../../utils/dates';
import { Header, Item, Name, Odd } from './styles';

// categoria especial com as opções e as cotações (screens/main/components/specials do sistema
// antigo); a opção no cupom fica destacada e tocar de novo remove (spec 006, FR-014)
export default function SpecialCard({ especial, selecionada_id = null, ao_escolher }) {
    return (
        <div>
            <Header>
                <span>{especial.nome}</span>
                <time>{formatar_data_hora(especial.data_limite)}</time>
            </Header>
            {especial.opcoes.map((opcao) => (
                <Item key={opcao.id}>
                    <Name title={opcao.nome}>{opcao.nome}</Name>
                    <Odd role="button" $selecionado={selecionada_id === opcao.id} onClick={() => ao_escolher(opcao)}>
                        {Number(opcao.cotacao).toFixed(2)}
                    </Odd>
                </Item>
            ))}
        </div>
    );
}
