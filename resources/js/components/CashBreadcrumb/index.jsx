import { Back, Container, Current, Part, Separator } from './styles';

// trilha do caixa (spec 017, contracts/paginas §3.1): do topo visível até o nível em foco, cada parte
// clicável; no celular vira "‹ Voltar" com o nome do nível atual. Some quando só há o próprio nível
export default function CashBreadcrumb({ trilha, ao_abrir }) {
    if (!trilha || trilha.length <= 1) return null;

    const atual = trilha[trilha.length - 1];
    const anterior = trilha[trilha.length - 2];

    return (
        <Container aria-label="Níveis do caixa">
            <Back type="button" onClick={() => ao_abrir(anterior.id)}>
                <i className="material-icons" aria-hidden="true">chevron_left</i>
                Voltar
                <Current>{atual.nome}</Current>
            </Back>
            <ol>
                {trilha.map((parte, indice) => (
                    <li key={parte.id ?? 'banca'}>
                        {indice > 0 && <Separator aria-hidden="true">chevron_right</Separator>}
                        {indice === trilha.length - 1
                            ? <Current aria-current="page">{parte.nome}</Current>
                            : <Part type="button" onClick={() => ao_abrir(parte.id)}>{parte.nome}</Part>}
                    </li>
                ))}
            </ol>
        </Container>
    );
}
