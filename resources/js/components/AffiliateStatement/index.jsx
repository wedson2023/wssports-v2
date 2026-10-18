import { useState } from 'react';

import Spinner from '../Spinner';
import { formatar_valor } from '../../hooks/useAfiliado';
import usePaginacao from '../../hooks/usePaginacao';
import { formatar_data_hora } from '../../utils/dates';
import { Button, Filters, List, Message, More, Td, Thead, Tr } from './styles';

const filtros_vazios = { de: '', ate: '', situacao: '' };

// extrato do afiliado: tabela ID, OP., VALOR, COMISSÃO, DATA do painel antigo, com filtro de
// período e de em aberto/prestados (spec 012, FR-054)
export default function AffiliateStatement({ requisitar }) {
    const [rascunho, definir_rascunho] = useState(filtros_vazios);
    const [filtros, definir_filtros] = useState(filtros_vazios);
    const { itens, carregando, tem_mais, carregar_mais } = usePaginacao(requisitar, '/area-afiliado/extrato', filtros);

    // lê o valor antes de atualizar: dentro da função de atualização o evento já não tem o campo
    const mudar = (campo) => (evento) => {
        const valor = evento.currentTarget.value;

        definir_rascunho((atual) => ({ ...atual, [campo]: valor }));
    };

    const filtrar = (evento) => {
        evento.preventDefault();
        definir_filtros(Object.fromEntries(Object.entries(rascunho).filter(([, valor]) => valor !== '')));
    };

    return (
        <>
            <Filters onSubmit={filtrar}>
                <input type="date" value={rascunho.de} onChange={mudar('de')} aria-label="Data inicial" />
                <input type="date" value={rascunho.ate} onChange={mudar('ate')} aria-label="Data final" />
                <select value={rascunho.situacao} onChange={mudar('situacao')} aria-label="Situação">
                    <option value="">Todos</option>
                    <option value="aberto">Em aberto</option>
                    <option value="prestado">Prestados</option>
                </select>
                <Button type="submit" disabled={carregando}>Filtrar</Button>
            </Filters>

            <List>
                <Thead>
                    <Td>ID</Td>
                    <Td>OP.</Td>
                    <Td>VALOR</Td>
                    <Td>COMISSÃO</Td>
                    <Td>DATA</Td>
                </Thead>
                {itens.length ? itens.map((lancamento) => (
                    <Tr key={lancamento.id}>
                        <Td>{lancamento.id}</Td>
                        <Td>{lancamento.evento}</Td>
                        <Td>{formatar_valor(lancamento.base)}</Td>
                        <Td $negativo={String(lancamento.valor).startsWith('-')}>{formatar_valor(lancamento.valor)}</Td>
                        <Td>{formatar_data_hora(lancamento.ocorrido_em)}</Td>
                    </Tr>
                )) : !carregando && <Message>Nenhum registro encontrado.</Message>}
            </List>

            {(tem_mais || carregando) && (
                <More type="button" onClick={carregar_mais} disabled={carregando}>
                    {carregando ? <Spinner tamanho={16} /> : 'Carregar mais'}
                </More>
            )}
        </>
    );
}
