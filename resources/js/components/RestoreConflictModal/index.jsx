import { useEffect, useState } from 'react';

import CashModal, { Button, erro_do_campo, Error, Field, GhostButton, Input, Label } from '../CashModal';
import api from '../../utils/api';
import { alerta_erro } from '../../utils/alerts';
import { Fields, Warning } from './styles';

const rotulos = { telefone: 'Novo telefone', email: 'Novo e-mail', cpf: 'Novo CPF' };

// restauração com dados em uso por outro cliente (spec 019, FR-029; regra da spec 002): pede os
// valores novos de cada campo em conflito e restaura. conflito = { cliente, campos: [...] }
export default function RestoreConflictModal({ conflito, token, ao_fechar, ao_concluir }) {
    const aberto = Boolean(conflito);
    const [valores, definir_valores] = useState({});
    const [erro, definir_erro] = useState(null);
    const [salvando, definir_salvando] = useState(false);

    useEffect(() => {
        if (!aberto) return;

        definir_valores(Object.fromEntries(conflito.campos.map((campo) => [campo, ''])));
        definir_erro(null);
    }, [conflito]);

    const restaurar = async () => {
        definir_salvando(true);
        definir_erro(null);

        try {
            await api.post(`/clientes/${conflito.cliente.id}/restaurar`, valores, { headers: { Authorization: `Bearer ${token}` } });
            ao_concluir('Cliente restaurado.');
        } catch (falha) {
            if (falha.response?.status === 422) definir_erro(falha);
            else alerta_erro(falha);
        } finally {
            definir_salvando(false);
        }
    };

    return (
        <CashModal
            aberto={aberto}
            icone="warning"
            titulo="Dados em uso"
            descricao="Alguns dados já estão em uso por outro cliente."
            ao_fechar={ao_fechar}
            rodape={(
                <>
                    <GhostButton type="button" onClick={ao_fechar}>Cancelar</GhostButton>
                    <Button type="button" disabled={salvando} onClick={restaurar}>{salvando ? 'Restaurando…' : 'Restaurar'}</Button>
                </>
            )}
        >
            {conflito && (
                <>
                    <Warning>
                        <i className="material-icons" aria-hidden="true">info</i>
                        {`Para restaurar ${conflito.cliente.nome}, informe valores novos para os dados abaixo.`}
                    </Warning>
                    <Fields>
                        {conflito.campos.map((campo) => (
                            <Field key={campo}>
                                <Label htmlFor={`restaurar_${campo}`}>{rotulos[campo] ?? campo}</Label>
                                <Input
                                    id={`restaurar_${campo}`}
                                    type={campo === 'email' ? 'email' : 'text'}
                                    inputMode={campo === 'email' ? 'email' : 'numeric'}
                                    value={valores[campo] ?? ''}
                                    onChange={(evento) => definir_valores((atual) => ({ ...atual, [campo]: evento.target.value }))}
                                />
                                {erro_do_campo(erro, campo) && <Error>{erro_do_campo(erro, campo)}</Error>}
                            </Field>
                        ))}
                    </Fields>
                </>
            )}
        </CashModal>
    );
}
