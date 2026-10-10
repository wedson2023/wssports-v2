import { usePage } from '@inertiajs/react';
import {
    BackLink, Card, Help, Hero, Logo, Page, Rule, RuleList, RuleNumber, RuleText, Subtitle, Title, TopBar, TopTitle,
} from './styles';

// regulamento da banca: regras numeradas em texto simples (FR-017)
export default function RulesContent({ regras }) {
    const { tema, contatos, nome_sistema } = usePage().props;

    const falar_no_whatsapp = () => {
        window.location.href = `https://api.whatsapp.com/send?phone=${contatos.whatsapp}&text=${encodeURIComponent('Olá! Tenho uma dúvida sobre as regras.')}`;
    };

    return (
        <Page>
            <TopBar>
                <BackLink href="/" aria-label="Voltar para os jogos">
                    <i className="material-icons">arrow_back</i>
                    Voltar
                </BackLink>
                <TopTitle>Regulamento</TopTitle>
            </TopBar>

            <Hero>
                <Logo src={tema.logo} alt={nome_sistema} />
                <Title>Regras e termos de uso</Title>
                <Subtitle>Leia com atenção antes de fazer sua aposta.</Subtitle>
            </Hero>

            <Card>
                <RuleList>
                    {regras.map((regra, indice) => (
                        <Rule key={regra}>
                            <RuleNumber aria-hidden="true">{indice + 1}</RuleNumber>
                            <RuleText>{regra}</RuleText>
                        </Rule>
                    ))}
                </RuleList>
            </Card>

            {contatos.whatsapp && (
                <Help>
                    Ficou com alguma dúvida?
                    <br />
                    <button type="button" onClick={falar_no_whatsapp}>
                        <i className="material-icons">chat</i>
                        Fale com a gente pelo WhatsApp
                    </button>
                </Help>
            )}
        </Page>
    );
}
