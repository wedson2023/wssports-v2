import { Header } from './styles';

// item das regras com o cabeçalho de "Regras de apostas" do sistema antigo: ícone, título em caixa
// alta e bordas na cor do tema, seguido do conteúdo (spec 006, FR-025)
export default function RuleItem({ titulo, children }) {
    return (
        <div>
            <Header>
                <i className="material-icons" aria-hidden="true">directions_run</i>
                <span>{titulo}</span>
            </Header>
            {children}
        </div>
    );
}

export { RuleLine } from './styles';
