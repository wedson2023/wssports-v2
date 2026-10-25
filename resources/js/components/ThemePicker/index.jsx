import { Background, Backgrounds, Group, Label, Layout, Swatch, Swatches } from './styles';

// fundos padrão do site: escuro (sistema antigo) ou claro
const fundos = [
    { cor: '#000000', nome: 'Escuro' },
    { cor: '#FFFFFF', nome: 'Claro' },
];

// escolha do tema da banca (spec 021, FR-006): as 6 cores principais do sistema antigo em amostras
// redondas e o fundo padrão em dois cartões; o apostador ainda pode trocar claro/escuro no aparelho
export default function ThemePicker({ cores, valor_cor, valor_fundo, ao_mudar }) {
    return (
        <Layout>
            <Group>
                <Label id="tema_cor">Cor principal</Label>
                <Swatches role="radiogroup" aria-labelledby="tema_cor">
                    {cores.map(({ cor, nome }) => (
                        <Swatch
                            key={cor}
                            type="button"
                            role="radio"
                            aria-checked={valor_cor === cor}
                            $cor={cor}
                            $marcada={valor_cor === cor}
                            onClick={() => ao_mudar('cor_tema', cor)}
                        >
                            <span>{valor_cor === cor && <i className="material-icons" aria-hidden="true">check</i>}</span>
                            {nome}
                        </Swatch>
                    ))}
                </Swatches>
            </Group>
            <Group>
                <Label id="tema_fundo">Fundo padrão</Label>
                <Backgrounds role="radiogroup" aria-labelledby="tema_fundo">
                    {fundos.map(({ cor, nome }) => (
                        <Background
                            key={cor}
                            type="button"
                            role="radio"
                            aria-checked={valor_fundo?.toUpperCase() === cor}
                            $fundo={cor}
                            $marcada={valor_fundo?.toUpperCase() === cor}
                            onClick={() => ao_mudar('cor_fundo', cor)}
                        >
                            <span aria-hidden="true" />
                            {nome}
                        </Background>
                    ))}
                </Backgrounds>
            </Group>
        </Layout>
    );
}
