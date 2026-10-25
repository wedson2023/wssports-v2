import { Fragment, useState } from 'react';

import ActionsMenu from '../ActionsMenu';
import useTelaMobile from '../../hooks/useTelaMobile';
import { formatar_valor } from '../../utils/charts';
import { formatar_data_hora } from '../../utils/dates';
import {
    Actions, Cell, Code, Detail, Details, DetailsRow, Empty, Expand, HeadCell, Retry, Row, Scroll, Skeleton, Status, Sub, Table,
} from './styles';

// ganho: valor fixo em reais ou percentual (com o depósito máximo)
export const texto_ganho = (promocao) => {
    if (promocao.tipo_ganho !== 'Percentual') return formatar_valor(promocao.valor);

    const percentual = `${String(Number(promocao.valor)).replace('.', ',')}%`;

    return promocao.valor_maximo_deposito ? `${percentual} até ${formatar_valor(promocao.valor_maximo_deposito)}` : percentual;
};

export const texto_afiliado = (promocao) => {
    if (!promocao.afiliado) return 'Geral';

    const nome = `${promocao.afiliado.nome} (${promocao.afiliado.codigo})`;

    return promocao.afiliado.excluido ? `${nome} · excluído` : nome;
};

export const texto_periodo = (promocao) => `${formatar_data_hora(promocao.data_inicio)} — ${promocao.data_fim ? formatar_data_hora(promocao.data_fim) : 'Sem fim'}`;

// "Estornando 3 de 10"
export const texto_situacao = (promocao) => (promocao.situacao === 'Estornando'
    ? `Estornando ${promocao.estorno.clientes_processados ?? 0} de ${promocao.estorno.total_clientes ?? 0}`
    : promocao.situacao);

const uso = (promocao) => (promocao.uso ? `${promocao.uso.clientes} · ${formatar_valor(promocao.uso.creditado)} · ${formatar_valor(promocao.uso.convertido)}` : '—');

// colunas (contracts/paginas §2): principal = sempre na linha; secundaria = só no computador
const colunas = [
    { chave: 'modalidade', rotulo: 'Modalidade', nivel: 'secundaria', texto: (promocao) => promocao.modalidade },
    { chave: 'ganho', rotulo: 'Ganho', nivel: 'principal', texto: texto_ganho, forte: true },
    { chave: 'rollover', rotulo: 'Rollover', nivel: 'secundaria', texto: (promocao) => `${promocao.rollover}x · ${promocao.dias_cumprir} dias` },
    { chave: 'afiliado', rotulo: 'Afiliado', nivel: 'secundaria', texto: texto_afiliado },
    { chave: 'periodo', rotulo: 'Período', nivel: 'secundaria', texto: texto_periodo },
    { chave: 'uso', rotulo: 'Uso (clientes · creditado · convertido)', nivel: 'secundaria', texto: uso },
];

// promoções da tela Bônus (spec 020, FR-004 a FR-010), no padrão das listas do painel: nome e
// categoria na primeira coluna, situação em selo colorido e o menu de ações; tocar na linha abre o
// detalhe; acoes(promocao) devolve os itens do menu
export default function PromotionsTable({ linhas, carregando, erro, ao_tentar_novamente, ao_abrir, acoes }) {
    const [abertos, definir_abertos] = useState([]);
    const e_mobile = useTelaMobile();
    const colunas_visiveis = (e_mobile ? colunas.filter((coluna) => coluna.nivel === 'principal') : colunas).length + 3;

    const alternar = (id) => definir_abertos((atuais) => (atuais.includes(id) ? atuais.filter((item) => item !== id) : [...atuais, id]));

    if (carregando && !linhas.length) {
        return <Skeleton>{Array.from({ length: 6 }, (_, indice) => <span key={indice} />)}</Skeleton>;
    }

    if (erro && !linhas.length) {
        return (
            <Empty>
                {erro.response?.data?.message ?? 'Não foi possível carregar as promoções.'}
                <Retry type="button" onClick={ao_tentar_novamente}>Tentar novamente</Retry>
            </Empty>
        );
    }

    if (!linhas.length) {
        return (
            <Empty>
                <i className="material-icons" aria-hidden="true">card_giftcard</i>
                Nenhuma promoção encontrada. Troque a busca ou os filtros.
            </Empty>
        );
    }

    return (
        <Scroll $atualizando={carregando}>
            <Table>
                <thead>
                    <Row>
                        <HeadCell $codigo>Promoção</HeadCell>
                        {colunas.map((coluna) => (
                            <HeadCell key={coluna.chave} $secundaria={coluna.nivel === 'secundaria'}>{coluna.rotulo}</HeadCell>
                        ))}
                        <HeadCell>Situação</HeadCell>
                        <HeadCell $acoes><span>Ação</span></HeadCell>
                    </Row>
                </thead>
                <tbody>
                    {linhas.map((promocao) => {
                        const aberto = abertos.includes(promocao.id);
                        const itens = acoes(promocao);

                        return (
                            <Fragment key={promocao.id}>
                                <Row $aberto={aberto} onClick={(evento) => !evento.target.closest('button') && ao_abrir(promocao)}>
                                    <Cell>
                                        <Code>{promocao.nome}</Code>
                                        <Sub><span>{`ID ${promocao.id} · ${promocao.categoria}`}</span></Sub>
                                    </Cell>
                                    {colunas.map((coluna) => (
                                        <Cell key={coluna.chave} $secundaria={coluna.nivel === 'secundaria'} $forte={coluna.forte}>{coluna.texto(promocao)}</Cell>
                                    ))}
                                    <Cell><Status $situacao={promocao.situacao}>{texto_situacao(promocao)}</Status></Cell>
                                    <Cell>
                                        <Actions>
                                            {itens.length > 0 && <ActionsMenu acoes={itens} rotulo={`Ações da promoção ${promocao.nome}`} />}
                                            {e_mobile && (
                                                <Expand
                                                    type="button"
                                                    title={aberto ? 'Ver menos' : 'Ver todas as informações'}
                                                    aria-label={aberto ? 'Ver menos da promoção' : 'Ver todas as informações da promoção'}
                                                    aria-expanded={aberto}
                                                    onClick={() => alternar(promocao.id)}
                                                >
                                                    <i className="material-icons" aria-hidden="true">{aberto ? 'expand_less' : 'expand_more'}</i>
                                                </Expand>
                                            )}
                                        </Actions>
                                    </Cell>
                                </Row>
                                {aberto && e_mobile && (
                                    <DetailsRow>
                                        <td colSpan={colunas_visiveis}>
                                            <Details>
                                                {colunas.filter((coluna) => coluna.nivel === 'secundaria').map((coluna) => (
                                                    <Detail key={coluna.chave} $so_celular>
                                                        <span>{coluna.rotulo}</span>
                                                        <span>{coluna.texto(promocao)}</span>
                                                    </Detail>
                                                ))}
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
