import { usePage } from '@inertiajs/react';

import Spinner from '../Spinner';
import { Arrow, Card, Content, Description, Error, Header, Loading, Retry, Texts, Title } from './styles';

// cartão de uma seção da tela Configurações (spec 021): ícone, título e o que a seção controla; na
// aba Operação vira sanfona (recolhivel). Mostra o carregando e o erro com "Tentar novamente"
export default function SettingsSection({
    icone, titulo, descricao, recolhivel = false, aberta = true, ao_alternar, carregando = false, erro = null, ao_tentar_de_novo, children,
}) {
    const { tema } = usePage().props;
    const mostrar = !recolhivel || aberta;

    const cabecalho = (
        <>
            <i className="material-icons" aria-hidden="true">{icone}</i>
            <Texts>
                <Title>{titulo}</Title>
                {descricao && <Description>{descricao}</Description>}
            </Texts>
            {recolhivel && <Arrow className="material-icons" aria-hidden="true" $aberta={aberta}>expand_more</Arrow>}
        </>
    );

    const conteudo = () => {
        if (carregando) return <Loading><Spinner tamanho={28} cor={tema.temas} /></Loading>;

        if (erro) {
            return (
                <Error>
                    Não foi possível carregar esta seção.
                    {ao_tentar_de_novo && <Retry type="button" onClick={ao_tentar_de_novo}>Tentar novamente</Retry>}
                </Error>
            );
        }

        return children;
    };

    return (
        <Card>
            {recolhivel
                ? <Header as="button" type="button" aria-expanded={aberta} $aberta={aberta} onClick={ao_alternar}>{cabecalho}</Header>
                : <Header $aberta>{cabecalho}</Header>}
            {mostrar && <Content>{conteudo()}</Content>}
        </Card>
    );
}
