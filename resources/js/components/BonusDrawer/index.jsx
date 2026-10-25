import { useEffect, useState } from 'react';

import Backdrop from '../Backdrop';
import ModalHeader from '../ModalHeader';
import Spinner from '../Spinner';
import useVoltarFecha from '../../hooks/useVoltarFecha';
import api from '../../utils/api';
import { alerta_erro } from '../../utils/alerts';
import { formatar_valor } from '../../utils/charts';
import { formatar_data_hora } from '../../utils/dates';
import {
    Actions, Body, Button, Container, Data, DataItem, Event, Loading, Timeline, Title,
} from './styles';

const data_hora = (texto) => (texto ? formatar_data_hora(texto) : '—');

// detalhe do bônus recebido (spec 020, FR-025, contracts/paginas §8): gaveta à direita (tela cheia no
// celular) com os dados, as regras de uso gravadas no recebimento e a linha do tempo; versao muda
// quando uma ação de fora altera o bônus
export default function BonusDrawer({ bonus, versao, token, acoes, ao_fechar }) {
    const aberto = Boolean(bonus);
    const [detalhe, definir_detalhe] = useState(null);

    useVoltarFecha(aberto, ao_fechar);

    useEffect(() => {
        if (!aberto) return undefined;

        let ativo = true;
        api.get(`/clientes-bonus/${bonus.id}`, { headers: { Authorization: `Bearer ${token}` } })
            .then(({ data }) => ativo && definir_detalhe(data.data))
            .catch((erro) => {
                if (!ativo) return;

                alerta_erro(erro);
                ao_fechar();
            });

        return () => {
            ativo = false;
        };
    }, [bonus?.id, versao]);

    useEffect(() => {
        if (!aberto) return undefined;

        const ao_teclar = (evento) => { if (evento.key === 'Escape') ao_fechar(); };
        window.addEventListener('keydown', ao_teclar);

        return () => window.removeEventListener('keydown', ao_teclar);
    }, [aberto, ao_fechar]);

    const dados = detalhe && detalhe.id === bonus?.id ? detalhe : null;

    const linha = (rotulo, valor) => (
        <DataItem key={rotulo}>
            <span>{rotulo}</span>
            <span>{valor ?? '—'}</span>
        </DataItem>
    );

    return (
        <>
            <Backdrop visivel={aberto} ao_fechar={ao_fechar} camada={45} />
            <Container $visivel={aberto} role="dialog" aria-modal="true" aria-label={bonus ? `Bônus de ${bonus.cliente.nome}` : 'Bônus'}>
                {aberto && (
                    <>
                        <ModalHeader icone="redeem" titulo={`Bônus de ${bonus.cliente.nome}`} descricao="Progresso, regras de uso e histórico do bônus." ao_fechar={ao_fechar} />
                        <Body>
                            {!dados && <Loading><Spinner tamanho={30} cor="currentColor" /></Loading>}
                            {dados && (
                                <>
                                    <Data>
                                        {linha('Cliente', `${dados.cliente.nome} (ID ${dados.cliente.id})`)}
                                        {linha('Situação', dados.situacao)}
                                        {linha('Promoção', dados.promocao.nome)}
                                        {linha('Afiliado da promoção', dados.promocao.afiliado ?? 'Geral')}
                                        {linha('Modalidade', dados.modalidade)}
                                        {linha('Valor recebido', formatar_valor(dados.valor))}
                                        {linha('Saldo de bônus atual', formatar_valor(dados.saldo_promocional))}
                                        {linha('Recebido em', data_hora(dados.recebido_em))}
                                        {linha('Expira em', data_hora(dados.expira_em))}
                                        {dados.rollover && linha('Rollover', `${dados.rollover.vezes}x · ${formatar_valor(dados.rollover.apostado)} de ${formatar_valor(dados.rollover.exigido)}`)}
                                        {dados.rollover && linha('Falta apostar', formatar_valor(dados.rollover.falta))}
                                    </Data>

                                    {dados.regras && (
                                        <>
                                            <Title>Regras de uso</Title>
                                            <Data>
                                                {linha('Aposta mínima', dados.regras.valor_minimo_aposta ? formatar_valor(dados.regras.valor_minimo_aposta) : null)}
                                                {linha('Aposta máxima', dados.regras.valor_maximo_aposta ? formatar_valor(dados.regras.valor_maximo_aposta) : null)}
                                                {linha('Odd mínima simples', dados.regras.odd_minima_aposta_simples)}
                                                {linha('Odd mínima múltipla', dados.regras.odd_minima_aposta_multipla)}
                                            </Data>
                                        </>
                                    )}

                                    <Title>Histórico</Title>
                                    <Timeline>
                                        {dados.historico.map((evento) => (
                                            <Event key={`${evento.evento}-${evento.em}`}>
                                                <strong>{evento.evento}</strong>
                                                <span>{data_hora(evento.em)}</span>
                                                {evento.evento === 'Estornado' && <span>{`Retirado: ${evento.valor ? formatar_valor(evento.valor) : '—'}`}</span>}
                                                {evento.evento !== 'Estornado' && evento.valor && <span>{formatar_valor(evento.valor)}</span>}
                                                {evento.autor && <span>{`Por ${evento.autor}`}</span>}
                                                {evento.observacao && <span>{evento.observacao}</span>}
                                            </Event>
                                        ))}
                                    </Timeline>

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
