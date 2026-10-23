import { Fragment, useState } from 'react';

import ActionsMenu from '../ActionsMenu';
import useTelaMobile from '../../hooks/useTelaMobile';
import { formatar_valor } from '../../utils/charts';
import { formatar_data_hora } from '../../utils/dates';
import {
    Actions, Cell, Code, Detail, Details, DetailsRow, Empty, Expand, HeadCell, Money, Retry, Row, Scroll, Skeleton, Sort, Sub, Table, Tag,
} from './styles';

// telefone com o DDI ("+55 (81) *****-1234")
const telefone = (cliente) => (cliente.telefone ? `+${cliente.ddi} ${cliente.telefone}` : '—');

const promocao = (cliente) => `${formatar_valor(cliente.saldo_promocao_esportes)} / ${formatar_valor(cliente.saldo_promocao_cassino)}`;

// colunas da lista (contracts/paginas §2): principal = sempre na linha; secundaria = na linha só no
// computador (no celular, nos detalhes ao expandir); ordem = coluna da API para ordenar
const colunas_da_lista = (lixeira) => [
    { chave: 'contato', rotulo: 'Contato', nivel: 'secundaria', texto: (cliente) => cliente.email ?? telefone(cliente) },
    { chave: 'saldo', rotulo: 'Saldo', nivel: 'principal', texto: (cliente) => formatar_valor(cliente.saldo), ordem: 'saldo' },
    { chave: 'promocao', rotulo: 'Promoção', nivel: 'secundaria', texto: promocao, dica: 'Saldo promocional de esportes / cassino' },
    { chave: 'saldo_liquido', rotulo: 'Saldo líquido', nivel: 'principal', dinheiro_sinal: true, dica: 'Depósitos e créditos manuais menos saques e débitos manuais (carteira real)' },
    { chave: 'ativo', rotulo: 'Ativo', nivel: 'secundaria', texto: (cliente) => (cliente.ativo ? 'Sim' : 'Não') },
    lixeira
        ? { chave: 'excluido_em', rotulo: 'Excluído em', nivel: 'secundaria', texto: (cliente) => (cliente.deleted_at ? formatar_data_hora(cliente.deleted_at) : '—') }
        : { chave: 'criado_em', rotulo: 'Criado em', nivel: 'secundaria', texto: (cliente) => formatar_data_hora(cliente.created_at), ordem: 'created_at' },
];

