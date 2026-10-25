import { Link, usePage } from '@inertiajs/react';
import { Container, Copy, Icons, Links, Logo, ResponsibleImage, SocialIcon, Text, TextLink, Version } from './styles';

const redes_sociais = [
    { chave: 'instagram', imagem: '/images/instagran.png' },
    { chave: 'youtube', imagem: '/images/youtube.png' },
    { chave: 'twitter', imagem: '/images/twitter.png' },
    { chave: 'facebook', imagem: '/images/facebook.png' },
];

const abrir = (url) => window.open(url, '_blank', 'noopener');

// rodapé do sistema antigo: logo, jogo responsável, redes sociais, links, copyright e versão
// (FR-046); a versão vem do servidor (FR-050b)
export default function Footer() {
    const { tema, contatos, nome_sistema, versao } = usePage().props;

    return (
        <Container>
            <Logo src={tema.logo} alt="" />
            <Text>Jogue com responsabilidade</Text>
            {/* sem link de jogo responsável configurado, a imagem fica sem clique (spec 021, FR-007) */}
            <ResponsibleImage
                src="/images/gordon_moody.png"
                title={contatos.jogo_responsavel ?? undefined}
                onClick={contatos.jogo_responsavel ? () => abrir(contatos.jogo_responsavel) : undefined}
                alt=""
            />
            <Icons>
                {redes_sociais.filter(({ chave }) => contatos[chave]).map(({ chave, imagem }) => (
                    <SocialIcon key={chave} src={imagem} title="Clique para acessar sua rede social." onClick={() => abrir(contatos[chave])} alt="" />
                ))}
            </Icons>
            <Links>
                {/* "Quem somos" ainda não tem tela no sistema novo */}
                <TextLink as="span">Quem somos</TextLink>
                <TextLink as={Link} href="/cadastro-afiliado">Afiliados</TextLink>
                <TextLink as={Link} href="/regras">Termos e condições</TextLink>
            </Links>
            <Copy>
                {new Date().getFullYear()} © {nome_sistema?.toUpperCase()}. Todos os direitos reservados.
                <Version>V.: {versao?.slice(0, 8)}</Version>
            </Copy>
        </Container>
    );
}
