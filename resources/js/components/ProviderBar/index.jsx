import Spinner from '../Spinner';
import { Container, Image, Item, List, Name } from './styles';

// barra de provedores (Providers de screens/casino): logo de cada provedor; sem logo, o nome
export default function ProviderBar({ provedores, selecionado, carregando, ao_escolher }) {
    return (
        <Container>
            <List $quantidade={provedores.length}>
                {provedores.map((provedor) => (
                    <Item
                        key={provedor.id}
                        $selecionado={selecionado === provedor.id}
                        $quantidade={provedores.length}
                        onClick={() => ao_escolher(provedor)}
                        title={provedor.nome}
                    >
                        {carregando === `provedor-${provedor.id}`
                            ? <Spinner tamanho={20} />
                            : provedor.imagem ? <Image src={provedor.imagem} alt={provedor.nome} /> : <Name>{provedor.nome}</Name>}
                    </Item>
                ))}
            </List>
        </Container>
    );
}
