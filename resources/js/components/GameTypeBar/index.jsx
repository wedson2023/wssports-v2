import Spinner from '../Spinner';
import { rotulo_tipo } from '../../utils/casino_types';
import { Container, Item, List } from './styles';

// barra de tipos de jogo (Types de screens/casino), sem o item Populares
export default function GameTypeBar({ tipos, selecionado, carregando, ao_escolher }) {
    const visiveis = tipos.filter(({ tipo }) => tipo !== 'populares');

    return (
        <Container>
            <List $quantidade={visiveis.length}>
                {visiveis.map(({ tipo }) => (
                    <Item key={tipo} $selecionado={selecionado === tipo} $quantidade={visiveis.length} onClick={() => ao_escolher(tipo)}>
                        {carregando === `tipo-${tipo}` ? <Spinner tamanho={20} /> : rotulo_tipo(tipo)}
                    </Item>
                ))}
            </List>
        </Container>
    );
}
