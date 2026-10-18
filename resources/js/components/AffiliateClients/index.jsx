import Spinner from '../Spinner';
import { formatar_valor } from '../../hooks/useAfiliado';
import usePaginacao from '../../hooks/usePaginacao';
import { formatar_data_hora } from '../../utils/dates';
import { List, Message, More, Td, Thead, Tr } from './styles';

// clientes indicados pelo afiliado (spec 012, FR-055): nome parcial, cadastro e total depositado
export default function AffiliateClients({ requisitar }) {
    const { itens, carregando, tem_mais, carregar_mais } = usePaginacao(requisitar, '/area-afiliado/clientes', {});

    return (
        <>
            <List>
                <Thead>
                    <Td $largura="40%">NOME</Td>
                    <Td $largura="30%">CADASTRO</Td>
                    <Td $largura="30%">DEPOSITADO</Td>
                </Thead>
                {itens.length ? itens.map((cliente, indice) => (
                    <Tr key={`${cliente.cadastrado_em}-${indice}`}>
                        <Td $largura="40%">{cliente.nome}</Td>
                        <Td $largura="30%">{formatar_data_hora(cliente.cadastrado_em)}</Td>
                        <Td $largura="30%">{formatar_valor(cliente.total_depositado)}</Td>
                    </Tr>
                )) : !carregando && <Message>Nenhum cliente indicado ainda.</Message>}
            </List>

            {(tem_mais || carregando) && (
                <More type="button" onClick={carregar_mais} disabled={carregando}>
                    {carregando ? <Spinner tamanho={16} /> : 'Carregar mais'}
                </More>
            )}
        </>
    );
}
