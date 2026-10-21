import { useState } from 'react';
import { useTheme } from 'styled-components';

import CashModal from '../CashModal';
import Pagination from '../Pagination';
import Spinner from '../Spinner';
import UndoSettlementModal from '../UndoSettlementModal';
import useTransacoesCaixa from '../../hooks/useTransacoesCaixa';
import api from '../../utils/api';
import { alerta_erro, confirmar } from '../../utils/alerts';
import { formatar_valor } from '../../utils/charts';
import { formatar_data_hora } from '../../utils/dates';
import {
    Action, Amount, DateInput, Empty, Filters, Item, ItemActions, ItemIcon, ItemInfo, ItemMeta, ItemTitle, List, Note, Select,
} from './styles';

const tipos_filtro = [
    { chave: 'todos', rotulo: 'Todos' },
    { chave: 'suprimento', rotulo: 'Suprimentos' },
    { chave: 'retirada', rotulo: 'Retiradas' },
    { chave: 'prestacao', rotulo: 'Prestações' },
];

// ícone e cor de cada tipo de transação
const visual = {
    Suprimento: { icone: 'redo', tom: '#1e88e5' },
    Retirada: { icone: 'undo', tom: '#f9a825' },
    'Prestação de contas': { icone: 'get_app', tom: '#43a047' },
};

// histórico de transações do vendedor (spec 017, FR-016, contracts/paginas §4): suprimentos,
// retiradas e prestações, com filtro por tipo e período; excluídos e desfeitos riscados com quem fez;
// Excluir no lançamento aberto e Desfazer na última prestação, conforme o servidor permitir.
// Altura fixa desde a abertura (lista que carrega depois)
export default function CashTransactionsModal({ vendedor, token, ao_fechar, ao_alterar }) {
    const tema = useTheme();
    const transacoes = useTransacoesCaixa(vendedor);
    const [desfazendo, definir_desfazendo] = useState(null);

    const excluir = async (item) => {
        if (!(await confirmar(`Excluir o lançamento de ${formatar_valor(item.valor)}?`, tema.principal))) return;

        try {
            await api.delete(`/caixa/lancamentos/${item.id}`, { headers: { Authorization: `Bearer ${token}` } });
            transacoes.recarregar();
            ao_alterar('Lançamento excluído.');
        } catch (erro) {
            alerta_erro(erro);
        }
    };

    const desfeita = (mensagem) => {
        definir_desfazendo(null);
        transacoes.recarregar();
        ao_alterar(mensagem);
    };

    return (
        <>
            <CashModal
                aberto={Boolean(vendedor)}
                icone="compare_arrows"
                titulo={vendedor ? `Transações · ${vendedor.nome}` : 'Transações'}
                descricao="Suprimentos, retiradas e prestações de contas, da mais nova para a mais antiga."
                ao_fechar={ao_fechar}
                altura_fixa
                largura={640}
            >
                <Filters>
                    <Select aria-label="Tipo" value={transacoes.filtros.tipo} onChange={(evento) => transacoes.filtrar({ tipo: evento.target.value })}>
                        {tipos_filtro.map((tipo) => <option key={tipo.chave} value={tipo.chave}>{tipo.rotulo}</option>)}
                    </Select>
                    <DateInput type="date" aria-label="De" value={transacoes.filtros.de} onChange={(evento) => transacoes.filtrar({ de: evento.target.value })} />
                    <DateInput type="date" aria-label="Até" value={transacoes.filtros.ate} onChange={(evento) => transacoes.filtrar({ ate: evento.target.value })} />
                </Filters>

                {transacoes.carregando && !transacoes.itens.length && <Empty><Spinner tamanho={30} cor="currentColor" /></Empty>}
                {!transacoes.carregando && transacoes.erro && <Empty>{transacoes.erro.response?.data?.message ?? 'Não foi possível carregar as transações.'}</Empty>}
                {!transacoes.carregando && !transacoes.erro && !transacoes.itens.length && <Empty>Nenhuma transação encontrada.</Empty>}

                <List>
                    {transacoes.itens.map((item) => {
                        const { icone, tom } = visual[item.tipo] ?? visual.Suprimento;
                        const prestacao = item.tipo === 'Prestação de contas';
                        const anulado = item.excluido || item.desfeita;

                        return (
                            <Item key={item.chave} $anulado={anulado}>
                                <ItemIcon $tom={tom}>{icone}</ItemIcon>
                                <ItemInfo>
                                    <ItemTitle>
                                        <span>{item.tipo}{item.em_lote ? ' (em lote)' : ''}</span>
                                        <Amount $anulado={anulado}>{formatar_valor(prestacao ? item.valor_recebido : item.valor)}</Amount>
                                    </ItemTitle>
                                    <ItemMeta>
                                        {formatar_data_hora(item.data)}
                                        {item.registrado_por ? ` · ${item.registrado_por.nome}` : ''}
                                        {prestacao ? ` · apurado ${formatar_valor(item.valor_apurado)}` : ''}
                                        {prestacao && Number(item.diferenca) !== 0 ? ` · fica ${formatar_valor(item.diferenca)}` : ''}
                                        {!prestacao && item.prestado ? ' · prestado' : ''}
                                    </ItemMeta>
                                    {item.observacao && <Note>{item.observacao}</Note>}
                                    {item.excluido && <Note $alerta>{`Excluído por ${item.excluido_por?.nome ?? '—'}`}</Note>}
                                    {item.desfeita && <Note $alerta>{`Desfeita por ${item.desfeita_por?.nome ?? '—'}: ${item.motivo_desfazer}`}</Note>}
                                </ItemInfo>
                                <ItemActions>
                                    {item.pode_excluir && (
                                        <Action type="button" title="Excluir lançamento" aria-label="Excluir lançamento" $tom="#e53935" onClick={() => excluir(item)}>
                                            <i className="material-icons" aria-hidden="true">delete</i>
                                        </Action>
                                    )}
                                    {item.pode_desfazer && (
                                        <Action type="button" title="Desfazer prestação" aria-label="Desfazer prestação" $tom="#e53935" onClick={() => definir_desfazendo(item)}>
                                            <i className="material-icons" aria-hidden="true">undo</i>
                                        </Action>
                                    )}
                                </ItemActions>
                            </Item>
                        );
                    })}
                </List>
                <Pagination pagina={transacoes.pagina} ultima={transacoes.ultima} ao_trocar={transacoes.definir_pagina} />
            </CashModal>
            <UndoSettlementModal prestacao={desfazendo} token={token} ao_fechar={() => definir_desfazendo(null)} ao_concluir={desfeita} />
        </>
    );
}
