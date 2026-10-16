import Spinner from '../Spinner';
import { Button, Buttons, Container, Image, Provider, Title } from './styles';

// imagem padrão quando o provedor não manda imagem ou o endereço dele não existe (404)
const imagem_padrao = '/images/cassino/jogo_sem_imagem.svg';

const usar_imagem_padrao = (evento) => {
    // evita repetir o erro se a própria imagem padrão falhar
    evento.currentTarget.onerror = null;
    evento.currentTarget.src = imagem_padrao;
};

// cartão do jogo (Game de screens/casino): Jogar e Demo aparecem ao passar o mouse ou tocar
export default function GameCard({ jogo, abrindo, ao_jogar, ao_demo }) {
    const carregando = abrindo === jogo.id;

    return (
        <Container>
            <Buttons>
                <Button onClick={() => ao_jogar(jogo)}>{carregando ? <Spinner tamanho={20} /> : 'Jogar'}</Button>
                {jogo.demo && <Button onClick={() => ao_demo(jogo)}>{carregando ? <Spinner tamanho={20} /> : 'Demo'}</Button>}
            </Buttons>
            <Image src={jogo.imagem || imagem_padrao} alt="" loading="lazy" onError={usar_imagem_padrao} />
            <Title title={jogo.nome}>{jogo.nome}</Title>
            <Provider>{jogo.provedor}</Provider>
        </Container>
    );
}
