import { Fragment, useState } from 'react';

import ActionsMenu from '../ActionsMenu';
import useTelaMobile from '../../hooks/useTelaMobile';
import { formatar_valor } from '../../utils/charts';
import {
    Actions, Bar, Cell, Detail, Details, DetailsRow, Empty, Expand, HeadCell, Login, More, Name, NameButton, Order, Retry, Row, Scroll, Search, SearchBox,
    Skeleton, Tag, Table, Value,
} from './styles';

// sinal do valor: positivo, negativo ou zero (verde, vermelho ou cinza)
const sinal = (valor) => {
    const numero = Number(valor ?? 0);

    if (numero > 0) return 'positivo';
    if (numero < 0) return 'negativo';

    return 'zero';
};

// texto de uma célula conforme o tipo da coluna
const texto_celula = (coluna, linha) => {
    const valor = linha[coluna.chave];

    if (coluna.tipo === 'limites') {
        return valor ? `${formatar_valor(valor.geral)} / ${formatar_valor(valor.duplo)} / ${formatar_valor(valor.simples)}` : '—';
    }

    if (valor === null || valor === undefined) return '—';
    if (coluna.tipo === 'dinheiro') return formatar_valor(valor);
    if (coluna.tipo === 'percentual') return `${String(Number(valor)).replace('.', ',')}%`;

    return String(valor);
};

const situacoes = { inativo: 'inativo', excluido: 'excluído' };

