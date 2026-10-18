import { useCallback, useEffect, useRef, useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';

import AffiliateClients from '../../components/AffiliateClients';
import AffiliatePasswordModal from '../../components/AffiliatePasswordModal';
import AffiliateProfileModal from '../../components/AffiliateProfileModal';
import AffiliateSettlements from '../../components/AffiliateSettlements';
import AffiliateStatement from '../../components/AffiliateStatement';
import Spinner from '../../components/Spinner';
import useAfiliado, { formatar_valor } from '../../hooks/useAfiliado';
import { alerta_erro, alerta_sucesso } from '../../utils/alerts';
import { formatar_data_hora } from '../../utils/dates';
import {
    Balance, Container, Content, CopyButton, Header, IconButton, LastBalance, Loading, Logo, Menu, MenuItem, Message, Tab, Tabs, TextBalance, Wellcome,
} from './styles';

const abas = [
    { chave: 'extrato', titulo: 'Extrato' },
    { chave: 'clientes', titulo: 'Clientes' },
    { chave: 'prestacoes', titulo: 'Prestações' },
];

// painel do afiliado (/afiliados, spec 012, US7): igual ao screens/affiliated do antigo (saudação,
// saldo, última prestação, comissão e extrato), com os acréscimos aprovados: copiar link, abas de
// clientes e prestações, Meus dados, Alterar senha e Sair
export default function AffiliatePanel() {
    const { tema, nome_sistema } = usePage().props;
    const { painel, carregando, requisitar, sair } = useAfiliado();
    const [aba, definir_aba] = useState('extrato');
    const [menu_aberto, definir_menu_aberto] = useState(false);
    // modal aberto: dados | senha | null
    const [modal, definir_modal] = useState(null);
    const menu = useRef(null);

    const fechar_modal = useCallback(() => definir_modal(null), []);

    // clicar fora fecha o menu
    useEffect(() => {
        if (!menu_aberto) return undefined;

        const ao_clicar = (evento) => {
            if (!menu.current?.contains(evento.target)) definir_menu_aberto(false);
        };
        document.addEventListener('mousedown', ao_clicar);

        return () => document.removeEventListener('mousedown', ao_clicar);
    }, [menu_aberto]);

    const abrir = (nome) => {
        definir_menu_aberto(false);
        definir_modal(nome);
    };

    const copiar_link = async () => {
        try {
            await navigator.clipboard.writeText(painel.link);
            alerta_sucesso('Link copiado!');
        } catch (erro) {
            alerta_erro(erro);
        }
    };

    if (carregando || !painel) {
        return (
            <Container>
                <Head title={`Afiliados - ${nome_sistema}`} />
                <Loading><Spinner tamanho={36} /></Loading>
            </Container>
        );
    }

    const { comissao, ultima_prestacao } = painel;
    const negativo = String(painel.valor_a_receber).startsWith('-');

    return (
        <Container>
            <Head title={`Afiliados - ${nome_sistema}`} />
            <Content>
                <Header>
                    <IconButton type="button" title="Voltar ao site" aria-label="Voltar ao site" onClick={() => router.visit('/')}>
                        <i className="material-icons" aria-hidden="true">keyboard_arrow_left</i>
                    </IconButton>
                    <Wellcome>Olá, {painel.nome}!</Wellcome>
                    <div ref={menu}>
                        <IconButton type="button" title="Clique para abrir o menu de opção" aria-label="Menu" aria-expanded={menu_aberto} onClick={() => definir_menu_aberto((atual) => !atual)}>
                            <i className="material-icons" aria-hidden="true">more_vert</i>
                        </IconButton>
                        <Menu $aberto={menu_aberto} role="menu">
                            <MenuItem type="button" role="menuitem" onClick={() => abrir('dados')}>
                                <i className="material-icons" aria-hidden="true">person</i> Meus dados
                            </MenuItem>
                            <MenuItem type="button" role="menuitem" onClick={() => abrir('senha')}>
                                <i className="material-icons" aria-hidden="true">vpn_key</i> Alterar senha
                            </MenuItem>
                            <MenuItem type="button" role="menuitem" onClick={sair}>
                                <i className="material-icons" aria-hidden="true">exit_to_app</i> Sair
                            </MenuItem>
                        </Menu>
                    </div>
                </Header>

                <Logo src={tema.logo} alt={nome_sistema} />
                <TextBalance>Meu Saldo</TextBalance>
                <Balance $negativo={negativo}>{formatar_valor(painel.valor_a_receber)}</Balance>
                <LastBalance>
                    {ultima_prestacao
                        ? `Última prestação ${formatar_valor(ultima_prestacao.valor_pago)} em ${formatar_data_hora(ultima_prestacao.periodo_fim)}`
                        : 'Última prestação --,--'}
                </LastBalance>
                {comissao && (
                    <Message>
                        Obs.: Sua comissão é de {Number(comissao.percentual).toLocaleString('pt-BR')}% em cima {comissao.tipo_ganho === 'Saldo líquido' ? 'do' : 'de'} {comissao.tipo_ganho.toLowerCase()}.
                    </Message>
                )}
                <CopyButton type="button" onClick={copiar_link} title={painel.link}>
                    <i className="material-icons" aria-hidden="true">content_copy</i> Copiar meu link
                </CopyButton>

                <Tabs role="tablist">
                    {abas.map(({ chave, titulo }) => (
                        <Tab key={chave} type="button" role="tab" $ativa={aba === chave} aria-selected={aba === chave} onClick={() => definir_aba(chave)}>
                            {titulo}
                        </Tab>
                    ))}
                </Tabs>

                {aba === 'extrato' && <AffiliateStatement requisitar={requisitar} />}
                {aba === 'clientes' && <AffiliateClients requisitar={requisitar} />}
                {aba === 'prestacoes' && <AffiliateSettlements requisitar={requisitar} />}
            </Content>

            <AffiliateProfileModal aberto={modal === 'dados'} requisitar={requisitar} ao_fechar={fechar_modal} />
            <AffiliatePasswordModal aberto={modal === 'senha'} requisitar={requisitar} ao_fechar={fechar_modal} />
        </Container>
    );
}
