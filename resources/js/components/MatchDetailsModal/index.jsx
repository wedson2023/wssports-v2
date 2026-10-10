import { Fragment, useEffect, useState } from 'react';

import Backdrop from '../Backdrop';
import OddButton from '../OddButton';
import useCupom from '../../hooks/useCupom';
import useVoltarFecha from '../../hooks/useVoltarFecha';
import api from '../../utils/api';
import { alerta_erro } from '../../utils/alerts';
import { abas_cotacoes } from '../../utils/market_groups';
import { Category, Container, Header, Icon, Item, List, Loading, Message, Tab, TabText, Tabs, TextMatch, TextOdd } from './styles';

// anel de carregamento do modal (Rings, 45px, #222)
const anel = (
    <svg width="45" height="45" viewBox="0 0 45 45" stroke="#222">
        <g fill="none" fillRule="evenodd" transform="translate(1 1)" strokeWidth="2">
            <circle cx="22" cy="22" r="6">
                <animate attributeName="r" begin="0s" dur="1.5s" values="6;22" calcMode="linear" repeatCount="indefinite" />
                <animate attributeName="stroke-opacity" begin="0s" dur="1.5s" values="1;0" calcMode="linear" repeatCount="indefinite" />
            </circle>
            <circle cx="22" cy="22" r="8">
                <animate attributeName="r" begin="0s" dur="1.5s" values="6;1;2;3;4;5;6" calcMode="linear" repeatCount="indefinite" />
            </circle>
        </g>
    </svg>
);

// todos os mercados de um jogo, nas abas e categorias do sistema antigo (FR-030, FR-031)
export default function MatchDetailsModal({ jogo, ao_fechar }) {
    const { cupom, alternar_palpite } = useCupom();
    const [detalhe, definir_detalhe] = useState(null);
    const [aba, definir_aba] = useState('favoritas');
    const visivel = Boolean(jogo);
    useVoltarFecha(visivel, ao_fechar);

    useEffect(() => {
        if (!jogo) return undefined;

        let ativo = true;
        const rota = jogo.tipo === 'ao_vivo' ? 'confrontos-ao-vivo' : 'confrontos';

        definir_detalhe(null);
        definir_aba('favoritas');

        api.get(`/publico/${rota}/${jogo.confronto_id}`)
            .then(({ data }) => ativo && definir_detalhe(data.data))
            .catch((erro) => {
                if (!ativo) return;
                alerta_erro(erro);
                ao_fechar();
            });

        return () => { ativo = false; };
    }, [jogo, ao_fechar]);

    const palpite_do_jogo = jogo && cupom.palpites.find((palpite) => palpite.tipo === jogo.tipo && palpite.confronto_id === jogo.confronto_id);

    const escolher = (codigo_cotacao, mercado, cotacao, jogador_id) => alternar_palpite({
        tipo: jogo.tipo,
        confronto_id: jogo.confronto_id,
        codigo_cotacao,
        mercado,
        cotacao: Number(cotacao).toFixed(2),
        time_casa: detalhe.time_casa,
        time_fora: detalhe.time_fora,
        campeonato: detalhe.campeonato,
        data_inicio: detalhe.data_inicio,
        ...(jogador_id ? { jogador_id } : {}),
    });

    const cotacoes = new Map((detalhe?.cotacoes ?? []).map((item) => [item.codigo_cotacao, item]));
    const categorias = (abas_cotacoes.find(({ chave }) => chave === aba)?.categorias ?? [])
        .map(({ titulo, codigos }) => ({ titulo, itens: codigos.map((codigo) => cotacoes.get(codigo)).filter((item) => item && Number(item.cotacao) > 1) }))
        .filter(({ itens }) => itens.length);

    // jogadores ("ATLETAS" do antigo) aparecem na aba 90 min, agrupados pelo tipo
    const jogadores_por_tipo = aba === '90 min'
        ? Object.entries((detalhe?.jogadores ?? []).reduce((grupos, jogador) => ({
            ...grupos,
            [jogador.tipo]: [...(grupos[jogador.tipo] ?? []), jogador],
        }), {}))
        : [];

    return (
        <>
            <Container $visivel={visivel}>
                {!detalhe ? (
                    <Loading>
                        {anel}
                    </Loading>
                ) : (
                    <>
                        <Header>
                            <TextMatch>{`${detalhe.time_casa} x ${detalhe.time_fora}`}</TextMatch>
                            <Icon onClick={ao_fechar}>close</Icon>
                        </Header>
                        <Tabs>
                            {abas_cotacoes.map(({ chave, titulo }) => (
                                <Tab key={chave} $ativa={aba === chave} onClick={() => definir_aba(chave)}>
                                    <TabText $ativa={aba === chave}>{titulo}</TabText>
                                </Tab>
                            ))}
                        </Tabs>
                        {!categorias.length && !jogadores_por_tipo.length && <Message>Nenhuma opção nessa categoria.</Message>}
                        <List>
                            {categorias.map(({ titulo, itens }) => (
                                <Fragment key={titulo}>
                                    <Category>{titulo}</Category>
                                    {itens.map(({ codigo_cotacao, mercado, cotacao }) => (
                                        <Item key={codigo_cotacao}>
                                            <TextOdd>{mercado}</TextOdd>
                                            <OddButton
                                                largo
                                                cotacao={cotacao}
                                                selecionado={palpite_do_jogo?.codigo_cotacao === codigo_cotacao}
                                                ao_clicar={() => escolher(codigo_cotacao, mercado, cotacao)}
                                            />
                                        </Item>
                                    ))}
                                </Fragment>
                            ))}
                            {jogadores_por_tipo.map(([tipo, jogadores]) => (
                                <Fragment key={tipo}>
                                    <Category>{`${tipo.toUpperCase()} A MARCAR GOL`}</Category>
                                    {jogadores.filter(({ cotacao }) => Number(cotacao) > 1).map((jogador) => (
                                        <Item key={jogador.confrontos_jogadores_id}>
                                            <TextOdd>{jogador.nome}</TextOdd>
                                            <OddButton
                                                largo
                                                cotacao={jogador.cotacao}
                                                selecionado={palpite_do_jogo?.jogador_id === jogador.confrontos_jogadores_id}
                                                ao_clicar={() => escolher('jogador', `${jogador.nome} (${tipo})`, jogador.cotacao, jogador.confrontos_jogadores_id)}
                                            />
                                        </Item>
                                    ))}
                                </Fragment>
                            ))}
                        </List>
                    </>
                )}
            </Container>
            <Backdrop visivel={visivel} ao_fechar={ao_fechar} />
        </>
    );
}
