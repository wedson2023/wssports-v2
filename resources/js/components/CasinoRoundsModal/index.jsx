import { useEffect, useState } from 'react';

import Spinner from '../Spinner';
import useVoltarFecha from '../../hooks/useVoltarFecha';
import api from '../../utils/api';
import { alerta_erro } from '../../utils/alerts';
import { formatar_real, para_centavos } from '../../utils/money';
import { Container, Header, Icon, List, More, Screen, Table, Td, Title, Tr, Wallet } from './styles';

const valor = (texto) => formatar_real(para_centavos(String(texto ?? '0')));

// "2026-10-16T21:01:02-03:00" → "16/10 21:01" (horário de Brasília)
const data_curta = (iso) => {
    const [data, hora] = iso.split('T');
    const [, mes, dia] = data.split('-');

    return `${dia}/${mes} ${hora.slice(0, 5)}`;
};

// detalhe das rodadas de uma sessão de jogo do cassino (modals/details_casino do antigo); a
// carteira usada aparece abaixo do ID e a rodada cancelada, riscada
export default function CasinoRoundsModal({ sessao, ao_fechar }) {
    const aberto = Boolean(sessao);
    const [rodadas, definir_rodadas] = useState([]);
    const [pagina, definir_pagina] = useState({ atual: 0, ultima: 0 });
    const [carregando, definir_carregando] = useState(false);

    useVoltarFecha(aberto, ao_fechar);

    const carregar = (numero) => {
        definir_carregando(true);

        api.get(`/area-cliente/cassino/sessoes/${sessao.sessao_id}/rodadas`, { params: { pagina: numero, por_pagina: 50 } })
            .then(({ data }) => {
                definir_rodadas((atuais) => (numero === 1 ? data.data : [...atuais, ...data.data]));
                definir_pagina({ atual: data.meta.current_page, ultima: data.meta.last_page });
            })
            .catch(alerta_erro)
            .finally(() => definir_carregando(false));
    };

    useEffect(() => {
        if (!aberto) return;

        definir_rodadas([]);
        carregar(1);
    }, [aberto, sessao?.sessao_id]);

    return (
        <>
            <Container $aberto={aberto}>
                <Header>
                    <Title>{`Detalhes apostas no ${sessao?.jogo ?? ''}`}</Title>
                    <Icon onClick={ao_fechar}>close</Icon>
                </Header>
                <List>
                    <Table>
                        <tbody>
                            <Tr>
                                <Td>DATA</Td>
                                <Td>ID TRAN.</Td>
                                <Td title="Saldo anterior.">SALDO</Td>
                                <Td>VALOR</Td>
                                <Td>PRÊMIO</Td>
                            </Tr>
                            {rodadas.map((rodada) => (
                                <Tr key={rodada.id} $cancelada={rodada.cancelada}>
                                    <Td>{data_curta(rodada.data)}</Td>
                                    <Td>
                                        {rodada.trade_id}
                                        <Wallet>{rodada.carteira}</Wallet>
                                    </Td>
                                    <Td>{valor(rodada.saldo_anterior)}</Td>
                                    <Td>{valor(rodada.aposta)}</Td>
                                    <Td>{valor(rodada.premio)}</Td>
                                </Tr>
                            ))}
                        </tbody>
                    </Table>
                    {carregando && <More as="div"><Spinner tamanho={24} cor="#999" /></More>}
                    {!carregando && pagina.atual < pagina.ultima && <More onClick={() => carregar(pagina.atual + 1)}>Carregar mais</More>}
                </List>
            </Container>
            <Screen $aberto={aberto} onClick={ao_fechar} />
        </>
    );
}
