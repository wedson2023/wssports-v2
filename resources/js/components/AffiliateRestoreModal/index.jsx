import { useEffect, useState } from 'react';

import CashModal, { Button, erro_do_campo, Error, Field, GhostButton, Input, Label } from '../CashModal';
import api from '../../utils/api';
import { alerta_erro } from '../../utils/alerts';
import { Fields, Warning } from './styles';

const rotulos = { login: 'Novo login', email: 'Novo e-mail', cpf: 'Novo CPF', codigo: 'Novo código do link' };

// restauração do afiliado com dados únicos já em uso por outro cadastro (spec 022, FR-030, R-11;
// regra da spec 012): pede o valor novo só de cada campo em conflito e restaura.
// conflito = { afiliado, campos: [...] }
export default function AffiliateRestoreModal({ conflito, token, ao_fechar, ao_concluir }) {
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
            await api.post(`/afiliados/${conflito.afiliado.id}/restaurar`, valores, { headers: { Authorization: `Bearer ${token}` } });
            ao_concluir('Afiliado restaurado.');
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
            descricao="Alguns dados já estão em uso por outro cadastro."
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
                        {`Para restaurar ${conflito.afiliado.nome}, informe valores novos para os dados abaixo.`}
                    </Warning>
                    <Fields>
                        {conflito.campos.map((campo) => (
                            <Field key={campo}>
                                <Label htmlFor={`restaurar_afiliado_${campo}`}>{rotulos[campo] ?? campo}</Label>
                                <Input
                                    id={`restaurar_afiliado_${campo}`}
                                    type={campo === 'email' ? 'email' : 'text'}
                                    inputMode={campo === 'cpf' ? 'numeric' : undefined}
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
