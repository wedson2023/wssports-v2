import { useEffect, useState } from 'react';

import CashModal, {
    Button, erro_do_campo, Error, Field, Fields, GhostButton, Input, Label, ler_valor, Textarea, Warning,
} from '../CashModal';
import api from '../../utils/api';
import { alerta_erro } from '../../utils/alerts';
import { agora_local } from '../../utils/periods';
import { Counter, Type, Types } from './styles';

const tipos = [
    { chave: 'Suprimento', icone: 'redo', explicacao: 'Dinheiro entregue ao vendedor: aumenta o que ele deve.' },
    { chave: 'Retirada', icone: 'undo', explicacao: 'Dinheiro recolhido do vendedor: diminui o que ele deve.' },
];

const LIMITE_OBSERVACAO = 500;

// lançamento no caixa do vendedor (spec 017, FR-014, FR-014a): Suprimento ou Retirada, valor,
// data e hora (padrão agora) e observação. Vendedor inativo ou excluído só recebe Retirada
export default function CashEntryModal({ vendedor, token, ao_fechar, ao_concluir }) {
    const aberto = Boolean(vendedor);
    const so_retirada = vendedor && vendedor.situacao !== 'ativo';
    const [formulario, definir_formulario] = useState({ tipo: 'Suprimento', valor: '', ocorrido_em: '', observacao: '' });
    const [erros, definir_erros] = useState({});
    const [salvando, definir_salvando] = useState(false);

    useEffect(() => {
        if (!aberto) return;

        definir_formulario({ tipo: so_retirada ? 'Retirada' : 'Suprimento', valor: '', ocorrido_em: agora_local(), observacao: '' });
        definir_erros({});
    }, [vendedor?.id]);

    const mudar = (campo, valor) => definir_formulario((atual) => ({ ...atual, [campo]: valor }));

    const lancar = async () => {
        const valor = ler_valor(formulario.valor);

        if (!valor || Number(valor) <= 0) {
            definir_erros({ valor: 'Informe um valor maior que zero, com até duas casas.' });
            return;
        }

        definir_salvando(true);

        try {
            await api.post(`/caixa/vendedores/${vendedor.id}/lancamentos`, { ...formulario, valor, observacao: formulario.observacao || null }, { headers: { Authorization: `Bearer ${token}` } });
            ao_concluir(`${formulario.tipo} lançado no caixa de ${vendedor.nome}.`);
        } catch (erro) {
            const campos = ['tipo', 'valor', 'ocorrido_em', 'observacao'];
            const do_campo = Object.fromEntries(campos.map((campo) => [campo, erro_do_campo(erro, campo)]).filter(([, mensagem]) => mensagem));

            if (Object.keys(do_campo).length) definir_erros(do_campo);
            else alerta_erro(erro);
        } finally {
            definir_salvando(false);
        }
    };

    return (
        <CashModal
            aberto={aberto}
            icone="redo"
            titulo={vendedor ? `Lançamento · ${vendedor.nome}` : 'Lançamento'}
            descricao="Suprimento entregue ao vendedor ou retirada do caixa dele."
            ao_fechar={ao_fechar}
            rodape={(
                <>
                    <GhostButton type="button" onClick={ao_fechar}>Cancelar</GhostButton>
                    <Button type="button" disabled={salvando} onClick={lancar}>{salvando ? 'Lançando…' : 'Lançar'}</Button>
                </>
            )}
        >
            {so_retirada && (
                <Warning>
                    <i className="material-icons" aria-hidden="true">info</i>
                    Vendedor inativo: só é possível recolher o que ele deve.
                </Warning>
            )}
            <Types role="radiogroup" aria-label="Tipo do lançamento">
                {tipos.filter((tipo) => !so_retirada || tipo.chave === 'Retirada').map((tipo) => (
                    <Type key={tipo.chave} type="button" role="radio" aria-checked={formulario.tipo === tipo.chave} onClick={() => mudar('tipo', tipo.chave)}>
                        <i className="material-icons" aria-hidden="true">{tipo.icone}</i>
                        <strong>{tipo.chave}</strong>
                        <span>{tipo.explicacao}</span>
                    </Type>
                ))}
            </Types>
            {erros.tipo && <Error>{erros.tipo}</Error>}
            <Fields>
                <Field>
                    <Label htmlFor="lancamento_valor">Valor (R$)</Label>
                    <Input id="lancamento_valor" inputMode="decimal" placeholder="0,00" value={formulario.valor} $erro={Boolean(erros.valor)} onChange={(evento) => mudar('valor', evento.target.value)} />
                    {erros.valor && <Error>{erros.valor}</Error>}
                </Field>
                <Field>
                    <Label htmlFor="lancamento_data">Data e hora</Label>
                    <Input id="lancamento_data" type="datetime-local" max={agora_local()} value={formulario.ocorrido_em} $erro={Boolean(erros.ocorrido_em)} onChange={(evento) => mudar('ocorrido_em', evento.target.value)} />
                    {erros.ocorrido_em && <Error>{erros.ocorrido_em}</Error>}
                </Field>
                <Field $inteiro>
                    <Label htmlFor="lancamento_observacao">Observação (opcional)</Label>
                    <Textarea id="lancamento_observacao" maxLength={LIMITE_OBSERVACAO} value={formulario.observacao} onChange={(evento) => mudar('observacao', evento.target.value)} />
                    <Counter>{formulario.observacao.length}/{LIMITE_OBSERVACAO}</Counter>
                </Field>
            </Fields>
        </CashModal>
    );
}
