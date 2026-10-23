import { useEffect, useState } from 'react';

import AffiliatePicker from '../AffiliatePicker';
import CashModal, { Button, Error, Field, Fields, GhostButton, Hint, Input, Label, erro_do_campo } from '../CashModal';
import Spinner from '../Spinner';
import api from '../../utils/api';
import { alerta_erro } from '../../utils/alerts';
import { Select } from './styles';

const generos = ['Masculino', 'Feminino', 'Outro', 'Não informado'];

const campos_texto = ['nome', 'ddi', 'telefone', 'email', 'cpf', 'data_nascimento', 'genero'];

const iniciais = (cliente) => ({
    ...Object.fromEntries(campos_texto.map((campo) => [campo, cliente?.[campo] ?? ''])),
    afiliado: cliente?.afiliado ? { id: cliente.afiliado.id, nome: cliente.afiliado.nome } : null,
    password: '',
    password_confirmation: '',
});

// edição do cliente pelo painel (spec 019, FR-012, contracts/paginas §5), pelas regras da spec 002.
// Só os campos alterados vão para a API: sem a permissão de ver dados completos, telefone, e-mail e
// CPF chegam mascarados e não podem voltar assim. Supervisor e Gerente só trocam o afiliado
// (FR-003b); o Admin pode tirá-lo
export default function ClientFormModal({ cliente, token, e_admin, ao_fechar, ao_salvar }) {
    const [dados, definir_dados] = useState(iniciais(cliente));
    const [salvando, definir_salvando] = useState(false);
    const [erro, definir_erro] = useState(null);

    useEffect(() => {
        definir_dados(iniciais(cliente));
        definir_erro(null);
    }, [cliente?.id]);

    const alterar = (mudanca) => definir_dados((atual) => ({ ...atual, ...mudanca }));

    const salvar = async () => {
        const originais = iniciais(cliente);
        const corpo = Object.fromEntries(campos_texto.filter((campo) => dados[campo] !== originais[campo]).map((campo) => [campo, dados[campo] || null]));

        if ((dados.afiliado?.id ?? null) !== (originais.afiliado?.id ?? null)) corpo.afiliados_id = dados.afiliado?.id ?? null;

        if (dados.password) {
            corpo.password = dados.password;
            corpo.password_confirmation = dados.password_confirmation;
        }

        if (!Object.keys(corpo).length) {
            ao_fechar();
            return;
        }

        definir_salvando(true);
        definir_erro(null);

        try {
            await api.patch(`/clientes/${cliente.id}`, corpo, { headers: { Authorization: `Bearer ${token}` } });
            ao_salvar('Cliente atualizado.');
        } catch (falha) {
            if (falha.response?.status === 422) definir_erro(falha);
            else alerta_erro(falha);
        } finally {
            definir_salvando(false);
        }
    };

    const campo = (nome) => erro_do_campo(erro, nome);

    return (
        <CashModal
            aberto={Boolean(cliente)}
            icone="edit"
            titulo="Editar cliente"
            descricao="Altere os dados do cliente."
            ao_fechar={ao_fechar}
            rodape={(
                <>
                    <GhostButton type="button" onClick={ao_fechar}>Cancelar</GhostButton>
                    <Button type="button" disabled={salvando} onClick={salvar}>{salvando ? <Spinner tamanho={18} /> : 'Salvar'}</Button>
                </>
            )}
        >
            <Fields>
                <Field>
                    <Label htmlFor="cliente_nome">Nome</Label>
                    <Input id="cliente_nome" maxLength={150} value={dados.nome} onChange={(evento) => alterar({ nome: evento.target.value })} />
                    {campo('nome') && <Error>{campo('nome')}</Error>}
                </Field>
                <Field>
                    <Label htmlFor="cliente_ddi">DDI</Label>
                    <Input id="cliente_ddi" inputMode="numeric" maxLength={3} value={dados.ddi} onChange={(evento) => alterar({ ddi: evento.target.value.replace(/\D/g, '') })} />
                    {campo('ddi') && <Error>{campo('ddi')}</Error>}
                </Field>
                <Field>
                    <Label htmlFor="cliente_telefone">Telefone</Label>
                    <Input id="cliente_telefone" inputMode="tel" value={dados.telefone} onChange={(evento) => alterar({ telefone: evento.target.value })} />
                    {campo('telefone') && <Error>{campo('telefone')}</Error>}
                </Field>
                <Field>
                    <Label htmlFor="cliente_email">E-mail</Label>
                    <Input id="cliente_email" type="email" value={dados.email} onChange={(evento) => alterar({ email: evento.target.value })} />
                    {campo('email') && <Error>{campo('email')}</Error>}
                </Field>
                <Field>
                    <Label htmlFor="cliente_cpf">CPF</Label>
                    <Input id="cliente_cpf" inputMode="numeric" value={dados.cpf} onChange={(evento) => alterar({ cpf: evento.target.value })} />
                    {campo('cpf') && <Error>{campo('cpf')}</Error>}
                </Field>
                <Field>
                    <Label htmlFor="cliente_nascimento">Data de nascimento</Label>
                    <Input id="cliente_nascimento" type="date" value={dados.data_nascimento} onChange={(evento) => alterar({ data_nascimento: evento.target.value })} />
                    {campo('data_nascimento') && <Error>{campo('data_nascimento')}</Error>}
                </Field>
                <Field>
                    <Label htmlFor="cliente_genero">Gênero</Label>
                    <Select id="cliente_genero" value={dados.genero} onChange={(evento) => alterar({ genero: evento.target.value })}>
                        <option value="">Escolha</option>
                        {generos.map((genero) => <option key={genero} value={genero}>{genero}</option>)}
                    </Select>
                    {campo('genero') && <Error>{campo('genero')}</Error>}
                </Field>
                <Field>
                    <Label htmlFor="cliente_afiliado">Afiliado</Label>
                    <AffiliatePicker id="cliente_afiliado" valor={dados.afiliado} token={token} pode_limpar={e_admin} ao_escolher={(afiliado) => alterar({ afiliado })} />
                    {!e_admin && <Hint>O cliente fica sempre com um afiliado da sua rede.</Hint>}
                    {campo('afiliados_id') && <Error>{campo('afiliados_id')}</Error>}
                </Field>
                <Field>
                    <Label htmlFor="cliente_senha">Nova senha (opcional)</Label>
                    <Input id="cliente_senha" type="password" autoComplete="new-password" value={dados.password} onChange={(evento) => alterar({ password: evento.target.value })} />
                    {campo('password') && <Error>{campo('password')}</Error>}
                </Field>
                {dados.password && (
                    <Field>
                        <Label htmlFor="cliente_senha_confirmacao">Confirme a nova senha</Label>
                        <Input id="cliente_senha_confirmacao" type="password" autoComplete="new-password" value={dados.password_confirmation} onChange={(evento) => alterar({ password_confirmation: evento.target.value })} />
                    </Field>
                )}
            </Fields>
        </CashModal>
    );
}
