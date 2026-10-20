import { Body, Container, ErrorBox, Header, Icons, RefreshIcon, RetryButton, SkeletonBlock, SkeletonGrid, Title } from './styles';
import EmptyMessage from '../EmptyMessage';

// esqueleto no formato de cada tipo de seção enquanto carrega pela primeira vez
const esqueletos = {
    cartoes: () => (
        <SkeletonGrid>
            {Array.from({ length: 6 }, (_, indice) => <SkeletonBlock key={indice} $altura={64} />)}
        </SkeletonGrid>
    ),
    grafico: () => <SkeletonBlock $altura={220} />,
    tabela: () => Array.from({ length: 4 }, (_, indice) => <SkeletonBlock key={indice} $altura={36} />),
};

// mensagem do erro da requisição (axios) ou aviso de conexão
const mensagem_erro = (erro) => erro?.response?.data?.message ?? 'Não foi possível carregar esta seção. Verifique sua conexão.';

// moldura de uma seção da Dashboard (Group + Header do sistema antigo): título, ⟳ e os estados
// carregando, sem dados e erro, sem travar as outras seções (spec 014, FR-038)
export default function DashboardSection({ titulo, esqueleto = 'cartoes', carregando, atualizando, erro, vazio, ao_atualizar, acoes_extras = null, largura_total = false, children }) {
    const conteudo = () => {
        if (carregando) return esqueletos[esqueleto]();

        if (erro) {
            return (
                <ErrorBox>
                    <span>{mensagem_erro(erro)}</span>
                    <RetryButton type="button" onClick={ao_atualizar}>Tentar novamente</RetryButton>
                </ErrorBox>
            );
        }

        if (vazio) return <EmptyMessage>Sem movimento hoje.</EmptyMessage>;

        return children;
    };

    return (
        <Container $largura_total={largura_total}>
            <Header>
                <Title>{titulo}</Title>
                <Icons>
                    {acoes_extras}
                    <RefreshIcon $girando={atualizando} title="Atualizar" onClick={atualizando ? undefined : ao_atualizar}>refresh</RefreshIcon>
                </Icons>
            </Header>
            <Body>{conteudo()}</Body>
        </Container>
    );
}
