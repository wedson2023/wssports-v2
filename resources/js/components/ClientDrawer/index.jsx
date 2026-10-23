import { useEffect, useState } from 'react';

import Backdrop from '../Backdrop';
import ClientSettingsForm from '../ClientSettingsForm';
import ClientStatement from '../ClientStatement';
import ModalHeader from '../ModalHeader';
import PanelTabs from '../PanelTabs';
import PaymentMethodsList from '../PaymentMethodsList';
import Spinner from '../Spinner';
import useVoltarFecha from '../../hooks/useVoltarFecha';
import api from '../../utils/api';
import { alerta_erro } from '../../utils/alerts';
import { formatar_valor } from '../../utils/charts';
import { formatar_data_hora } from '../../utils/dates';
import { Actions, Body, Button, Container, Data, DataItem, Loading, Money } from './styles';

const abas = [
    { chave: 'dados', rotulo: 'Dados', icone: 'person' },
    { chave: 'configuracoes', rotulo: 'Configurações', icone: 'tune' },
    { chave: 'pagamentos', rotulo: 'Pagamentos', icone: 'pix' },
    { chave: 'extrato', rotulo: 'Extrato', icone: 'receipt_long' },
];

const data_br = (texto) => (texto ? texto.split('-').reverse().join('/') : '—');

// detalhe do cliente (spec 019, FR-011 a FR-016, contracts/paginas §4): gaveta à direita (tela cheia
// no celular) com os dados e os atalhos das ações, as configurações, as chaves Pix e o extrato, sem
// perder a lista por trás. versao muda quando uma ação de fora altera o cliente
export default function ClientDrawer({ cliente, versao, token, pode, acoes, ao_fechar, ao_alterar }) {
    const aberto = Boolean(cliente);
    const [aba, definir_aba] = useState('dados');
    const [detalhe, definir_detalhe] = useState(null);

    useVoltarFecha(aberto, ao_fechar);

    useEffect(() => {
        if (aberto) definir_aba('dados');
    }, [cliente?.id]);

    useEffect(() => {
        if (!aberto) return undefined;

        let ativo = true;
        api.get(`/clientes/${cliente.id}`, { headers: { Authorization: `Bearer ${token}` } })
            .then(({ data }) => ativo && definir_detalhe(data.data))
            .catch((erro) => {
                if (!ativo) return;

                alerta_erro(erro);
                ao_fechar();
            });

        return () => {
            ativo = false;
        };
    }, [cliente?.id, versao]);

    useEffect(() => {
        if (!aberto) return undefined;

        const ao_teclar = (evento) => { if (evento.key === 'Escape') ao_fechar(); };
        window.addEventListener('keydown', ao_teclar);

        return () => window.removeEventListener('keydown', ao_teclar);
    }, [aberto, ao_fechar]);

    const dados = detalhe && detalhe.id === cliente?.id ? detalhe : null;

    const linha = (rotulo, valor) => (
        <DataItem key={rotulo}>
            <span>{rotulo}</span>
            <span>{valor ?? '—'}</span>
        </DataItem>
    );

    return (
        <>
            <Backdrop visivel={aberto} ao_fechar={ao_fechar} camada={45} />
            <Container $visivel={aberto} role="dialog" aria-modal="true" aria-label={cliente ? `Cliente ${cliente.nome}` : 'Cliente'}>
                {aberto && (
                    <>
                        <ModalHeader icone="person" titulo={cliente.nome} descricao="Dados, configurações, pagamentos e extrato do cliente." ao_fechar={ao_fechar} />
                        <PanelTabs abas={abas} ativa={aba} ao_trocar={definir_aba} />
                        <Body>
                            {!dados && <Loading><Spinner tamanho={30} cor="currentColor" /></Loading>}
                            {dados && aba === 'dados' && (
                                <>
                                    <Data>
                                        {linha('ID', dados.id)}
                                        {linha('Situação', dados.ativo ? 'Ativo' : 'Inativo')}
                                        {linha('Telefone', dados.telefone ? `+${dados.ddi} ${dados.telefone}` : null)}
                                        {linha('E-mail', dados.email)}
                                        {linha('CPF', dados.cpf)}
                                        {linha('Nascimento', data_br(dados.data_nascimento))}
                                        {linha('Gênero', dados.genero)}
                                        {linha('Afiliado', dados.afiliado ? `${dados.afiliado.nome} (${dados.afiliado.codigo})` : null)}
                                        {linha('Saldo', formatar_valor(dados.saldo))}
                                        {linha('Promoção esportes', formatar_valor(dados.saldo_promocao_esportes))}
                                        {linha('Promoção cassino', formatar_valor(dados.saldo_promocao_cassino))}
                                        <DataItem>
                                            <span>Saldo líquido</span>
                                            <Money $sinal={Math.sign(Number(dados.saldo_liquido ?? 0))}>{formatar_valor(dados.saldo_liquido ?? '0')}</Money>
                                        </DataItem>
                                        {linha('Cadastro', formatar_data_hora(dados.created_at))}
                                        {linha('Alterado em', formatar_data_hora(dados.updated_at))}
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
                            {dados && aba === 'configuracoes' && (
                                <ClientSettingsForm cliente={dados} token={token} pode_editar={pode('clientes.editar_configuracoes')} ao_salvar={ao_alterar} />
                            )}
                            {dados && aba === 'pagamentos' && (
                                <PaymentMethodsList cliente={dados} token={token} pode_editar={pode('clientes.editar')} ao_salvar={ao_alterar} />
                            )}
                            {dados && aba === 'extrato' && <ClientStatement cliente={dados} token={token} />}
                        </Body>
                    </>
                )}
            </Container>
        </>
    );
}