// lista de clientes do painel (spec 019, FR-005 e FR-029), no padrão da lista de apostas: ID e nome na
// primeira coluna, saldo líquido colorido, tocar na linha abre o detalhe, três pontinhos com as ações
// e o botão de expandir com todas as informações. acoes(cliente) devolve os itens do menu; ordenacao
// e ao_ordenar só na aba Cadastros
export default function ClientsTable({ linhas, lixeira = false, carregando, erro, ao_tentar_novamente, ao_abrir, acoes, ordenacao = null, ao_ordenar = null }) {
    const [abertos, definir_abertos] = useState([]);
    const e_mobile = useTelaMobile();
    const colunas = colunas_da_lista(lixeira);
    const colunas_visiveis = (e_mobile ? colunas.filter((coluna) => coluna.nivel === 'principal') : colunas).length + 2;

    const alternar = (id) => definir_abertos((atuais) => (atuais.includes(id) ? atuais.filter((item) => item !== id) : [...atuais, id]));

    // título que ordena: a mesma coluna inverte a direção; outra começa do maior/mais novo
    const titulo = (rotulo, ordem) => {
        if (!ordem || !ao_ordenar) return rotulo;

        const ativa = ordenacao?.ordenar_por === ordem;
        const proxima = ativa && ordenacao.direcao === 'desc' ? 'asc' : 'desc';

        return (
            <Sort type="button" $ativa={ativa} onClick={() => ao_ordenar({ ordenar_por: ordem, direcao: ordem === 'nome' && !ativa ? 'asc' : proxima })}>
                {rotulo}
                {ativa && <i className="material-icons" aria-hidden="true">{ordenacao.direcao === 'desc' ? 'arrow_downward' : 'arrow_upward'}</i>}
            </Sort>
        );
    };

    if (carregando && !linhas.length) {
        return <Skeleton>{Array.from({ length: 6 }, (_, indice) => <span key={indice} />)}</Skeleton>;
    }

    if (erro && !linhas.length) {
        return (
            <Empty>
                {erro.response?.data?.message ?? 'Não foi possível carregar a lista de clientes.'}
                <Retry type="button" onClick={ao_tentar_novamente}>Tentar novamente</Retry>
            </Empty>
        );
    }

    if (!linhas.length) {
        return (
            <Empty>
                <i className="material-icons" aria-hidden="true">{lixeira ? 'delete_outline' : 'person_off'}</i>
                {lixeira ? 'Nenhum cliente excluído encontrado.' : 'Nenhum cliente encontrado. Troque a busca ou os filtros.'}
            </Empty>
        );
    }

    return (
        <Scroll $atualizando={carregando}>
            <Table>
                <thead>
                    <Row>
                        <HeadCell $codigo>{titulo('Cliente', 'nome')}</HeadCell>
                        {colunas.map((coluna) => (
                            <HeadCell key={coluna.chave} $secundaria={coluna.nivel === 'secundaria'} title={coluna.dica ?? coluna.rotulo}>
                                {titulo(coluna.rotulo, coluna.ordem)}
                            </HeadCell>
                        ))}
                        <HeadCell $acoes><span>Ação</span></HeadCell>
                    </Row>
                </thead>
                <tbody>
                    {linhas.map((cliente) => {
                        const aberto = abertos.includes(cliente.id);

                        return (
                            <Fragment key={cliente.id}>
                                {/* tocar na linha (fora dos botões) abre o detalhe */}
                                <Row $aberto={aberto} onClick={(evento) => !evento.target.closest('button') && ao_abrir(cliente)}>
                                    <Cell>
                                        <Code title={`Ver o cliente ${cliente.nome}`}>{cliente.nome}</Code>
                                        <Sub>
                                            <span>{`ID ${cliente.id}`}</span>
                                            {!cliente.ativo && <Tag>inativo</Tag>}
                                        </Sub>
                                    </Cell>
                                    {colunas.map((coluna) => (
                                        <Cell key={coluna.chave} $secundaria={coluna.nivel === 'secundaria'}>
                                            {coluna.dinheiro_sinal
                                                ? <Money $sinal={Math.sign(Number(cliente.saldo_liquido ?? 0))}>{formatar_valor(cliente.saldo_liquido ?? '0')}</Money>
                                                : coluna.texto(cliente)}
                                        </Cell>
                                    ))}
                                    <Cell>
                                        <Actions>
                                            <ActionsMenu acoes={acoes(cliente)} rotulo={`Ações do cliente ${cliente.nome}`} />
                                            <Expand
                                                type="button"
                                                title={aberto ? 'Ver menos' : 'Ver todas as informações'}
                                                aria-label={aberto ? `Ver menos do cliente ${cliente.nome}` : `Ver todas as informações do cliente ${cliente.nome}`}
                                                aria-expanded={aberto}
                                                onClick={() => alternar(cliente.id)}
                                            >
                                                <i className="material-icons" aria-hidden="true">{aberto ? 'expand_less' : 'expand_more'}</i>
                                            </Expand>
                                        </Actions>
                                    </Cell>
                                </Row>
                                {aberto && (
                                    <DetailsRow>
                                        <td colSpan={colunas_visiveis}>
                                            <Details>
                                                {colunas.filter((coluna) => coluna.nivel === 'secundaria').map((coluna) => (
                                                    <Detail key={coluna.chave} $so_celular>
                                                        <span>{coluna.rotulo}</span>
                                                        <span>{coluna.texto(cliente)}</span>
                                                    </Detail>
                                                ))}
                                                <Detail>
                                                    <span>Telefone</span>
                                                    <span>{telefone(cliente)}</span>
                                                </Detail>
                                                <Detail>
                                                    <span>E-mail</span>
                                                    <span>{cliente.email ?? '—'}</span>
                                                </Detail>
                                                <Detail>
                                                    <span>CPF</span>
                                                    <span>{cliente.cpf ?? '—'}</span>
                                                </Detail>
                                                <Detail>
                                                    <span>Afiliado</span>
                                                    <span>{cliente.afiliado?.nome ?? '—'}</span>
                                                </Detail>
                                            </Details>
                                        </td>
                                    </DetailsRow>
                                )}
                            </Fragment>
                        );
                    })}
                </tbody>
            </Table>
        </Scroll>
    );
}
