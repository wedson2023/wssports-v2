import { useEffect, useState } from 'react';

import Backdrop from '../Backdrop';
import ModalHeader from '../ModalHeader';
import Spinner from '../Spinner';
import StatCard from '../StatCard';
import { texto_afiliado, texto_ganho, texto_situacao } from '../PromotionsTable';
import useVoltarFecha from '../../hooks/useVoltarFecha';
import api from '../../utils/api';
import { alerta_erro } from '../../utils/alerts';
import { formatar_valor } from '../../utils/charts';
import { formatar_data_hora } from '../../utils/dates';
import { Actions, Body, Button, Cards, Container, Data, DataItem, Loading, Progress, Title } from './styles';

// intervalo da consulta do progresso do estorno geral
const INTERVALO_ESTORNO = 3000;

const data_hora = (texto) => (texto ? formatar_data_hora(texto) : '—');

const sim_nao = (valor) => (valor ? 'Sim' : 'Não');

// detalhe da promoção (spec 020, FR-011, contracts/paginas §5): gaveta à direita (tela cheia no
// celular) com os campos agrupados como no cadastro, os números de uso e o estorno com o progresso;
// versao muda quando uma ação de fora altera a promoção
export default function PromotionDrawer({ promocao, versao, token, acoes, ao_fechar }) {
    const aberto = Boolean(promocao);
    const [detalhe, definir_detalhe] = useState(null);

    useVoltarFecha(aberto, ao_fechar);

    useEffect(() => {
        if (!aberto) return undefined;

        let ativo = true;
        api.get(`/clientes-promocoes/${promocao.id}`, { headers: { Authorization: `Bearer ${token}` } })
            .then(({ data }) => ativo && definir_detalhe(data.data))
            .catch((erro) => {
                if (!ativo) return;

                alerta_erro(erro);
                ao_fechar();
            });

        return () => {
            ativo = false;
        };
    }, [promocao?.id, versao]);

    useEffect(() => {
        if (!aberto) return undefined;

        const ao_teclar = (evento) => { if (evento.key === 'Escape') ao_fechar(); };
        window.addEventListener('keydown', ao_teclar);

        return () => window.removeEventListener('keydown', ao_teclar);
    }, [aberto, ao_fechar]);

    const dados = detalhe && detalhe.id === promocao?.id ? detalhe : null;
    const estorno = dados?.estorno;
    const estornando = aberto && estorno?.situacao === 'Em andamento';

    // progresso do estorno geral, até concluir (R-11)
    useEffect(() => {
        if (!estornando) return undefined;

        const intervalo = window.setInterval(() => {
            api.get(`/clientes-promocoes/${promocao.id}`, { headers: { Authorization: `Bearer ${token}` } })
                .then(({ data }) => definir_detalhe(data.data))
                .catch(() => {});
        }, INTERVALO_ESTORNO);

        return () => window.clearInterval(intervalo);
    }, [estornando, promocao?.id]);

    const linha = (rotulo, valor) => (
        <DataItem key={rotulo}>
            <span>{rotulo}</span>
            <span>{valor ?? '—'}</span>
        </DataItem>
    );

    const de_deposito = ['Primeiro depósito', 'Qualquer depósito'].includes(dados?.categoria);

    return (
        <>
            <Backdrop visivel={aberto} ao_fechar={ao_fechar} camada={45} />
            <Container $visivel={aberto} role="dialog" aria-modal="true" aria-label={promocao ? `Promoção ${promocao.nome}` : 'Promoção'}>
                {aberto && (
                    <>
                        <ModalHeader icone="card_giftcard" titulo={promocao.nome} descricao="Regras, números de uso e estorno da promoção." ao_fechar={ao_fechar} />
                        <Body>
                            {!dados && <Loading><Spinner tamanho={30} cor="currentColor" /></Loading>}
                            {dados && (
                                <>
                                    {dados.uso && (
                                        <Cards>
                                            <StatCard icone="group" rotulo="Clientes" valor={dados.uso.clientes} dinheiro={false} titulo="Clientes que receberam o bônus." />
                                            <StatCard icone="redeem" rotulo="Creditado" valor={dados.uso.creditado} titulo="Soma dos bônus dados." />
                                            <StatCard icone="task_alt" rotulo="Cumpridos" valor={dados.uso.cumpridos} dinheiro={false} titulo="Bônus que cumpriram o rollover." />
                                            <StatCard icone="currency_exchange" rotulo="Convertido" valor={dados.uso.convertido} titulo="O que passou para o saldo real." />
                                            <StatCard icone="remove_circle_outline" rotulo="Retirado" valor={dados.uso.retirado} titulo="O que saiu por expiração, exclusão pelo cliente e estorno." />
                                        </Cards>
                                    )}

                                    <Title>Identificação</Title>
                                    <Data>
                                        {linha('ID', dados.id)}
                                        {linha('Situação', texto_situacao(dados))}
                                        {linha('Descrição', dados.descricao)}
                                        {linha('Afiliado', texto_afiliado(dados))}
                                    </Data>

                                    <Title>Ganho</Title>
                                    <Data>
                                        {linha('Categoria', dados.categoria)}
                                        {linha('Modalidade', dados.modalidade)}
                                        {linha('Ganho', texto_ganho(dados))}
                                        {dados.categoria === 'Indicação' && linha('Indicações exigidas', dados.quantidade_indicacoes)}
                                    </Data>

                                    <Title>Período</Title>
                                    <Data>
                                        {linha('Início', data_hora(dados.data_inicio))}
                                        {linha('Fim', dados.data_fim ? data_hora(dados.data_fim) : 'Sem fim')}
                                    </Data>

                                    <Title>Rollover e prazo</Title>
                                    <Data>
                                        {linha('Rollover', `${dados.rollover}x`)}
                                        {linha('Dias para cumprir', dados.dias_cumprir)}
                                        {de_deposito && linha('Rollover no primeiro depósito', sim_nao(dados.rollover_primeiro_deposito))}
                                    </Data>

                                    <Title>Regras de aposta</Title>
                                    <Data>
                                        {linha('Aposta mínima', formatar_valor(dados.valor_minimo_aposta))}
                                        {linha('Aposta máxima', formatar_valor(dados.valor_maximo_aposta))}
                                        {linha('Odd mínima simples', dados.odd_minima_aposta_simples)}
                                        {linha('Odd mínima múltipla', dados.odd_minima_aposta_multipla)}
                                        {linha('Conversão máxima', formatar_valor(dados.valor_maximo_conversao))}
                                    </Data>

                                    {estorno?.situacao && (
                                        <>
                                            <Title>Estorno</Title>
                                            {estorno.situacao === 'Em andamento' && (
                                                <Progress
                                                    value={estorno.clientes_processados ?? 0}
                                                    max={Math.max(estorno.total_clientes ?? 0, 1)}
                                                    aria-label={`Estornando ${estorno.clientes_processados ?? 0} de ${estorno.total_clientes ?? 0} clientes`}
                                                />
                                            )}
                                            <Data>
                                                {linha('Situação', estorno.situacao === 'Em andamento' ? `Estornando ${estorno.clientes_processados ?? 0} de ${estorno.total_clientes ?? 0} clientes` : 'Estornada')}
                                                {linha('Valor retirado', formatar_valor(estorno.valor_total ?? '0'))}
                                                {linha('Motivo', estorno.motivo)}
                                                {linha('Autor', estorno.autor?.nome)}
                                                {linha('Início', data_hora(estorno.iniciado_em))}
                                                {linha('Conclusão', data_hora(estorno.concluido_em))}
                                            </Data>
                                        </>
                                    )}

                                    <Data>
                                        {linha('Cadastro', data_hora(dados.created_at))}
                                        {linha('Alterado em', data_hora(dados.updated_at))}
                                    </Data>

                                    <Actions>
                                        {acoes(dados).map((acao) => (
                                            <Button key={acao.chave} type="button" $tom={acao.tom} onClick={acao.ao_clicar}>
                                                <i className="material-icons" aria-hidden="true">{acao.icone}</i>
                                                {acao.titulo}
                                            </Button>
                                        ))}
                                    </Actions>
                                </>
                            )}
                        </Body>
                    </>
                )}
            </Container>
        </>
    );
}
