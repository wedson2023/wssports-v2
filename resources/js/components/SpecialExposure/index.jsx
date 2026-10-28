import Spinner from '../Spinner';
import { formatar_valor } from '../../utils/charts';
import {
    Empty, Entry, EntryLine, Highlight, List, Loading, Retry, Status, Summary, VoidButton,
} from './styles';

const cotacao = (valor) => Number(valor).toFixed(2).replace('.', ',');

const grupo = (rotulo, dados) => `${rotulo}: ${dados.quantidade} · ${formatar_valor(dados.valor)} · retorno ${formatar_valor(dados.possivel_retorno)}`;

// exposição por opção na gaveta do especial (spec 024, FR-021 e FR-022, contracts/paginas §2):
// apostas, valor e possível retorno em simples, múltiplas e total, com a opção de maior retorno em
// destaque e o total da categoria; Anular por opção quando pode_anular. exposicao = GET .../exposicao
export default function SpecialExposure({ exposicao, carregando, erro, pode_anular, ao_anular, ao_tentar_novamente }) {
    if (carregando && !exposicao) return <Loading><Spinner tamanho={30} cor="currentColor" /></Loading>;

    if (erro && !exposicao) {
        return (
            <Empty>
                {erro.response?.data?.message ?? 'Não foi possível carregar a exposição.'}
                <Retry type="button" onClick={ao_tentar_novamente}>Tentar novamente</Retry>
            </Empty>
        );
    }

    if (!exposicao) return null;

    return (
        <>
            <Summary>
                {`Total da categoria: ${exposicao.total.quantidade} ${exposicao.total.quantidade === 1 ? 'aposta' : 'apostas'} · ${formatar_valor(exposicao.total.valor)}. Múltiplas contam inteiras.`}
                {exposicao.alcance === 'rede' && ' Números da sua rede.'}
            </Summary>
            {!exposicao.opcoes.length && <Empty>Nenhuma opção.</Empty>}
            <List>
                {exposicao.opcoes.map((opcao) => {
                    const destaque = opcao.id === exposicao.maior_retorno_opcoes_id;
                    const Card = destaque ? Highlight : Entry;

                    return (
                        <Card key={opcao.id}>
                            <EntryLine>
                                <strong>{`${opcao.nome} · ${cotacao(opcao.cotacao)}`}</strong>
                                <Status $situacao={opcao.situacao}>{opcao.empatada ? `${opcao.situacao} (empatada)` : opcao.situacao}</Status>
                            </EntryLine>
                            <EntryLine><span>{grupo('Simples', opcao.simples)}</span></EntryLine>
                            <EntryLine><span>{grupo('Múltiplas', opcao.multiplas)}</span></EntryLine>
                            <EntryLine>
                                <strong>{grupo('Total', opcao.total)}</strong>
                                {pode_anular && opcao.situacao !== 'Anulada' && (
                                    <VoidButton type="button" onClick={() => ao_anular(opcao)}>
                                        <i className="material-icons" aria-hidden="true">block</i>
                                        Anular
                                    </VoidButton>
                                )}
                            </EntryLine>
                            {destaque && <EntryLine><span>Maior possível retorno da categoria</span></EntryLine>}
                        </Card>
                    );
                })}
            </List>
        </>
    );
}
