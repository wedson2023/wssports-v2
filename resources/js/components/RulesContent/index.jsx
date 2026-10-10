import { usePage } from '@inertiajs/react';

import BetLimits from '../BetLimits';
import BonusRules from '../BonusRules';
import MarketRules from '../MarketRules';
import RulesBlock from '../RulesBlock';
import { BackLink, BankRule, BankRules, Help, Logo, LogoArea, Page, TopBar, TopTitle } from './styles';

// regulamento da banca em blocos, como no sistema antigo (spec 006, FR-021a): regras da banca,
// regras de bônus, regras de apostas e limites de aposta; barra com "Voltar" e atalho do WhatsApp
// aprovados na spec 005
export default function RulesContent({ regras, regras_bonus = [], limites_aposta = null }) {
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

            <LogoArea>
                <Logo src={tema.logo} alt={nome_sistema} />
            </LogoArea>

            {/* texto do administrador: cada linha é um parágrafo, sempre como texto (sem HTML) */}
            {regras.length > 0 && (
                <RulesBlock>
                    <BankRules>
                        {regras.map((paragrafo, indice) => (
                            <BankRule key={`${indice}-${paragrafo}`}>
                                <i className="material-icons" aria-hidden="true">check_circle</i>
                                <span>{paragrafo}</span>
                            </BankRule>
                        ))}
                    </BankRules>
                </RulesBlock>
            )}

            <BonusRules promocoes={regras_bonus} />
            <MarketRules />
            <BetLimits limites={limites_aposta} />

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
