import { useCallback, useEffect, useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import Swal from 'sweetalert2';

import BannersManager from '../../components/BannersManager';
import LogoUploader from '../../components/LogoUploader';
import MercadoPagoCard from '../../components/MercadoPagoCard';
import MessagesCard from '../../components/MessagesCard';
import NoticesManager from '../../components/NoticesManager';
import PanelShell from '../../components/PanelShell';
import PanelTabs from '../../components/PanelTabs';
import RulesEditor from '../../components/RulesEditor';
import SaveBar from '../../components/SaveBar';
import SettingsForm from '../../components/SettingsForm';
import SettingsFormCard from '../../components/SettingsFormCard';
import SettingsSection from '../../components/SettingsSection';
import Spinner from '../../components/Spinner';
import ThemePicker from '../../components/ThemePicker';
import WhatsAppBotCard from '../../components/WhatsAppBotCard';
import usePainel from '../../hooks/usePainel';
import useSecaoConfiguracoes from '../../hooks/useSecaoConfiguracoes';
import { alerta_erro, confirmar } from '../../utils/alerts';
import {
    campos_atendimento, campos_identificacao, campos_rastreamento, campos_redes, secoes_operacao,
} from '../../utils/settings_fields';
import {
    Columns, Grid, Intro, Loading, Section, Stack, TabsArea, Wide,
} from './styles';

// abas da tela, com a frase que aparece embaixo explicando o que se configura em cada uma
const abas = [
    { chave: 'banca', rotulo: 'Banca', icone: 'storefront', descricao: 'Dados da banca que aparecem no site: nome, atendimento, redes sociais, cores e rastreamento.' },
    { chave: 'visual', rotulo: 'Visual', icone: 'palette', descricao: 'Logo e ícones do aplicativo, banners do carrossel e avisos que abrem no site.' },
    { chave: 'regras', rotulo: 'Regras', icone: 'gavel', descricao: 'Texto das regras da banca mostrado na página de regras do site.' },
    { chave: 'integracoes', rotulo: 'Integrações', icone: 'hub', descricao: 'Conta do Mercado Pago, número do WhatsApp conectado à banca e mensagens do sistema.' },
    { chave: 'operacao', rotulo: 'Operação', icone: 'tune', descricao: 'Regras de funcionamento: pagamentos, apostas, apuração, encerramento, cassino e mais.' },
];

const chaves_abas = abas.map((aba) => aba.chave);
const chaves_secoes = secoes_operacao.map((secao) => secao.chave);

// aba e seção da Operação vindas do endereço (contracts/paginas §5); valores fora da lista são ignorados
const da_url = () => {
    const parametros = new URLSearchParams(window.location.search);

    return {
        aba: chaves_abas.includes(parametros.get('aba')) ? parametros.get('aba') : 'banca',
        secao: chaves_secoes.includes(parametros.get('secao')) ? parametros.get('secao') : null,
    };
};

const gravar_na_url = (aba, secao) => {
    const parametros = new URLSearchParams({ aba, ...(aba === 'operacao' && secao ? { secao } : {}) });
    window.history.replaceState(window.history.state, '', `?${parametros}`);
};

// aviso curto no canto, sem bloquear a tela
const avisar = (texto) => Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: texto, showConfirmButton: false, timer: 2500 });

const TEXTO_PENDENTE = 'Há alterações não salvas. Deseja sair sem salvar?';

