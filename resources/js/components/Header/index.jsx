import { Link, usePage } from '@inertiajs/react';

import { formatar_real, para_centavos } from '../../utils/money';
import { Balance, EnterButton, Greeting, Logo, MenuIcon, Nav, RegisterButton } from './styles';

// colunas do grid depois do logo/ícone de menu: com o botão dia/noite (US8) ganha uma coluna
const colunas_padrao = { desktop: '100px 100px', mobile: '100px' };

// para onde a saudação leva cada público (o afiliado continua sem link)
const destino_saudacao = {
    cliente: { href: '/minha-conta', titulo: 'Minha conta' },
    usuario: { href: '/painel', titulo: 'Painel' },
};

// primeiro nome para a saudação do cabeçalho
const primeiro_nome = (nome) => (nome ?? '').trim().split(/\s+/)[0];

// cabeçalho do site: logo (desktop), ícone de menu (mobile), Criar Conta e Entrar (FR-007); com
// sessão, mostra a saudação e Sair
export default function Header({ ao_abrir_menu, extra = null, colunas = colunas_padrao, sessao = null, saldo = null, ao_entrar, ao_cadastrar, ao_sair }) {
    const { tema } = usePage().props;

    return (
        <Nav $colunas={colunas}>
            <Logo src={tema.logo} alt="" />
            <MenuIcon onClick={ao_abrir_menu} title="Menu">menu</MenuIcon>
            {extra}
            {sessao ? (
                <>
                    {/* o apostador vai para a tela de créditos ao tocar na saudação, como no antigo (spec 007);
                        o usuário do painel vai para o painel (spec 014) */}
                    <Greeting
                        as={destino_saudacao[sessao.tipo] ? Link : 'span'}
                        href={destino_saudacao[sessao.tipo]?.href}
                        $link={Boolean(destino_saudacao[sessao.tipo])}
                        title={destino_saudacao[sessao.tipo]?.titulo ?? (sessao.nome ?? '')}
                    >
                        Olá{sessao.nome ? `, ${primeiro_nome(sessao.nome)}` : ''}
                        {saldo !== null && <Balance>R$ {formatar_real(para_centavos(saldo))}</Balance>}
                    </Greeting>
                    <EnterButton role="button" onClick={ao_sair}>Sair</EnterButton>
                </>
            ) : (
                <>
                    <RegisterButton role="button" onClick={ao_cadastrar}>Criar Conta</RegisterButton>
                    <EnterButton role="button" onClick={ao_entrar}>Entrar</EnterButton>
                </>
            )}
        </Nav>
    );
}
