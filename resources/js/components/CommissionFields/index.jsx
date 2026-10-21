import { Error, Field, Grid, Input, Label, Section, SectionTitle } from './styles';

const faixas = Array.from({ length: 12 }, (_, indice) => indice + 1);

// comissões do vendedor em %, de 1 a 12 jogos, do pré-jogo e do ao vivo, e a comissão por prêmio
// ("Comissões vendedor" e "Comissões vendedor ao vivo" do modal antigo); vazio = não alterar
export default function CommissionFields({ valores, ao_mudar, somente_leitura = false, erros = {} }) {
    const campo = (chave, rotulo, titulo) => (
        <Field key={chave} title={titulo}>
            <Label htmlFor={`comissao-${chave}`}>{rotulo}</Label>
            <Input
                id={`comissao-${chave}`}
                type="number"
                inputMode="decimal"
                min="0"
                max="100"
                step="0.01"
                placeholder="%"
                value={valores[chave] ?? ''}
                disabled={somente_leitura}
                $erro={Boolean(erros[chave])}
                onChange={(evento) => ao_mudar(chave, evento.target.value)}
            />
            {erros[chave] && <Error>{erros[chave]}</Error>}
        </Field>
    );

    const grade = (prefixo) => faixas.map((faixa) => campo(
        `${prefixo}${faixa}`,
        faixa === 12 ? '12 ou mais' : `${faixa} ${faixa === 1 ? 'jogo' : 'jogos'}`,
        `Porcentagem da aposta com ${faixa === 12 ? '12 ou mais palpites' : `${faixa} palpite${faixa === 1 ? '' : 's'}`}.`,
    ));

    return (
        <>
            <Section>
                <SectionTitle>Pré-jogo</SectionTitle>
                <Grid>{grade('comissao_pre_jogo_')}</Grid>
            </Section>
            <Section>
                <SectionTitle>Ao vivo</SectionTitle>
                <Grid>{grade('comissao_ao_vivo_')}</Grid>
            </Section>
            <Section>
                <SectionTitle>Prêmio</SectionTitle>
                <Grid>
                    {campo('comissao_por_premio', 'Comissão por prêmio', 'Porcentagem para o vendedor em cima do prêmio do bilhete.')}
                </Grid>
            </Section>
        </>
    );
}