// tela "Configurações" do painel (spec 021; a antiga "Configuração", /adm/config): abas Banca,
// Visual, Regras, Integrações e Operação. Cada aba ou seção salva só os seus campos; trocar de aba
// ou sair com alterações pendentes pede confirmação (FR-003)
export default function PanelSettings() {
    const { nome_sistema, tema } = usePage().props;
    const { usuario: logado, carregando } = usePainel();
    const pode = (permissao) => Boolean(logado?.permissoes.includes(permissao));
    const pode_ver = pode('configuracoes.editar');
    const pode_mensagens = pode('configuracoes.editar_mensagens');
    const [{ aba, secao }, definir_local] = useState(da_url);
    const [pendencias_operacao, definir_pendencias_operacao] = useState({});

    const banca = useSecaoConfiguracoes({ rota: '/configuracoes/banca', habilitado: pode_ver && aba === 'banca' });
    const regras = useSecaoConfiguracoes({ rota: '/configuracoes/regras', habilitado: pode_ver && aba === 'regras' });

    const mensagens = useSecaoConfiguracoes({ rota: '/configuracoes/mensagens', habilitado: pode_mensagens && aba === 'integracoes' });

    const pendentes = banca.quantidade + regras.quantidade + mensagens.quantidade + Object.values(pendencias_operacao).reduce((soma, quantidade) => soma + quantidade, 0);

    // sem acesso, volta para a Dashboard
    useEffect(() => {
        if (logado && !pode_ver) router.visit('/painel');
    }, [logado]);

    // fechar ou recarregar a aba do navegador com alterações pendentes pergunta antes
    useEffect(() => {
        if (!pendentes) return undefined;

        const avisar_saida = (evento) => {
            evento.preventDefault();
            evento.returnValue = '';
        };

        window.addEventListener('beforeunload', avisar_saida);

        return () => window.removeEventListener('beforeunload', avisar_saida);
    }, [pendentes]);

    const ao_pendencias = useCallback((chave, quantidade) => {
        definir_pendencias_operacao((atuais) => (atuais[chave] === quantidade ? atuais : { ...atuais, [chave]: quantidade }));
    }, []);

    // descarta as alterações de todas as abas (depois da confirmação)
    const descartar = () => {
        banca.desfazer();
        regras.desfazer();
        mensagens.desfazer();
        definir_pendencias_operacao({});
    };

    const mudar_local = async (proximo) => {
        if (pendentes && !(await confirmar(TEXTO_PENDENTE, tema.temas))) return;
        if (pendentes) descartar();

        definir_local(proximo);
        gravar_na_url(proximo.aba, proximo.secao);
    };

    if (carregando || !logado || !pode_ver) {
        return (
            <>
                <Head title={nome_sistema} />
                <Loading><Spinner tamanho={40} cor={tema.temas} /></Loading>
            </>
        );
    }

    const salvar = async (secao_salva, recarregar_props = []) => {
        try {
            if (!(await secao_salva.salvar())) return;

            avisar('Configurações salvas.');
            if (recarregar_props.length) router.reload({ only: recarregar_props });
        } catch (falha) {
            alerta_erro(falha);
        }
    };

    // tema e logo novos aparecem no painel sem recarregar a página
    const recarregar_tema = () => router.reload({ only: ['tema'] });

    const conteudo_banca = () => {
        // campos numa coluna nos cartões estreitos (alinhados); redes sociais em duas
        const secao_banca = (icone, titulo, descricao, campos, colunas = 1) => (
            <SettingsSection icone={icone} titulo={titulo} descricao={descricao} carregando={banca.carregando || (!banca.valores && !banca.erro_carga)} erro={banca.erro_carga} ao_tentar_de_novo={banca.recarregar}>
                {banca.valores && <SettingsForm campos={campos} valores={banca.valores} erros={banca.erros} ao_mudar={banca.mudar} colunas={colunas} />}
            </SettingsSection>
        );

        // duas colunas com a altura de cada cartão: identificação e redes | atendimento e rastreamento;
        // o tema embaixo, na largura toda
        return (
            <>
                <Columns>
                    <div>
                        {secao_banca('badge', 'Identificação', 'Nome do sistema e mensagem do bilhete.', campos_identificacao)}
                        {secao_banca('share', 'Redes sociais', 'Ícones do rodapé do site; vazio = ícone escondido.', campos_redes, 2)}
                    </div>
                    <div>
                        {secao_banca('support_agent', 'Atendimento', 'WhatsApp de atendimento do site.', campos_atendimento)}
                        {secao_banca('analytics', 'Rastreamento', 'Google Tag Manager para medir as campanhas da banca.', campos_rastreamento)}
                    </div>
                </Columns>
                <SettingsSection icone="palette" titulo="Tema" descricao="Cor principal e fundo padrão do site, do painel e dos e-mails." carregando={!banca.valores && !banca.erro_carga} erro={banca.erro_carga}>
                    {banca.valores && (
                        <ThemePicker
                            cores={banca.valores.cores_tema ?? []}
                            valor_cor={banca.valores.cor_tema}
                            valor_fundo={banca.valores.cor_fundo}
                            ao_mudar={banca.mudar}
                        />
                    )}
                </SettingsSection>
                <SaveBar
                    quantidade={banca.quantidade}
                    salvando={banca.salvando}
                    ao_desfazer={banca.desfazer}
                    ao_salvar={() => salvar(banca, ['tema', 'contatos', 'nome_sistema', 'rastreamento'])}
                />
            </>
        );
    };

    // logo e avisos lado a lado; banners (imagens largas) na linha toda
    const conteudo_visual = () => (
        <Grid>
            <SettingsSection icone="image" titulo="Logo" descricao="A logo também gera os ícones do aplicativo e da aba do navegador.">
                <LogoUploader logo={tema.logo} icones={tema.icones} ao_enviar={recarregar_tema} />
            </SettingsSection>
            <SettingsSection icone="campaign" titulo="Avisos da banca (pop-up)" descricao="Imagem que aparece ao abrir o site até o apostador marcar como lido.">
                <NoticesManager />
            </SettingsSection>
            <Wide>
                <SettingsSection icone="view_carousel" titulo="Banners do slide" descricao="Carrossel da página inicial (1280x405).">
                    <BannersManager />
                </SettingsSection>
            </Wide>
        </Grid>
    );

    const conteudo_regras = () => (
        <SettingsSection
            icone="gavel"
            titulo="Regras da banca"
            descricao="Aparecem no topo da página de regras do site."
            carregando={regras.carregando || (!regras.valores && !regras.erro_carga)}
            erro={regras.erro_carga}
            ao_tentar_de_novo={regras.recarregar}
        >
            {regras.valores && (
                <>
                    <RulesEditor valor={regras.valores.regras} erro={regras.erros.regras} ao_mudar={(html) => regras.mudar('regras', html)} />
                    <SaveBar quantidade={regras.quantidade} salvando={regras.salvando} ao_desfazer={regras.desfazer} ao_salvar={() => salvar(regras)} />
                </>
            )}
        </SettingsSection>
    );

    const conteudo_integracoes = () => (
        <Stack>
            <Grid>
                <SettingsSection icone="account_balance_wallet" titulo="Mercado Pago" descricao="Conta que recebe os depósitos e paga os saques.">
                    <MercadoPagoCard />
                </SettingsSection>
                <SettingsSection icone="smart_toy" titulo="Robô do WhatsApp" descricao="Número da banca conectado pela Evolution API, para as mensagens automáticas.">
                    <WhatsAppBotCard />
                </SettingsSection>
            </Grid>
            {pode_mensagens && (
                <SettingsSection
                    icone="forum"
                    titulo="Mensagens"
                    descricao="Quem recebe os avisos e por onde cada mensagem sai."
                    carregando={mensagens.carregando || (!mensagens.valores && !mensagens.erro_carga)}
                    erro={mensagens.erro_carga}
                    ao_tentar_de_novo={mensagens.recarregar}
                >
                    {mensagens.valores && <MessagesCard secao={mensagens} ao_salvar={() => salvar(mensagens)} />}
                </SettingsSection>
            )}
        </Stack>
    );

    const conteudo_operacao = () => (
        <Stack>
            {secoes_operacao.map((definicao) => (
                <SettingsFormCard
                    key={definicao.chave}
                    definicao={definicao}
                    aberta={secao === definicao.chave}
                    ao_alternar={() => mudar_local({ aba, secao: secao === definicao.chave ? null : definicao.chave })}
                    ao_pendencias={ao_pendencias}
                />
            ))}
        </Stack>
    );

    const conteudos = {
        banca: conteudo_banca, visual: conteudo_visual, regras: conteudo_regras, integracoes: conteudo_integracoes, operacao: conteudo_operacao,
    };

    return (
        <PanelShell usuario={logado} titulo="Configurações">
            <Head title={`Configurações - ${nome_sistema}`} />
            <Section>
                <TabsArea>
                    <PanelTabs abas={abas} ativa={aba} ao_trocar={(chave) => chave !== aba && mudar_local({ aba: chave, secao: null })} />
                </TabsArea>
                <Intro>{abas.find((item) => item.chave === aba).descricao}</Intro>
                {conteudos[aba]()}
            </Section>
        </PanelShell>
    );
}
