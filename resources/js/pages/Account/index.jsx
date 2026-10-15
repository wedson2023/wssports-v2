import { useCallback, useEffect, useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';

import AccountHeader from '../../components/AccountHeader';
import AccountMenu from '../../components/AccountMenu';
import AssistedBanner from '../../components/AssistedBanner';
import Backdrop from '../../components/Backdrop';
import BalanceCards from '../../components/BalanceCards';
import ConfirmModal from '../../components/ConfirmModal';
import DepositModal from '../../components/DepositModal';
import InactiveAccount from '../../components/InactiveAccount';
import ProfileModal from '../../components/ProfileModal';
import ReferralModal from '../../components/ReferralModal';
import ReferralsModal from '../../components/ReferralsModal';
import StatementFilters from '../../components/StatementFilters';
import StatementTable, { Icon as BonusIcon } from '../../components/StatementTable';
import WithdrawModal from '../../components/WithdrawModal';
import useMinhaConta from '../../hooks/useMinhaConta';
import useSessao from '../../hooks/useSessao';
import useTelaMobile from '../../hooks/useTelaMobile';
import api from '../../utils/api';
import { alerta_atencao, alerta_erro, alerta_sucesso } from '../../utils/alerts';
import { formatar_real, para_centavos } from '../../utils/money';
import { Container, Content, Loading } from './styles';

// tela de créditos do cliente (/minha-conta, spec 007): igual à screens/credits do antigo; os
// modais (depósito, saque, Meus dados, indicações) têm o visual novo
export default function Account({ acesso = null }) {
    const { contatos, nome_sistema } = usePage().props;
    const { sessao, sair, acesso_assistido, iniciar_acesso_assistido, encerrar_acesso_assistido } = useSessao();
    const e_mobile = useTelaMobile();
    // com ?acesso= na URL, o painel abriu a conta do cliente: troca o código antes de carregar
    const [trocando_codigo, definir_trocando_codigo] = useState(Boolean(acesso));
    const logado = Boolean(acesso_assistido) || sessao?.tipo === 'cliente';
    const conta = useMinhaConta(logado && !trocando_codigo);

    const [menu_aberto, definir_menu_aberto] = useState(false);
    // modal aberto: confirmar_saida | motivo | deposito | saque | bonus | indicar | indicacoes
    const [modal, definir_modal] = useState(null);
    const [motivo, definir_motivo] = useState('');

    useEffect(() => {
        if (!acesso) return;

        const url = new URL(window.location.href);
        url.searchParams.delete('acesso');
        window.history.replaceState(window.history.state, '', `${url.pathname}${url.search}${url.hash}`);

        iniciar_acesso_assistido(acesso)
            .catch((erro) => alerta_erro(erro))
            .finally(() => definir_trocando_codigo(false));
        // o código é de uso único: troca uma vez ao abrir
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    // sem sessão de cliente nem acesso assistido, vai para o login e volta para cá depois de entrar
    useEffect(() => {
        if (!logado && !trocando_codigo) router.visit('/?entrar=1&voltar=/minha-conta');
    }, [logado, trocando_codigo]);

    // token vencido ou recusado: encerra o acesso assistido ou a sessão local e volta para os jogos
    useEffect(() => {
        if (conta.situacao !== 'expirada') return;

        if (acesso_assistido) {
            alerta_atencao('Acesso assistido encerrado.');
            encerrar_acesso_assistido();
        } else {
            sair().then(() => router.visit('/'));
        }
    }, [conta.situacao, sair, acesso_assistido, encerrar_acesso_assistido]);

    // "Voltar ao painel": encerra o acesso e fecha a aba que o painel abriu
    const voltar_ao_painel = async () => {
        await encerrar_acesso_assistido();
        window.close();
        router.visit('/');
    };

    const fechar_modal = useCallback(() => definir_modal(null), []);

    // Meus dados abre por cima do depósito ou do saque quando falta algum dado; ao salvar, a
    // operação que pediu é retomada (FR-020, FR-036)
    const [perfil, definir_perfil] = useState(null); // { foco: [...], retomar: função | null }

    const completar_cadastro = useCallback(async (faltando, mensagem, retomar) => {
        await alerta_atencao(mensagem);
        definir_perfil({ foco: faltando, retomar });
    }, []);

    const perfil_salvo = () => {
        const retomar = perfil?.retomar;
        definir_perfil(null);
        conta.recarregar_tudo();
        retomar?.();
    };

    const tocar_linha = (item) => {
        if (item.saque?.situacao === 'Recusado') {
            definir_motivo(item.saque.motivo ?? 'Saque recusado.');
            definir_modal('motivo');
            return;
        }

        // operação de aposta abre direto o bilhete em tempo real (spec 010, FR-015)
        if (item.aposta) router.visit(`/bilhete/${encodeURIComponent(item.aposta.codigo)}`);
    };

    const escolher_item = (chave) => {
        if (e_mobile) definir_menu_aberto(false);

        const acoes = {
            apostar: () => router.visit('/'),
            regras: () => router.visit('/regras'),
            atendimento: () => {
                window.location.href = `https://api.whatsapp.com/send?phone=${contatos.whatsapp}&text=${encodeURIComponent('Olá')}`;
            },
            // no acesso assistido, "Sair" encerra o acesso (a sessão do cliente não é dele)
            sair: () => (acesso_assistido ? voltar_ao_painel() : definir_modal('confirmar_saida')),
            depositar: () => definir_modal('deposito'),
            sacar: () => definir_modal('saque'),
            meus_dados: () => definir_perfil({ foco: [], retomar: null }),
            indicar: () => definir_modal('indicar'),
        };

        acoes[chave]?.();
    };

    // converter ou excluir bônus pelo extrato, com confirmação (FR-015 a FR-018)
    const [bonus_escolhido, definir_bonus_escolhido] = useState(null);

    const pedir_acao_bonus = (item, acao) => {
        definir_bonus_escolhido({ ...item.bonus, valor: item.valor, acao });
        definir_modal('bonus');
    };

    const executar_acao_bonus = async () => {
        try {
            const { data } = await api.post(`/area-cliente/bonus/${bonus_escolhido.rollover_id}/${bonus_escolhido.acao}`);
            definir_modal(null);
            alerta_sucesso(data.message);
            conta.recarregar_tudo();
        } catch (erro) {
            definir_modal(null);
            alerta_erro(erro);
        }
    };

    const mensagem_bonus = () => {
        if (!bonus_escolhido) return '';

        if (bonus_escolhido.acao === 'converter') {
            return `Converter o valor de R$ ${formatar_real(para_centavos(bonus_escolhido.liberado))} para seu saldo real? Dúvidas sobre o valor convertido, consulte as regras de bônus.`;
        }

        const modalidade = bonus_escolhido.modalidade === 'Cassino' ? 'cassino' : 'esportes';

        return `Tem certeza que deseja excluir o bônus ${(bonus_escolhido.nome ?? '').toUpperCase()}? Além de remover o rollover, removerá seu saldo em bônus ${modalidade}.`;
    };

    const acoes_bonus = (item) => (
        <>
            <BonusIcon title="Clique para converter o valor desse bônus para o saldo" onClick={() => pedir_acao_bonus(item, 'converter')}>download</BonusIcon>
            <BonusIcon title="Clique para excluir esse bônus" onClick={() => pedir_acao_bonus(item, 'excluir')}>delete</BonusIcon>
        </>
    );

    const sair_da_conta = async () => {
        await sair();
        router.visit('/');
    };

    if (trocando_codigo) return <Container><Loading>Carregando...</Loading></Container>;

    if (!logado) return null;

    if (conta.situacao === 'inativa') {
        return (
            <Container>
                <Head title={nome_sistema} />
                <InactiveAccount ao_atualizar={() => window.location.reload()} ao_sair={sair_da_conta} />
            </Container>
        );
    }

    // carregando (saldos, Meus dados e a primeira página do extrato): só o aviso em tela cheia, sem
    // o menu (sozinho na linha ele ficaria à esquerda e pularia para a direita quando o conteúdo
    // chegasse) e sem o extrato pela metade, que quebrava a rolagem
    if (!conta.dados || !conta.extrato_pronto) {
        return (
            <Container>
                <Head title={nome_sistema} />
                <Loading>Carregando...</Loading>
            </Container>
        );
    }

    const itens_ocultos = [
        ...(conta.dados?.menu.indicacao.vigente ? [] : ['indicar']),
        ...(contatos?.whatsapp ? [] : ['atendimento']),
    ];

    return (
        <Container>
            <Head title={nome_sistema} />
            <Content>
                {acesso_assistido && (
                    <AssistedBanner nome={acesso_assistido.nome} usuario={acesso_assistido.usuario} vence_em={acesso_assistido.vence_em} ao_voltar={voltar_ao_painel} />
                )}
                <AccountHeader nome={conta.dados.cliente.nome} ao_voltar={() => router.visit('/')} ao_abrir_menu={() => definir_menu_aberto(true)} />
                <BalanceCards saldos={conta.dados.saldos} />
                <StatementFilters filtros={conta.filtros} ao_mudar={conta.definir_filtros} carregando={conta.carregando_extrato} />
                <StatementTable
                    itens={conta.itens}
                    fim={conta.fim}
                    carregando={conta.carregando_extrato}
                    ao_carregar_mais={conta.carregar_mais}
                    ao_tocar_linha={tocar_linha}
                    acoes_bonus={acoes_bonus}
                />
            </Content>

            <AccountMenu aberto={menu_aberto} ao_fechar={() => definir_menu_aberto(false)} itens_ocultos={itens_ocultos} ao_escolher={escolher_item} />
            {e_mobile && <Backdrop visivel={menu_aberto} ao_fechar={() => definir_menu_aberto(false)} camada={45} />}

            <ConfirmModal
                aberto={modal === 'confirmar_saida'}
                titulo="Sair da conta"
                icone="exit_to_app"
                mensagem="Deseja sair da sua conta e voltar para tela de login?"
                rotulo_confirmar="Sair"
                ao_confirmar={sair_da_conta}
                ao_fechar={fechar_modal}
            />
            <ConfirmModal aberto={modal === 'motivo'} titulo="Saque recusado" icone="info" mensagem={motivo} ao_fechar={fechar_modal} />
            <ConfirmModal
                aberto={modal === 'bonus'}
                titulo={bonus_escolhido?.acao === 'converter' ? 'Converter bônus' : 'Excluir bônus'}
                icone={bonus_escolhido?.acao === 'converter' ? 'download' : 'delete'}
                mensagem={mensagem_bonus()}
                rotulo_confirmar={bonus_escolhido?.acao === 'converter' ? 'Converter' : 'Excluir'}
                ao_confirmar={executar_acao_bonus}
                ao_fechar={fechar_modal}
            />
            <DepositModal
                aberto={modal === 'deposito'}
                opcoes_iniciais={conta.opcoes_deposito}
                ao_fechar={fechar_modal}
                ao_concluir={conta.recarregar_tudo}
                ao_completar_cadastro={completar_cadastro}
            />
            <WithdrawModal
                aberto={modal === 'saque'}
                saldo={conta.dados?.saldos.saldo.valor}
                ao_fechar={fechar_modal}
                ao_concluir={conta.recarregar_tudo}
                ao_completar_cadastro={completar_cadastro}
            />
            <ReferralModal aberto={modal === 'indicar'} resumo_inicial={conta.resumo_indicacoes} ao_fechar={fechar_modal} ao_ver_indicacoes={() => definir_modal('indicacoes')} />
            <ReferralsModal aberto={modal === 'indicacoes'} ao_fechar={fechar_modal} />
            <ProfileModal
                aberto={Boolean(perfil)}
                foco={perfil?.foco ?? []}
                perfil={conta.perfil}
                ao_fechar={() => definir_perfil(null)}
                ao_salvar={perfil_salvo}
                ao_senha_alterada={sair_da_conta}
            />
        </Container>
    );
}
