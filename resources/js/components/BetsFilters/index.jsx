import { useEffect, useState } from 'react';

import CashModal, { Button, Error, Field, GhostButton, Input, Label, ler_valor, valor_para_campo } from '../CashModal';
import SellerPicker from '../SellerPicker';
import useTelaMobile from '../../hooks/useTelaMobile';
import { Chip, Chips, Group, GroupTitle, Panel } from './styles';

// opções dos filtros (contracts/paginas §3); os valores são os da API
export const opcoes_resultado = [
    { valor: 'Aguardando', rotulo: 'Aguardando' },
    { valor: 'Vencedor', rotulo: 'Premiadas' },
    { valor: 'Perdedor', rotulo: 'Perdidas' },
    { valor: 'Devolvido', rotulo: 'Devolvidas' },
    { valor: 'Encerrada', rotulo: 'Encerradas' },
];

export const opcoes_forma_pagamento = ['Dinheiro', 'Saldo', 'Promoção esportes'];

const vazio = { resultado: null, vendedor: null, forma_pagamento: null, premio_minimo: null };

// filtros da lista de apostas (spec 018, FR-010): resultado, vendedor (não para o Vendedor), forma
// de pagamento e prêmio mínimo. No computador, painel abaixo do cabeçalho que aplica a cada mudança;
// no celular, modal de tela cheia com Limpar e Aplicar
export default function BetsFilters({ aberto, filtros, aba, e_vendedor, token, ao_aplicar, ao_fechar }) {
    const e_mobile = useTelaMobile();
    const atuais = { resultado: filtros.resultado, vendedor: filtros.vendedor, forma_pagamento: filtros.forma_pagamento, premio_minimo: filtros.premio_minimo };
    const [rascunho, definir_rascunho] = useState(atuais);
    const [texto_premio, definir_texto_premio] = useState(valor_para_campo(filtros.premio_minimo ?? ''));
    const [erro_premio, definir_erro_premio] = useState(null);

    // ao abrir, o rascunho parte dos filtros em vigor
    useEffect(() => {
        if (!aberto) return;

        definir_rascunho(atuais);
        definir_texto_premio(valor_para_campo(filtros.premio_minimo ?? ''));
        definir_erro_premio(null);
    }, [aberto]);

    // no computador cada mudança já vale; no celular, só ao tocar em Aplicar
    const alterar = (mudanca) => {
        const proximo = { ...rascunho, ...mudanca };
        definir_rascunho(proximo);

        if (!e_mobile) ao_aplicar(mudanca);
    };

    const ler_premio = () => {
        if (!texto_premio.trim()) {
            definir_erro_premio(null);
            return { ok: true, valor: null };
        }

        const valor = ler_valor(texto_premio);

        if (valor === null || Number(valor) <= 0) {
            definir_erro_premio('Informe um valor maior que zero, com até duas casas.');
            return { ok: false };
        }

        definir_erro_premio(null);

        return { ok: true, valor };
    };

    const aplicar_premio = () => {
        const { ok, valor } = ler_premio();

        if (ok && valor !== rascunho.premio_minimo) alterar({ premio_minimo: valor });
    };

    const aplicar_tudo = () => {
        const { ok, valor } = ler_premio();

        if (!ok) return;

        ao_aplicar({ ...rascunho, premio_minimo: valor });
        ao_fechar();
    };

    const limpar = () => {
        definir_rascunho(vazio);
        definir_texto_premio('');
        definir_erro_premio(null);

        if (!e_mobile) ao_aplicar(vazio);
    };

    const campos = (
        <>
            {aba !== 'canceladas' && (
                <Group>
                    <GroupTitle>Resultado</GroupTitle>
                    <Chips role="group" aria-label="Resultado">
                        {opcoes_resultado.map((opcao) => (
                            <Chip
                                key={opcao.valor}
                                type="button"
                                aria-pressed={rascunho.resultado === opcao.valor}
                                onClick={() => alterar({ resultado: rascunho.resultado === opcao.valor ? null : opcao.valor })}
                            >
                                {opcao.rotulo}
                            </Chip>
                        ))}
                    </Chips>
                </Group>
            )}
            {!e_vendedor && (
                <Field>
                    <Label htmlFor="filtro_vendedor">Vendedor</Label>
                    <SellerPicker id="filtro_vendedor" valor={rascunho.vendedor} token={token} ao_escolher={(vendedor) => alterar({ vendedor })} />
                </Field>
            )}
            <Group>
                <GroupTitle>Forma de pagamento</GroupTitle>
                <Chips role="group" aria-label="Forma de pagamento">
                    {opcoes_forma_pagamento.map((forma) => (
                        <Chip
                            key={forma}
                            type="button"
                            aria-pressed={rascunho.forma_pagamento === forma}
                            onClick={() => alterar({ forma_pagamento: rascunho.forma_pagamento === forma ? null : forma })}
                        >
                            {forma}
                        </Chip>
                    ))}
                </Chips>
            </Group>
            <Field>
                <Label htmlFor="filtro_premio">Prêmio mínimo (R$)</Label>
                <Input
                    id="filtro_premio"
                    inputMode="decimal"
                    value={texto_premio}
                    placeholder="0,00"
                    onChange={(evento) => definir_texto_premio(evento.target.value)}
                    onBlur={() => !e_mobile && aplicar_premio()}
                    onKeyDown={(evento) => evento.key === 'Enter' && !e_mobile && aplicar_premio()}
                />
                {erro_premio && <Error role="alert">{erro_premio}</Error>}
            </Field>
        </>
    );

    if (e_mobile) {
        return (
            <CashModal
                aberto={aberto}
                icone="filter_list"
                titulo="Filtros"
                descricao="Escolha o que quer ver na lista de apostas."
                ao_fechar={ao_fechar}
                rodape={(
                    <>
                        <GhostButton type="button" onClick={limpar}>Limpar</GhostButton>
                        <Button type="button" onClick={aplicar_tudo}>Aplicar</Button>
                    </>
                )}
            >
                {campos}
            </CashModal>
        );
    }

    if (!aberto) return null;

    return (
        <Panel aria-label="Filtros">
            {campos}
            <GhostButton type="button" onClick={limpar}>Limpar filtros</GhostButton>
        </Panel>
    );
}
