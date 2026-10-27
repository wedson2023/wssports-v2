import { router } from '@inertiajs/react';

import Pagination from '../Pagination';
import { nome_da_opcao } from '../MatchPicksRanking';
import useNomesCotacoes from '../../hooks/useNomesCotacoes';
import { formatar_valor } from '../../utils/charts';
import { formatar_data_hora } from '../../utils/dates';
import { Empty, Entry, EntryLine, List, Retry } from './styles';

// apostas com palpite no jogo (spec 023, FR-017), da mais nova para a mais antiga: só o palpite deste
// jogo; nas múltiplas, a quantidade de jogos. "Abrir aposta" leva à tela Apostas pela busca do código
// (com apostas.listar). apostas = { linhas, pagina, ultima }
export default function MatchBetsList({ apostas, pode_abrir, ao_trocar_pagina }) {
    const nomes = useNomesCotacoes();

    if (!apostas.linhas.length) return <Empty>Nenhuma aposta neste jogo.</Empty>;

    return (
        <>
            <List>
                {apostas.linhas.map((aposta) => (
                    <Entry key={`${aposta.codigo}-${aposta.codigo_cotacao}`}>
                        <EntryLine>
                            <strong>{aposta.codigo}</strong>
                            <strong>{formatar_valor(aposta.valor)}</strong>
                        </EntryLine>
                        <EntryLine>
                            <span>{nome_da_opcao(nomes, aposta)}</span>
                            <span>{aposta.palpites > 1 ? `Múltipla (${aposta.palpites} jogos)` : 'Simples'}</span>
                        </EntryLine>
                        <EntryLine>
                            <span>{aposta.vendedor?.nome ?? aposta.cliente?.nome ?? '—'}</span>
                            <span>{`Retorno ${formatar_valor(aposta.premio)} · ${aposta.resultado}`}</span>
                        </EntryLine>
                        <EntryLine>
                            <span>{formatar_data_hora(aposta.created_at)}</span>
                            {pode_abrir && <Retry type="button" onClick={() => router.visit(`/painel/apostas?busca=${encodeURIComponent(aposta.codigo)}`)}>Abrir aposta</Retry>}
                        </EntryLine>
                    </Entry>
                ))}
            </List>
            <Pagination pagina={apostas.pagina} ultima={apostas.ultima} ao_trocar={ao_trocar_pagina} />
        </>
    );
}
