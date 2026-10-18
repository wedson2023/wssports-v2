import Spinner from '../Spinner';
import { formatar_valor } from '../../hooks/useAfiliado';
import usePaginacao from '../../hooks/usePaginacao';
import { formatar_data_hora } from '../../utils/dates';
import { List, Message, More, Td, Thead, Tr } from './styles';

// histórico de prestações de contas do afiliado (spec 012, FR-054)
export default function AffiliateSettlements({ requisitar }) {
    const { itens, carregando, tem_mais, carregar_mais } = usePaginacao(requisitar, '/area-afiliado/prestacoes', {});

    return (
        <>
            <List>
                <Thead>
                    <Td $largura="34%">PERÍODO</Td>
                    <Td $largura="18%">APURADO</Td>
                    <Td $largura="18%">PAGO</Td>
                    <Td $largura="30%">OBS.</Td>
                </Thead>
                {itens.length ? itens.map((prestacao) => (
                    <Tr key={prestacao.id}>
                        <Td $largura="34%">{formatar_data_hora(prestacao.periodo_inicio)} a {formatar_data_hora(prestacao.periodo_fim)}</Td>
                        <Td $largura="18%" $negativo={String(prestacao.valor_apurado).startsWith('-')}>{formatar_valor(prestacao.valor_apurado)}</Td>
                        <Td $largura="18%">{formatar_valor(prestacao.valor_pago)}</Td>
                        <Td $largura="30%">{prestacao.observacao ?? '-'}</Td>
                    </Tr>
                )) : !carregando && <Message>Nenhuma prestação de contas ainda.</Message>}
            </List>

            {(tem_mais || carregando) && (
                <More type="button" onClick={carregar_mais} disabled={carregando}>
                    {carregando ? <Spinner tamanho={16} /> : 'Carregar mais'}
                </More>
            )}
        </>
    );
}
