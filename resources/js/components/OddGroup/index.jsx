import OddButton from '../OddButton';
import { Container, MoreOptions } from './styles';

// cotações principais da listagem, na ordem do sistema antigo
export const cotacoes_principais = [
    { codigo: 'odd1', letra: 'C', mercado: 'Casa', titulo: 'Opção CASA' },
    { codigo: 'odd2', letra: 'E', mercado: 'Empate', titulo: 'Opção EMPATE' },
    { codigo: 'odd3', letra: 'F', mercado: 'Fora', titulo: 'Opção FORA' },
    { codigo: 'odd4', letra: 'A', mercado: 'Ambas', titulo: 'Opção AMBAS' },
];

const codigos_principais = cotacoes_principais.map(({ codigo }) => codigo);

// C, E, F, A e o "+N"; o "+N" fica na cor do tema quando o palpite é de outro mercado (FR-032)
export default function OddGroup({ confronto, codigo_selecionado, variacoes = {}, ao_escolher, ao_abrir_detalhes }) {
    const palpite_de_outro_mercado = Boolean(codigo_selecionado) && !codigos_principais.includes(codigo_selecionado);

    return (
        <Container>
            {cotacoes_principais.map(({ codigo, letra, mercado, titulo }) => (
                <OddButton
                    key={codigo}
                    letra={letra}
                    titulo={titulo}
                    cotacao={confronto.cotacoes?.[codigo]}
                    selecionado={codigo_selecionado === codigo}
                    variacao={variacoes[codigo] ?? null}
                    ao_clicar={() => ao_escolher({ codigo, mercado, cotacao: confronto.cotacoes[codigo] })}
                />
            ))}
            <MoreOptions $destacado={palpite_de_outro_mercado} onClick={ao_abrir_detalhes} title="Clique para abrir mais opções de apostas.">
                +{confronto.quantidade_cotacoes}
            </MoreOptions>
        </Container>
    );
}
