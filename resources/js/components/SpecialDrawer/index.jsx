import { useEffect, useState } from 'react';

import Backdrop from '../Backdrop';
import ModalHeader from '../ModalHeader';
import SpecialCorrections from '../SpecialCorrections';
import SpecialExposure from '../SpecialExposure';
import Spinner from '../Spinner';
import { texto_vencedoras } from '../SpecialsTable';
import useVoltarFecha from '../../hooks/useVoltarFecha';
import api from '../../utils/api';
import { alerta_erro } from '../../utils/alerts';
import { formatar_data_hora } from '../../utils/dates';
import {
    Actions, Body, Button, Container, Data, DataItem, Late, Loading, Status, Statuses, Title, Top,
} from './styles';

const data_hora = (texto) => (texto ? formatar_data_hora(texto) : '—');

const exposicao_inicial = { dados: null, carregando: true, erro: null };

// detalhe do especial (spec 024, FR-021 a FR-023 e FR-032, contracts/paginas §2): gaveta à direita
// (tela cheia no celular) com os dados, a exposição por opção e as correções de resultado; versao
// muda quando uma ação de fora altera o especial. acoes(especial) devolve os atalhos do topo;
// pode_anular(especial) libera o Anular de cada opção, que chama ao_anular(especial, opcao)
export default function SpecialDrawer({ especial, versao, token, acoes, pode_anular, ao_anular, ao_fechar }) {
    const aberto = Boolean(especial);
    const [detalhe, definir_detalhe] = useState(null);
    const [exposicao, definir_exposicao] = useState(exposicao_inicial);
    const [recarga, definir_recarga] = useState(0);

    const cabecalhos = { headers: { Authorization: `Bearer ${token}` } };

    useVoltarFecha(aberto, ao_fechar);

    useEffect(() => {
        if (!aberto) return undefined;

        let ativo = true;
        api.get(`/especiais/${especial.id}`, cabecalhos)
            .then(({ data }) => ativo && definir_detalhe(data.data))
            .catch((erro) => {
                if (!ativo) return;

                alerta_erro(erro);
                ao_fechar();
            });

        return () => {
            ativo = false;
        };
    }, [especial?.id, versao]);

    useEffect(() => {
        if (!aberto) return undefined;

        let ativo = true;
        definir_exposicao((atual) => ({ ...atual, carregando: true, erro: null }));
        api.get(`/especiais/${especial.id}/exposicao`, cabecalhos)
            .then(({ data }) => ativo && definir_exposicao({ dados: data, carregando: false, erro: null }))
            .catch((erro) => ativo && definir_exposicao((atual) => ({ ...atual, carregando: false, erro })));

        return () => {
            ativo = false;
        };
    }, [especial?.id, versao, recarga]);

    useEffect(() => {
        if (!aberto) return undefined;

        const ao_teclar = (evento) => { if (evento.key === 'Escape') ao_fechar(); };
        window.addEventListener('keydown', ao_teclar);

        return () => window.removeEventListener('keydown', ao_teclar);
    }, [aberto, ao_fechar]);

    // troca de especial: não mostra os dados do anterior enquanto carrega
    const dados = detalhe && detalhe.id === especial?.id ? { ...especial, ...detalhe } : null;
    const exposicao_atual = exposicao.dados && dados ? exposicao.dados : null;

    const linha = (rotulo, valor) => (
        <DataItem key={rotulo}>
            <span>{rotulo}</span>
            <span>{valor ?? '—'}</span>
        </DataItem>
    );

    return (
        <>
            <Backdrop visivel={aberto} ao_fechar={ao_fechar} camada={45} />
            <Container $visivel={aberto} role="dialog" aria-modal="true" aria-label={especial ? `Especial ${especial.nome}` : 'Especial'}>
                {aberto && (
                    <>
                        <ModalHeader icone="emoji_events" titulo={especial.nome} descricao="Dados, exposição por opção e correções do especial." ao_fechar={ao_fechar} />
                        {dados && (
                            <Top>
                                <span>{`Data limite ${data_hora(dados.data_limite)}`}</span>
                                <Statuses>
                                    <Status $situacao={dados.situacao}>{dados.situacao}</Status>
                                    {dados.aguardando_resultado && <Late>Aguardando resultado</Late>}
                                </Statuses>
                            </Top>
                        )}
                        <Body>
                            {!dados && <Loading><Spinner tamanho={30} cor="currentColor" /></Loading>}
                            {dados && (
                                <>
                                    {acoes(dados).length > 0 && (
                                        <Actions>
                                            {acoes(dados).map((acao) => (
                                                <Button key={acao.chave} type="button" $tom={acao.tom} onClick={() => acao.ao_clicar(dados)}>
                                                    <i className="material-icons" aria-hidden="true">{acao.icone}</i>
                                                    {acao.titulo}
                                                </Button>
                                            ))}
                                        </Actions>
                                    )}

                                    <Title>Dados</Title>
                                    <Data>
                                        {linha('ID', dados.id)}
                                        {linha('Quantidade de vencedoras', dados.quantidade_vencedoras)}
                                        {linha('Ativo', dados.ativo ? 'Sim' : 'Não')}
                                        {linha('Vencedoras', texto_vencedoras(dados))}
                                        {dados.situacao === 'Encerrado' && linha('Divisão por empate', dados.dividir_empate ? 'Sim' : 'Não')}
                                        {dados.encerrado_em && linha(dados.situacao === 'Cancelado' ? 'Cancelado em' : 'Encerrado em', `${data_hora(dados.encerrado_em)}${dados.encerrado_por ? ` · ${dados.encerrado_por}` : ''}`)}
                                    </Data>

                                    <Title>Exposição por opção</Title>
                                    <SpecialExposure
                                        exposicao={exposicao_atual}
                                        carregando={exposicao.carregando}
                                        erro={exposicao.erro}
                                        pode_anular={pode_anular(dados)}
                                        ao_anular={(opcao) => ao_anular(dados, opcao)}
                                        ao_tentar_novamente={() => definir_recarga((atual) => atual + 1)}
                                    />

                                    <SpecialCorrections correcoes={dados.correcoes} />
                                </>
                            )}
                        </Body>
                    </>
                )}
            </Container>
        </>
    );
}
