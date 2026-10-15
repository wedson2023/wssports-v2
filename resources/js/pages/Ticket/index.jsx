import { Fragment, useRef } from 'react';
import { Head, router, usePage } from '@inertiajs/react';

import CashOutBar from '../../components/CashOutBar';
import CashOutPanel from '../../components/CashOutPanel';
import ConnectionStatus from '../../components/ConnectionStatus';
import LiveBlockCard from '../../components/LiveBlockCard';
import LiveHunchCard from '../../components/LiveHunchCard';
import NotificationBell from '../../components/NotificationBell';
import Spinner from '../../components/Spinner';
import TicketSummary from '../../components/TicketSummary';
import useBilheteTempoReal from '../../hooks/useBilheteTempoReal';
import useSessao from '../../hooks/useSessao';
import { agrupar_palpites_comprovante } from '../../utils/bet_builder';
import { Aside, BackButton, Container, Empty, Grid, Hunches, Invite, Logo, Main, TopBar } from './styles';

// bilhete em tempo real (/bilhete/{codigo}, spec 010): visual aprovado no protótipo; no celular uma
// coluna (resumo, encerramento e palpites) e, a partir do ponto de quebra, palpites à esquerda e
// resumo à direita acompanhando a rolagem
export default function Ticket({ codigo, tempo_real = null }) {
    const { tema, nome_sistema } = usePage().props;
    const { bilhete, carregando, erro, conexao, atualizado_em, gols, recarregar } = useBilheteTempoReal(codigo, tempo_real);
    const { sessao, acesso_assistido } = useSessao();
    const painel = useRef(null);

    const voltar = () => (window.history.length > 1 ? window.history.back() : router.visit('/'));

    // barra fixa do celular: leva ao bloco de encerramento e abre a confirmação do total
    const encerrar_pela_barra = () => {
        document.getElementById('encerramento')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        painel.current?.abrir_total();
    };

    // sem login de cliente, a página só acompanha; o convite leva ao login e volta para cá
    const convidar_login = bilhete && !bilhete.final && !bilhete.dono && sessao?.tipo !== 'cliente' && !acesso_assistido;

    return (
        <Container>
            <Head title={`${nome_sistema} - Bilhete ${codigo}`} />
            <TopBar>
                <BackButton type="button" onClick={voltar} aria-label="Voltar">
                    <span className="material-icons" aria-hidden="true">keyboard_arrow_left</span>
                </BackButton>
                <Logo src={tema.logo} alt={nome_sistema} />
                <span aria-hidden="true" />
            </TopBar>

            {carregando && !bilhete && <Empty><Spinner tamanho={32} cor={tema.temas} /></Empty>}

            {erro === 'nao_encontrado' && (
                <Empty>
                    <p>Bilhete não encontrado ou ainda não validado.</p>
                    <button type="button" onClick={() => router.visit('/')}>Ir para a tela principal</button>
                </Empty>
            )}

            {erro === 'falha' && !bilhete && (
                <Empty><p>Não foi possível carregar o bilhete. Tentando de novo…</p></Empty>
            )}

            {bilhete && (
                <Grid>
                    <Aside>
                        <TicketSummary
                            bilhete={bilhete}
                            indicador={<ConnectionStatus conexao={conexao} atualizado_em={atualizado_em} />}
                            acoes={<NotificationBell ativo={sessao?.tipo === 'cliente' || Boolean(acesso_assistido)} />}
                        />
                        {bilhete.encerramento && <CashOutPanel ref={painel} bilhete={bilhete} recarregar={recarregar} />}
                        {convidar_login && (
                            <Invite>
                                É o dono desta aposta?{' '}
                                <a href={`/?entrar=1&voltar=/bilhete/${encodeURIComponent(bilhete.codigo)}`}>Entre para encerrar</a>
                            </Invite>
                        )}
                    </Aside>
                    <Main>
                        <Hunches>
                            {agrupar_palpites_comprovante(bilhete).map(({ bloco, palpites }) => (
                                <Fragment key={palpites[0].id}>
                                    {bloco
                                        ? <LiveBlockCard bloco={bloco} palpites={palpites} gol={Boolean(gols[palpites[0].confrontos_id])} />
                                        : <LiveHunchCard palpite={palpites[0]} gol={Boolean(gols[palpites[0].confrontos_id])} />}
                                </Fragment>
                            ))}
                        </Hunches>
                    </Main>
                </Grid>
            )}

            {bilhete?.encerramento && !bilhete.encerramento.final && (
                <CashOutBar encerramento={bilhete.encerramento} ao_encerrar={encerrar_pela_barra} />
            )}
        </Container>
    );
}
