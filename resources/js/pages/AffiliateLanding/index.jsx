import { useCallback, useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';

import AffiliateRecoveryModal from '../../components/AffiliateRecoveryModal';
import AffiliateSignupModal from '../../components/AffiliateSignupModal';
import AuthModal from '../../components/AuthModal';
import useSessao from '../../hooks/useSessao';
import { Button, Buttons, Container, Description, Header, Image, Logo, RecoveryLink, Text, Title } from './styles';

// página pública de afiliados (/cadastro-afiliado, spec 012): igual à screens/new-affiliated do
// antigo; "Entrar" abre o mesmo modal de entrada do site (FR-021)
export default function AffiliateLanding() {
    const { tema, nome_sistema } = usePage().props;
    const { sessao } = useSessao();
    const logado_como_afiliado = sessao?.tipo === 'afiliado';

    const [cadastro_aberto, definir_cadastro_aberto] = useState(false);
    const [recuperacao_aberta, definir_recuperacao_aberta] = useState(false);
    // aba do modal de entrada (entrar | cadastrar) ou null fechado
    const [aba_acesso, definir_aba_acesso] = useState(null);

    const abrir_cadastro = () => definir_cadastro_aberto(true);
    const fechar_cadastro = useCallback(() => definir_cadastro_aberto(false), []);
    const fechar_recuperacao = useCallback(() => definir_recuperacao_aberta(false), []);
    const fechar_acesso = useCallback(() => definir_aba_acesso(null), []);
    const ir_ao_painel = () => router.visit('/afiliados');

    return (
        <Container>
            <Head title={`Afiliados - ${nome_sistema}`} />
            <Header>
                <Logo src={tema.logo} alt={nome_sistema} />
                <Buttons>
                    {logado_como_afiliado ? (
                        <Button type="button" $preenchido onClick={ir_ao_painel}>Meu painel</Button>
                    ) : (
                        <>
                            <Button type="button" $preenchido onClick={abrir_cadastro} title="cadastrar-se">Cadastrar-se</Button>
                            <Button type="button" onClick={() => definir_aba_acesso('entrar')} title="entrar">Entrar</Button>
                        </>
                    )}
                </Buttons>
            </Header>
            <Description>
                <Title>Seja um afiliado!</Title>
                <Text>Junte-se ao programa de afiliados do site de apostas esportivas que mais cresce no Brasil e tenha uma renda extra com comissões excelentes.</Text>
                {logado_como_afiliado
                    ? <Button type="button" onClick={ir_ao_painel}>Meu painel</Button>
                    : <Button type="button" onClick={abrir_cadastro}>Quero me afiliar</Button>}
                {!logado_como_afiliado && (
                    <RecoveryLink type="button" onClick={() => definir_recuperacao_aberta(true)}>Esqueci minha senha</RecoveryLink>
                )}
            </Description>
            <Image src="/images/afiliado.jpg" alt="" />

            <AffiliateSignupModal aberto={cadastro_aberto} ao_fechar={fechar_cadastro} />
            <AffiliateRecoveryModal aberto={recuperacao_aberta} ao_fechar={fechar_recuperacao} ao_concluir={() => definir_aba_acesso('entrar')} />
            <AuthModal aba={aba_acesso} ao_trocar_aba={definir_aba_acesso} ao_fechar={fechar_acesso} />
        </Container>
    );
}
