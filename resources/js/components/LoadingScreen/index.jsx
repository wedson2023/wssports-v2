import { Container, Text } from './styles';

// valores do anel "Rings" do react-loader-spinner (sistema antigo: 100px, #ccc)
const animacao_anel = (inicio) => (
    <>
        <animate attributeName="r" begin={inicio} dur="3s" values="6;22" calcMode="linear" repeatCount="indefinite" />
        <animate attributeName="stroke-opacity" begin={inicio} dur="3s" values="1;0" calcMode="linear" repeatCount="indefinite" />
        <animate attributeName="stroke-width" begin={inicio} dur="3s" values="2;0" calcMode="linear" repeatCount="indefinite" />
    </>
);

// tela de carregamento dos jogos (FR-029, R-22)
export default function LoadingScreen() {
    return (
        <Container>
            <svg width="100" height="100" viewBox="0 0 45 45" stroke="#ccc" aria-label="Carregando">
                <g fill="none" fillRule="evenodd" transform="translate(1 1)" strokeWidth="2">
                    <circle cx="22" cy="22" r="6" strokeOpacity="0">{animacao_anel('1.5s')}</circle>
                    <circle cx="22" cy="22" r="6" strokeOpacity="0">{animacao_anel('3s')}</circle>
                    <circle cx="22" cy="22" r="8">
                        <animate attributeName="r" begin="0s" dur="1.5s" values="6;1;2;3;4;5;6" calcMode="linear" repeatCount="indefinite" />
                    </circle>
                </g>
            </svg>
            <Text>Carregando jogos.</Text>
        </Container>
    );
}