// lista do caixa (spec 017, contracts/paginas §3.4 e §5), no padrão da lista de usuários do painel:
// no celular só as colunas principais (nome com o login embaixo, as colunas em destaque e as ações);
// no computador também as secundárias; o botão de expandir de cada linha mostra todas as informações.
// colunas = [{ chave, rotulo, tipo: 'dinheiro'|'numero'|'percentual'|'limites'|'texto', cor, nivel:
// 'principal'|'secundaria'|'detalhe', dica }]; o nome abre o nível abaixo quando ao_abrir vem;
// acoes(linha) devolve os itens do menu de três pontinhos
export default function CashTable({
    linhas, colunas, carregando, erro, ao_tentar_novamente, ao_abrir = null, acoes = null, subtitulo = (linha) => linha.login,
    busca, ao_buscar, ordenacao, opcoes_ordenacao, ao_ordenar, placeholder_busca = 'Buscar por nome ou login',
}) {
    const [abertos, definir_abertos] = useState([]);
    const e_mobile = useTelaMobile();
    const na_tabela = colunas.filter((coluna) => coluna.nivel !== 'detalhe');
    // a linha de detalhes ocupa só as colunas visíveis: contar as escondidas no celular criaria
    // colunas fantasmas e espremeria os valores
    const colunas_visiveis = (e_mobile ? na_tabela.filter((coluna) => coluna.nivel === 'principal') : na_tabela).length + 2;
    // nos detalhes: no computador só as colunas "detalhe"; no celular também as secundárias
    const nos_detalhes = colunas.filter((coluna) => coluna.nivel !== 'principal');

    const alternar = (id) => definir_abertos((atuais) => (atuais.includes(id) ? atuais.filter((item) => item !== id) : [...atuais, id]));

    const conteudo = () => {
        if (carregando && !linhas.length) {
            return <Skeleton>{Array.from({ length: 5 }, (_, indice) => <span key={indice} />)}</Skeleton>;
        }

        if (erro && !linhas.length) {
            return (
                <Empty>
                    {erro.response?.data?.message ?? 'Não foi possível carregar a lista.'}
                    <Retry type="button" onClick={ao_tentar_novamente}>Tentar novamente</Retry>
                </Empty>
            );
        }

        if (!linhas.length) {
            return (
                <Empty>
                    <i className="material-icons" aria-hidden="true">event_busy</i>
                    Nenhum movimento neste período. Experimente outro período.
                </Empty>
            );
        }

        return (
            <Scroll $atualizando={carregando}>
                <Table>
                    <thead>
                        <Row>
                            <HeadCell $nome>Nome</HeadCell>
                            {na_tabela.map((coluna) => (
                                <HeadCell key={coluna.chave} $secundaria={coluna.nivel === 'secundaria'} title={coluna.dica ?? coluna.rotulo}>{coluna.rotulo}</HeadCell>
                            ))}
                            <HeadCell $acoes $com_menu={Boolean(acoes)}><span>Ação</span></HeadCell>
                        </Row>
                    </thead>
                    <tbody>
                        {linhas.map((linha) => {
                            const aberto = abertos.includes(linha.id);
                            const acoes_linha = acoes ? acoes(linha) : [];

                            return (
                                <Fragment key={linha.id}>
                                    {/* tocar na linha (fora dos botões) também expande: no celular é o jeito principal */}
                                    <Row $aberto={aberto} onClick={(evento) => !evento.target.closest('button') && alternar(linha.id)}>
                                        <Cell>
                                            {ao_abrir ? (
                                                <NameButton type="button" title={`Abrir ${linha.nome}`} onClick={() => ao_abrir(linha)}>
                                                    <span>{linha.nome}</span>
                                                    <i className="material-icons" aria-hidden="true">chevron_right</i>
                                                </NameButton>
                                            ) : <Name title={linha.nome}>{linha.nome}</Name>}
                                            <Login title={subtitulo(linha)}>
                                                <span>{subtitulo(linha)}</span>
                                                {situacoes[linha.situacao] && <Tag>{situacoes[linha.situacao]}</Tag>}
                                            </Login>
                                        </Cell>
                                        {na_tabela.map((coluna) => (
                                            <Cell key={coluna.chave} $secundaria={coluna.nivel === 'secundaria'}>
                                                <Value $sinal={coluna.cor ? sinal(linha[coluna.chave]) : null} $forte={coluna.nivel === 'principal' && coluna.cor}>
                                                    {texto_celula(coluna, linha)}
                                                </Value>
                                            </Cell>
                                        ))}
                                        <Cell>
                                            <Actions>
                                                {acoes_linha.length > 0 && <ActionsMenu acoes={acoes_linha} rotulo={`Ações de ${linha.nome}`} />}
                                                <Expand
                                                    type="button"
                                                    title={aberto ? 'Ver menos' : 'Ver todas as informações'}
                                                    aria-label={aberto ? `Ver menos de ${linha.nome}` : `Ver todas as informações de ${linha.nome}`}
                                                    aria-expanded={aberto}
                                                    onClick={() => alternar(linha.id)}
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
                                                    {nos_detalhes.map((coluna) => (
                                                        <Detail key={coluna.chave} $so_celular={coluna.nivel === 'secundaria'}>
                                                            <span>{coluna.rotulo}</span>
                                                            <Value $sinal={coluna.cor ? sinal(linha[coluna.chave]) : null}>{texto_celula(coluna, linha)}</Value>
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
    };

    return (
        <>
            {ao_buscar && (
                <Bar>
                    <SearchBox>
                        <i className="material-icons" aria-hidden="true">search</i>
                        <Search type="search" value={busca} placeholder={placeholder_busca} aria-label={placeholder_busca} onChange={(evento) => ao_buscar(evento.target.value)} />
                    </SearchBox>
                    {opcoes_ordenacao && (
                        <Order>
                            <select aria-label="Ordenar por" value={ordenacao.campo} onChange={(evento) => ao_ordenar({ ...ordenacao, campo: evento.target.value })}>
                                {opcoes_ordenacao.map((opcao) => <option key={opcao.chave} value={opcao.chave}>{opcao.rotulo}</option>)}
                            </select>
                            <More
                                type="button"
                                title={ordenacao.direcao === 'asc' ? 'Crescente' : 'Decrescente'}
                                aria-label={ordenacao.direcao === 'asc' ? 'Ordem crescente' : 'Ordem decrescente'}
                                onClick={() => ao_ordenar({ ...ordenacao, direcao: ordenacao.direcao === 'asc' ? 'desc' : 'asc' })}
                            >
                                <i className="material-icons" aria-hidden="true">{ordenacao.direcao === 'asc' ? 'arrow_upward' : 'arrow_downward'}</i>
                            </More>
                        </Order>
                    )}
                </Bar>
            )}
            {conteudo()}
        </>
    );
}
