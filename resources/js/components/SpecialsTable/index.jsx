import { Fragment, useState } from 'react';

import ActionsMenu from '../ActionsMenu';
import useTelaMobile from '../../hooks/useTelaMobile';
import { formatar_valor } from '../../utils/charts';
import { formatar_data_hora } from '../../utils/dates';
import {
    Actions, Cell, Code, Detail, Details, DetailsRow, Empty, Expand, HeadCell, Late, Retry, Row, Scroll, Skeleton, Status, Sub, Table,
} from './styles';

const data_hora = (texto) => (texto ? formatar_data_hora(texto) : '—');

// rótulo de cada situação da categoria e da opção, com a mesma cor no selo
export const rotulos_situacao = {
    Aguardando: 'Aguardando',
    Encerrado: 'Encerrado',
    Cancelado: 'Cancelado',
};

// vencedoras na linha: "Time A, Time B (empatada)"
export const texto_vencedoras = (especial) => (especial.vencedoras?.length
    ? especial.vencedoras.map((opcao) => (opcao.empatada ? `${opcao.nome} (empatada)` : opcao.nome)).join(', ')
    : '—');

// selo da situação; "Aguardando resultado" em âmbar quando a data limite já passou (FR-004)
const situacao = (especial) => (
    <>
        <Status $situacao={especial.situacao}>{rotulos_situacao[especial.situacao] ?? especial.situacao}</Status>
        {especial.aguardando_resultado && <Late>Aguardando resultado</Late>}
    </>
);

// colunas de cada aba (contracts/paginas §1): principal = sempre na linha; secundaria = só no
// computador (no celular, ao expandir)
const colunas_especiais = [
    { chave: 'opcoes', rotulo: 'Opções', nivel: 'secundaria', texto: (especial) => especial.quantidade_opcoes ?? '—' },
    { chave: 'data_limite', rotulo: 'Data limite', nivel: 'secundaria', texto: (especial) => data_hora(especial.data_limite) },
    { chave: 'situacao', rotulo: 'Situação', nivel: 'principal', texto: situacao },
    { chave: 'ativo', rotulo: 'Ativo', nivel: 'secundaria', texto: (especial) => (especial.ativo ? 'Sim' : 'Não') },
    { chave: 'vencedoras', rotulo: 'Vencedoras', nivel: 'secundaria', texto: texto_vencedoras },
    { chave: 'apostas', rotulo: 'Apostas em aberto', nivel: 'secundaria', texto: (especial) => (especial.apostas_em_aberto ?? 0).toLocaleString('pt-BR') },
    { chave: 'valor', rotulo: 'Valor em aberto', nivel: 'principal', forte: true, texto: (especial) => formatar_valor(especial.valor_em_aberto) },
];

const colunas_lixeira = [
    { chave: 'opcoes', rotulo: 'Opções', nivel: 'secundaria', texto: (especial) => especial.quantidade_opcoes ?? '—' },
    { chave: 'data_limite', rotulo: 'Data limite', nivel: 'secundaria', texto: (especial) => data_hora(especial.data_limite) },
    { chave: 'situacao', rotulo: 'Situação', nivel: 'principal', texto: situacao },
    { chave: 'removida', rotulo: 'Removida em', nivel: 'principal', texto: (especial) => data_hora(especial.deleted_at) },
];

// categorias da tela Especiais (spec 024, FR-004 e FR-029), no padrão das listas do painel: nome e
// ID na primeira coluna, situação em selo e o menu de ações; tocar na linha abre a gaveta; no
// celular, cartões com o restante ao expandir. acoes(especial) devolve os itens do menu
export default function SpecialsTable({ aba, linhas, carregando, erro, ao_tentar_novamente, ao_abrir, acoes }) {
    const [abertos, definir_abertos] = useState([]);
    const e_mobile = useTelaMobile();
    const colunas = aba === 'lixeira' ? colunas_lixeira : colunas_especiais;
    const colunas_visiveis = (e_mobile ? colunas.filter((coluna) => coluna.nivel === 'principal') : colunas).length + 2;

    const alternar = (id) => definir_abertos((atuais) => (atuais.includes(id) ? atuais.filter((item) => item !== id) : [...atuais, id]));

    if (carregando && !linhas.length) {
        return <Skeleton>{Array.from({ length: 6 }, (_, indice) => <span key={indice} />)}</Skeleton>;
    }

    if (erro && !linhas.length) {
        return (
            <Empty>
                {erro.response?.data?.message ?? 'Não foi possível carregar os especiais.'}
                <Retry type="button" onClick={ao_tentar_novamente}>Tentar novamente</Retry>
            </Empty>
        );
    }

    if (!linhas.length) {
        return (
            <Empty>
                <i className="material-icons" aria-hidden="true">emoji_events</i>
                {aba === 'lixeira' ? 'Nenhum especial na lixeira.' : 'Nenhum especial encontrado. Troque a busca ou os filtros.'}
            </Empty>
        );
    }

    return (
        <Scroll $atualizando={carregando}>
            <Table>
                <thead>
                    <Row>
                        <HeadCell $codigo>Especial</HeadCell>
                        {colunas.map((coluna) => (
                            <HeadCell key={coluna.chave} $secundaria={coluna.nivel === 'secundaria'}>{coluna.rotulo}</HeadCell>
                        ))}
                        <HeadCell $acoes><span>Ação</span></HeadCell>
                    </Row>
                </thead>
                <tbody>
                    {linhas.map((especial) => {
                        const aberto = abertos.includes(especial.id);
                        const itens = acoes(especial);

                        return (
                            <Fragment key={especial.id}>
                                <Row $aberto={aberto} onClick={(evento) => !evento.target.closest('button, a') && ao_abrir(especial)}>
                                    <Cell>
                                        <Code>{especial.nome}</Code>
                                        <Sub><span>{`ID ${especial.id}`}</span></Sub>
                                    </Cell>
                                    {colunas.map((coluna) => (
                                        <Cell key={coluna.chave} $secundaria={coluna.nivel === 'secundaria'} $forte={coluna.forte}>{coluna.texto(especial)}</Cell>
                                    ))}
                                    <Cell>
                                        <Actions>
                                            {itens.length > 0 && <ActionsMenu acoes={itens} rotulo={`Ações do especial ${especial.nome}`} />}
                                            {e_mobile && (
                                                <Expand
                                                    type="button"
                                                    title={aberto ? 'Ver menos' : 'Ver todas as informações'}
                                                    aria-label={aberto ? 'Ver menos do especial' : 'Ver todas as informações do especial'}
                                                    aria-expanded={aberto}
                                                    onClick={() => alternar(especial.id)}
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
                                                        <span>{coluna.texto(especial)}</span>
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
