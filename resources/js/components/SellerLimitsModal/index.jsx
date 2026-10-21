import { useEffect, useState } from 'react';

import CashModal, {
    Button, erro_do_campo, Error, Field, Fields, GhostButton, Hint, Input, Label, ler_valor,
} from '../CashModal';
import api from '../../utils/api';
import { alerta_erro } from '../../utils/alerts';
import { formatar_valor } from '../../utils/charts';
import { Current, Preview } from './styles';

const limites = [
    { chave: 'geral', rotulo: 'Limite geral' },
    { chave: 'duplo', rotulo: 'Limite duplo' },
    { chave: 'simples', rotulo: 'Limite simples' },
];

// ajustar os limites do vendedor pelo caixa (spec 017, FR-028): soma ou subtrai (negativo reduz) em
// cada limite, com a prévia do novo valor; nenhum limite pode ficar abaixo de zero
export default function SellerLimitsModal({ vendedor, token, ao_fechar, ao_concluir }) {
    const aberto = Boolean(vendedor);
    const [ajustes, definir_ajustes] = useState({ geral: '', duplo: '', simples: '' });
    const [erros, definir_erros] = useState({});
    const [salvando, definir_salvando] = useState(false);

    useEffect(() => {
        definir_ajustes({ geral: '', duplo: '', simples: '' });
        definir_erros({});
    }, [vendedor?.id]);

    const atual = (chave) => Number(vendedor?.limites?.[chave] ?? 0);

    // novo valor do limite, ou o erro do campo
    const previa = (chave) => {
        const texto = ajustes[chave];

        if (!texto.trim()) return { novo: atual(chave), erro: null };

        const valor = ler_valor(texto, { permitir_negativo: true });

        if (valor === null) return { novo: null, erro: 'Informe um valor válido, com até duas casas.' };

        const novo = atual(chave) + Number(valor);

        return { novo, erro: novo < 0 ? `O limite ficaria negativo; o disponível agora é ${formatar_valor(atual(chave).toFixed(2))}.` : null };
    };

    const salvar = async () => {
        const locais = Object.fromEntries(limites.map(({ chave }) => [chave, previa(chave).erro]).filter(([, erro]) => erro));
        const corpo = Object.fromEntries(limites.map(({ chave }) => [chave, ler_valor(ajustes[chave], { permitir_negativo: true })]).filter(([, valor]) => valor && Number(valor) !== 0));

        if (Object.keys(locais).length) {
            definir_erros(locais);
            return;
        }

        if (!Object.keys(corpo).length) {
            definir_erros({ geral: 'Informe ao menos um valor para ajustar.' });
            return;
        }

        definir_salvando(true);

        try {
            await api.post(`/caixa/vendedores/${vendedor.id}/limites`, corpo, { headers: { Authorization: `Bearer ${token}` } });
            ao_concluir(`Limites de ${vendedor.nome} atualizados.`);
        } catch (erro) {
            const do_campo = Object.fromEntries(limites.map(({ chave }) => [chave, erro_do_campo(erro, chave)]).filter(([, mensagem]) => mensagem));

            if (Object.keys(do_campo).length) definir_erros(do_campo);
            else alerta_erro(erro);
        } finally {
            definir_salvando(false);
        }
    };

    return (
        <CashModal
            aberto={aberto}
            icone="attach_money"
            titulo={vendedor ? `Ajustar limite · ${vendedor.nome}` : 'Ajustar limite'}
            descricao="Some (valor positivo) ou subtraia (valor negativo) dos limites de venda do vendedor."
            ao_fechar={ao_fechar}
            largura={520}
            rodape={(
                <>
                    <GhostButton type="button" onClick={ao_fechar}>Cancelar</GhostButton>
                    <Button type="button" disabled={salvando} onClick={salvar}>{salvando ? 'Salvando…' : 'Salvar'}</Button>
                </>
            )}
        >
            <Hint>Os limites diminuem a cada aposta do vendedor; o valor mostrado é o disponível agora.</Hint>
            <Fields>
                {limites.map(({ chave, rotulo }) => {
                    const { novo, erro } = previa(chave);

                    return (
                        <Field key={chave} $inteiro>
                            <Label htmlFor={`limite_${chave}`}>{rotulo}</Label>
                            <Current>
                                <span>Atual: {formatar_valor(atual(chave).toFixed(2))}</span>
                                {novo !== null && ajustes[chave].trim() && <Preview $negativo={novo < 0}>Novo: {formatar_valor(novo.toFixed(2))}</Preview>}
                            </Current>
                            <Input
                                id={`limite_${chave}`}
                                inputMode="decimal"
                                placeholder="Ex.: 500,00 ou -200,00"
                                value={ajustes[chave]}
                                $erro={Boolean(erro || erros[chave])}
                                onChange={(evento) => definir_ajustes({ ...ajustes, [chave]: evento.target.value })}
                            />
                            {(erro || erros[chave]) && <Error>{erro || erros[chave]}</Error>}
                        </Field>
                    );
                })}
            </Fields>
        </CashModal>
    );
}
