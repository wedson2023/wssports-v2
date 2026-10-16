import { useCallback, useEffect, useRef, useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';

import AuthModal from '../../components/AuthModal';
import BannerCarousel from '../../components/BannerCarousel';
import CasinoHeader from '../../components/CasinoHeader';
import CasinoMenu from '../../components/CasinoMenu';
import CasinoSearch from '../../components/CasinoSearch';
import CasinoTabs from '../../components/CasinoTabs';
import DepositModal from '../../components/DepositModal';
import Footer from '../../components/Footer';
import GameFrame from '../../components/GameFrame';
import GameGrid from '../../components/GameGrid';
import GameRow from '../../components/GameRow';
import GameTypeBar from '../../components/GameTypeBar';
import PromotionsModal from '../../components/PromotionsModal';
import ProviderBar from '../../components/ProviderBar';
import Spinner from '../../components/Spinner';
import WalletPopup from '../../components/WalletPopup';
import WithdrawModal from '../../components/WithdrawModal';
import useCassino from '../../hooks/useCassino';
import useJogoCassino from '../../hooks/useJogoCassino';
import useSessao from '../../hooks/useSessao';
import { alerta_atencao } from '../../utils/alerts';
import { Container, Content, List, Loading, Slide } from './styles';

// área do cassino (/cassino, spec 011) com o visual do screens/casino do sistema antigo
export default function Casino({ cassino, banners, promocoes }) {
    const { nome_sistema } = usePage().props;
    const { sessao } = useSessao();
    const lista = useCassino(cassino.habilitado);
    const conteudo = useRef(null);
    const barra_provedores = useRef(null);

    const [menu_aberto, definir_menu_aberto] = useState(false);
    const [modal, definir_modal] = useState(null);
    const [aba_acesso, definir_aba_acesso] = useState(() => (new URLSearchParams(window.location.search).get('user') ? 'cadastrar' : null));
    const [saldo_saque, definir_saldo_saque] = useState(null);
    const [recarregar_carteira, definir_recarregar_carteira] = useState(0);

    const pedir_login = useCallback(() => definir_aba_acesso('entrar'), []);
    const jogo = useJogoCassino({ ao_pedir_login: pedir_login });

    // cassino desligado: avisa e volta para os esportes em 3 s, como o antigo (R-21)
    useEffect(() => {
        if (!lista.indisponivel) return undefined;

        alerta_atencao('Acesso ao cassino não está disponível.');
        const espera = setTimeout(() => router.visit('/'), 3000);

        return () => clearTimeout(espera);
    }, [lista.indisponivel]);

    // ao filtrar, a tela rola até a barra de provedores
    const rolar_para_filtros = () => {
        if (conteudo.current && barra_provedores.current) {
            conteudo.current.scrollTo({ top: barra_provedores.current.offsetTop - 70, behavior: 'smooth' });
        }
    };

    const filtrar = (novos, item = null) => {
        rolar_para_filtros();
        lista.filtrar(novos, item);
    };

    const ao_rolar = (evento) => {
        if (!lista.com_filtro) return;

        const { scrollHeight, scrollTop, clientHeight } = evento.target;

        if (scrollHeight - (scrollTop + 500) <= clientHeight) lista.carregar_mais();
    };

    const limpar = () => {
        rolar_para_filtros();
        lista.limpar();
    };

    // Carteira e Minha conta: só o apostador (cadastro online), como no antigo
    const so_cliente = (acao) => () => {
        if (!sessao) {
            pedir_login();
            return;
        }

        if (sessao.tipo !== 'cliente') {
            alerta_atencao('Só é possível acessar com cadastro online.');
            return;
        }

        acao();
    };

    const fechar_modal = useCallback(() => definir_modal(null), []);
    const completar_cadastro = () => router.visit('/minha-conta');

    if (!cassino.habilitado || lista.indisponivel || !lista.inicio) {
        return (
            <Loading>
                <Head title={`${nome_sistema} | Cassino`} />
                <Spinner tamanho={32} />
            </Loading>
        );
    }

    const { provedores, tipos, linhas } = lista.inicio;
    const populares = linhas.find((linha) => linha.tipo === 'populares')?.jogos ?? [];

    return (
        <Container>
            <Head title={`${nome_sistema} | Cassino`} />
            <CasinoHeader logado={Boolean(sessao)} ao_abrir_menu={() => definir_menu_aberto(true)} ao_criar_conta={() => definir_aba_acesso('cadastrar')} />
            <Content ref={conteudo} onScroll={ao_rolar} $desfocado={modal === 'carteira'}>
                {banners.length > 0 && (
                    <Slide>
                        <BannerCarousel banners={banners} />
                    </Slide>
                )}
                <CasinoSearch ao_buscar={(busca) => filtrar({ busca })} />
                <div ref={barra_provedores}>
                    <ProviderBar
                        provedores={provedores}
                        selecionado={lista.filtros.provedor}
                        carregando={lista.carregando_item}
                        ao_escolher={(provedor) => filtrar({ provedor: provedor.id }, `provedor-${provedor.id}`)}
                    />
                </div>
                <GameTypeBar
                    tipos={tipos}
                    selecionado={lista.filtros.tipo}
                    carregando={lista.carregando_item}
                    ao_escolher={(tipo) => filtrar({ tipo }, `tipo-${tipo}`)}
                />
                {lista.com_filtro ? (
                    <GameGrid jogos={lista.jogos_filtrados} aguardando={lista.aguardando} abrindo={jogo.abrindo} ao_jogar={jogo.jogar} ao_demo={jogo.demo} />
                ) : (
                    <List>
                        {linhas.filter((linha) => linha.jogos.length > 0).map((linha) => (
                            <GameRow
                                key={linha.tipo}
                                tipo={linha.tipo}
                                jogos={linha.jogos}
                                abrindo={jogo.abrindo}
                                ao_ver_mais={(tipo) => filtrar({ tipo })}
                                ao_jogar={jogo.jogar}
                                ao_demo={jogo.demo}
                            />
                        ))}
                    </List>
                )}
                <Footer />
            </Content>
            <CasinoTabs
                ao_limpar={limpar}
                ao_promocoes={() => definir_modal('promocoes')}
                ao_carteira={so_cliente(() => definir_modal(modal === 'carteira' ? null : 'carteira'))}
                ao_minha_conta={so_cliente(() => router.visit('/minha-conta'))}
            />
            <CasinoMenu
                aberto={menu_aberto}
                mostrar_esportes={!cassino.somente_cassino}
                populares={populares}
                tipos={tipos}
                provedores={provedores}
                ao_fechar={() => definir_menu_aberto(false)}
                ao_esportes={() => router.visit('/')}
                ao_jogar={jogo.jogar}
                ao_tipo={(tipo) => filtrar({ tipo }, `tipo-${tipo}`)}
                ao_provedor={(provedor) => filtrar({ provedor: provedor.id }, `provedor-${provedor.id}`)}
            />
            <WalletPopup
                aberto={modal === 'carteira'}
                recarregar={recarregar_carteira}
                ao_fechar={fechar_modal}
                ao_depositar={() => definir_modal('deposito')}
                ao_sacar={(saldo) => {
                    definir_saldo_saque(saldo);
                    definir_modal('saque');
                }}
            />
            <PromotionsModal aberto={modal === 'promocoes'} promocoes={promocoes} ao_fechar={fechar_modal} />
            <DepositModal
                aberto={modal === 'deposito'}
                ao_fechar={fechar_modal}
                ao_concluir={() => definir_recarregar_carteira((vezes) => vezes + 1)}
                ao_completar_cadastro={completar_cadastro}
            />
            <WithdrawModal
                aberto={modal === 'saque'}
                saldo={saldo_saque}
                ao_fechar={fechar_modal}
                ao_concluir={() => definir_recarregar_carteira((vezes) => vezes + 1)}
                ao_completar_cadastro={completar_cadastro}
            />
            <AuthModal
                aba={aba_acesso}
                codigo_afiliado={new URLSearchParams(window.location.search).get('user') ?? ''}
                ao_trocar_aba={definir_aba_acesso}
                ao_fechar={() => definir_aba_acesso(null)}
            />
            {jogo.jogo_aberto && <GameFrame url={jogo.jogo_aberto.url} nome={jogo.jogo_aberto.nome} ao_fechar={jogo.fechar} />}
        </Container>
    );
}
