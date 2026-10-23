import { useEffect, useState } from 'react';

import CashModal, {
    Button, erro_do_campo, Error, Field, Fields, GhostButton, Input, Label, ler_valor, Summary, SummaryRow, Textarea,
} from '../CashModal';
import api from '../../utils/api';
import { alerta_erro } from '../../utils/alerts';
import { formatar_valor } from '../../utils/charts';
import { Counter, Type, Types } from './styles';

const carteiras = [
    { chave: 'Saldo', rotulo: 'Saldo', campo: 'saldo', icone: 'account_balance_wallet' },
    { chave: 'Promoção esportes', rotulo: 'Promoção esportes', campo: 'saldo_promocao_esportes', icone: 'sports_soccer' },
    { chave: 'Promoção cassino', rotulo: 'Promoção cassino', campo: 'saldo_promocao_cassino', icone: 'casino' },
];

const tipos = [
    { chave: 'Crédito', icone: 'add_circle_outline', explicacao: 'Soma o valor na carteira.' },
    { chave: 'Débito', icone: 'remove_circle_outline', explicacao: 'Tira o valor da carteira.' },
];

const LIMITE_MOTIVO = 255;

const vazio = { carteira: 'Saldo', tipo: 'Crédito', valor: '', observacao: '', senha: '' };

// soma de centavos sem ponto flutuante na exibição: "10.00" + "5.50"
const somar = (a, b, sinal) => ((Math.round(Number(a) * 100) + sinal * Math.round(Number(b) * 100)) / 100).toFixed(2);

// ajuste manual de saldo (spec 019, FR-018, contracts/paginas §5): carteira, crédito ou débito,
// valor, motivo e a senha de quem ajusta; mostra o saldo atual e o saldo depois
export default function BalanceAdjustModal({ cliente, token, ao_fechar, ao_concluir }) {
    const aberto = Boolean(cliente);
    const [formulario, definir_formulario] = useState(vazio);
    const [erros, definir_erros] = useState({});
    const [salvando, definir_salvando] = useState(false);

    useEffect(() => {
        if (!aberto) return;

        definir_formulario(vazio);
        definir_erros({});
    }, [cliente?.id]);

    const mudar = (campo, valor) => definir_formulario((atual) => ({ ...atual, [campo]: valor }));

    const carteira = carteiras.find((item) => item.chave === formulario.carteira);
    const saldo_atual = cliente?.[carteira.campo] ?? '0.00';
    const valor = ler_valor(formulario.valor);
    const saldo_depois = valor ? somar(saldo_atual, valor, formulario.tipo === 'Crédito' ? 1 : -1) : null;

    const ajustar = async () => {
        if (!valor || Number(valor) <= 0) {
            definir_erros({ valor: 'Informe um valor maior que zero, com até duas casas.' });
            return;
        }

        definir_salvando(true);

        try {
            await api.post(`/clientes/${cliente.id}/transacoes`, { ...formulario, valor }, { headers: { Authorization: `Bearer ${token}` } });
            ao_concluir(`${formulario.tipo} de ${formatar_valor(valor)} lançado em ${formulario.carteira}.`);
        } catch (erro) {
            const do_campo = Object.fromEntries(Object.keys(vazio).map((campo) => [campo, erro_do_campo(erro, campo)]).filter(([, mensagem]) => mensagem));

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
            titulo={cliente ? `Ajustar saldo · ${cliente.nome}` : 'Ajustar saldo'}
            descricao="Crédito ou débito manual no saldo do cliente."
            ao_fechar={ao_fechar}
            rodape={(
                <>
                    <GhostButton type="button" onClick={ao_fechar}>Cancelar</GhostButton>
                    <Button type="button" disabled={salvando} onClick={ajustar}>{salvando ? 'Ajustando…' : 'Ajustar'}</Button>
                </>
            )}
        >
            <Types role="radiogroup" aria-label="Carteira">
                {carteiras.map((item) => (
                    <Type key={item.chave} type="button" role="radio" aria-checked={formulario.carteira === item.chave} onClick={() => mudar('carteira', item.chave)}>
                        <i className="material-icons" aria-hidden="true">{item.icone}</i>
                        <strong>{item.rotulo}</strong>
                        <span>{formatar_valor(cliente?.[item.campo] ?? '0')}</span>
                    </Type>
                ))}
            </Types>
            <Types role="radiogroup" aria-label="Tipo">
                {tipos.map((tipo) => (
                    <Type key={tipo.chave} type="button" role="radio" aria-checked={formulario.tipo === tipo.chave} onClick={() => mudar('tipo', tipo.chave)}>
                        <i className="material-icons" aria-hidden="true">{tipo.icone}</i>
                        <strong>{tipo.chave}</strong>
                        <span>{tipo.explicacao}</span>
                    </Type>
                ))}
            </Types>
            <Fields>
                <Field>
                    <Label htmlFor="ajuste_valor">Valor (R$)</Label>
                    <Input id="ajuste_valor" inputMode="decimal" placeholder="0,00" value={formulario.valor} $erro={Boolean(erros.valor)} onChange={(evento) => mudar('valor', evento.target.value)} />
                    {erros.valor && <Error>{erros.valor}</Error>}
                </Field>
                <Field>
                    <Label htmlFor="ajuste_senha">Sua senha</Label>
                    <Input id="ajuste_senha" type="password" autoComplete="current-password" value={formulario.senha} $erro={Boolean(erros.senha)} onChange={(evento) => mudar('senha', evento.target.value)} />
                    {erros.senha && <Error>{erros.senha}</Error>}
                </Field>
                <Field $inteiro>
                    <Label htmlFor="ajuste_motivo">Motivo</Label>
                    <Textarea id="ajuste_motivo" maxLength={LIMITE_MOTIVO} value={formulario.observacao} onChange={(evento) => mudar('observacao', evento.target.value)} />
                    <Counter>{formulario.observacao.length}/{LIMITE_MOTIVO}</Counter>
                    {erros.observacao && <Error>{erros.observacao}</Error>}
                </Field>
            </Fields>
            <Summary>
                <SummaryRow>
                    <dt>{`Saldo atual (${formulario.carteira})`}</dt>
                    <dd>{formatar_valor(saldo_atual)}</dd>
                </SummaryRow>
                <SummaryRow>
                    <dt>Saldo depois</dt>
                    <dd>{saldo_depois === null ? '—' : formatar_valor(saldo_depois)}</dd>
                </SummaryRow>
            </Summary>
            {(erros.carteira || erros.tipo) && <Error>{erros.carteira ?? erros.tipo}</Error>}
        </CashModal>
    );
}
