import { Close, Container, Description, Icon, Texts, Title } from './styles';

// cabeçalho padrão dos modais do painel: ícone à esquerda, título ao lado e, embaixo do título, um
// texto menor explicando para que serve o modal (Constituição, "Cabeçalho dos modais")
export default function ModalHeader({ icone, titulo, descricao, ao_fechar }) {
    return (
        <Container>
            <Icon>{icone}</Icon>
            <Texts>
                <Title>{titulo}</Title>
                {descricao && <Description>{descricao}</Description>}
            </Texts>
            <Close type="button" title="Fechar" aria-label="Fechar" onClick={ao_fechar}>
                <i className="material-icons" aria-hidden="true">close</i>
            </Close>
        </Container>
    );
}
