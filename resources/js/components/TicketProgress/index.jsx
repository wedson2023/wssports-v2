import { aparencia_estado, cores_estado } from '../../utils/live_states';
import { Bar, Legend, LegendItem, Segment, Swatch } from './styles';

// cor de cada segmento: o resultado quando decidido, senão o estado atual
const cor_segmento = (segmento) => (segmento.estado === 'decidido'
    ? aparencia_estado(segmento.resultado).cor
    : cores_estado[segmento.estado] ?? cores_estado.aguardando);

const itens_legenda = [
    { chave: 'decididos', rotulo: 'decidido(s)', cor: cores_estado.devolvido },
    { chave: 'ganhando', rotulo: 'ganhando', cor: cores_estado.ganhando },
    { chave: 'perdendo', rotulo: 'perdendo', cor: cores_estado.perdendo },
    { chave: 'aguardando', rotulo: 'aguardando', cor: cores_estado.aguardando },
    { chave: 'nao_iniciados', rotulo: 'não iniciado(s)', cor: cores_estado.nao_iniciado },
];

// barra com um segmento por palpite (ou bloco) e a legenda em texto com a contagem (FR-002)
export default function TicketProgress({ progresso }) {
    const legenda = itens_legenda.filter(({ chave }) => progresso[chave] > 0);

    return (
        <div>
            <Bar role="img" aria-label={legenda.map(({ chave, rotulo }) => `${progresso[chave]} ${rotulo}`).join(', ')}>
                {progresso.segmentos.map((segmento, indice) => (
                    <Segment key={indice} $cor={cor_segmento(segmento)} />
                ))}
            </Bar>
            <Legend>
                {legenda.map(({ chave, rotulo, cor }) => (
                    <LegendItem key={chave}>
                        <Swatch $cor={cor} aria-hidden="true" />
                        {progresso[chave]} {rotulo}
                    </LegendItem>
                ))}
            </Legend>
        </div>
    );
}
