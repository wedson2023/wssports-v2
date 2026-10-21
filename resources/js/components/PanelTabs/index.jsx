import { Count, Tab, Tabs } from './styles';

// abas do painel (Tabs de screens/adm/components/components do antigo): Ativos/Lixeira nas listas
// e as abas dos modais; abas = [{ chave, rotulo, icone?, contador? }]
export default function PanelTabs({ abas, ativa, ao_trocar }) {
    return (
        <Tabs role="tablist">
            {abas.map((aba) => (
                <Tab
                    key={aba.chave}
                    type="button"
                    role="tab"
                    aria-selected={aba.chave === ativa}
                    $ativa={aba.chave === ativa}
                    onClick={() => ao_trocar(aba.chave)}
                >
                    {aba.icone && <i className="material-icons" aria-hidden="true">{aba.icone}</i>}
                    {aba.rotulo}
                    {aba.contador !== undefined && <Count>{aba.contador}</Count>}
                </Tab>
            ))}
        </Tabs>
    );
}
