import { Container, Icon, Item, Slide, Title } from './styles';

// esportes na ordem, ícones e rótulos do sistema antigo (FR-009); "chave" = nome do esporte no backend
export const esportes = [
    { chave: 'CASSINO', icone: 'casino', titulo: 'Cassino' },
    { chave: 'FUTEBOL', icone: 'sports_soccer', titulo: 'Futebol' },
    { chave: 'AO VIVO', icone: 'live_tv', titulo: 'Ao vivo' },
    { chave: 'BASQUETE', icone: 'sports_basketball', titulo: 'Basquete' },
    { chave: 'LUTAS', icone: 'sports_mma', titulo: 'Lutas' },
    { chave: 'ESPECIAL', icone: 'sort', titulo: 'Especiais' },
    { chave: 'VÔLEI', icone: 'sports_volleyball', titulo: 'Vôlei' },
    { chave: 'TÊNIS', icone: 'sports_tennis', titulo: 'Tênis' },
    { chave: 'TÊNIS DE MESA', icone: 'sports_tennis', titulo: 'Tênis de mesa' },
    { chave: 'E-SPORTS', icone: 'sports_esports', titulo: 'E-sports' },
    { chave: 'FUTEBOL AMERICANO', icone: 'sports_football', titulo: 'Futebol americano' },
    { chave: 'RUGBY', icone: 'sports_rugby', titulo: 'Rugby' },
    { chave: 'HOQUEI NO GELO', icone: 'sports_hockey', titulo: 'Hoquei no gelo' },
    { chave: 'HANDEBOL', icone: 'sports_handball', titulo: 'Handebol' },
    { chave: 'BAISEBOL', icone: 'sports_baseball', titulo: 'Baisebol' },
];

// barra de esportes; recebe só os esportes visíveis e o ativo ("AO VIVO" para a aba ao vivo)
export default function SportsBar({ visiveis, esporte_ativo, ao_escolher }) {
    return (
        <Container>
            <Slide>
                {esportes.filter(({ chave }) => visiveis.includes(chave)).map(({ chave, icone, titulo }) => (
                    <Item key={chave} $ativo={esporte_ativo === chave} onClick={() => ao_escolher(chave)} title={titulo}>
                        <Icon>{icone}</Icon>
                        <Title>{titulo}</Title>
                    </Item>
                ))}
            </Slide>
        </Container>
    );
}
