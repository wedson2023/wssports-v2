import { useEffect, useState } from 'react';

import { atalho_do_periodo, atalhos_periodo, hoje, periodo_do_atalho, texto_periodo, validar_periodo } from '../../utils/periods';
import { Apply, Container, Custom, Error, Field, Input, Label, Period, Shortcut, Shortcuts } from './styles';

// filtro de período do caixa (spec 017, contracts/paginas §2): atalhos Hoje, Ontem, Esta semana,
// Este mês e Personalizado (De/Até com Aplicar e o erro no campo) e o texto do período em vigor
export default function PeriodFilter({ periodo, ao_mudar }) {
    const [atalho, definir_atalho] = useState(() => atalho_do_periodo(periodo));
    const [rascunho, definir_rascunho] = useState(periodo);
    const [erro, definir_erro] = useState(null);

    // período trocado por fora (voltar do navegador)
    useEffect(() => {
        definir_rascunho(periodo);

        if (atalho !== 'personalizado') definir_atalho(atalho_do_periodo(periodo));
    }, [periodo.de, periodo.ate]);

    const escolher = (chave) => {
        definir_atalho(chave);
        definir_erro(null);

        if (chave !== 'personalizado') ao_mudar(periodo_do_atalho(chave));
    };

    const aplicar = () => {
        const mensagem = validar_periodo(rascunho);
        definir_erro(mensagem);

        if (!mensagem) ao_mudar(rascunho);
    };

    return (
        <Container>
            <Shortcuts role="group" aria-label="Período">
                {atalhos_periodo.map((item) => (
                    <Shortcut key={item.chave} type="button" aria-pressed={atalho === item.chave} onClick={() => escolher(item.chave)}>
                        {item.rotulo}
                    </Shortcut>
                ))}
            </Shortcuts>
            {atalho === 'personalizado' && (
                <Custom>
                    <Field>
                        <Label htmlFor="periodo_de">De</Label>
                        <Input id="periodo_de" type="date" max={hoje()} value={rascunho.de} $erro={Boolean(erro)} onChange={(evento) => definir_rascunho({ ...rascunho, de: evento.target.value })} />
                    </Field>
                    <Field>
                        <Label htmlFor="periodo_ate">Até</Label>
                        <Input id="periodo_ate" type="date" max={hoje()} value={rascunho.ate} $erro={Boolean(erro)} onChange={(evento) => definir_rascunho({ ...rascunho, ate: evento.target.value })} />
                    </Field>
                    <Apply type="button" onClick={aplicar}>Aplicar</Apply>
                    {erro && <Error role="alert">{erro}</Error>}
                </Custom>
            )}
            <Period>
                <i className="material-icons" aria-hidden="true">event</i>
                {texto_periodo(periodo)}
            </Period>
        </Container>
    );
}
