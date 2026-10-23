import { useEffect, useState } from 'react';

import { Button, erro_do_campo, Error, Field, Fields, GhostButton, Input, Label } from '../CashModal';
import Spinner from '../Spinner';
import api from '../../utils/api';
import { alerta_erro, confirmar } from '../../utils/alerts';
import { Actions, Empty, Form, Item, Items, Loading, Select, Tag } from './styles';

const tipos_chave = ['CPF', 'CNPJ', 'E-mail', 'Telefone', 'Chave aleatória'];

const vazio = { pix_tipo_chave: 'CPF', pix_chave: '', pix_nome_titular: '', principal: false };

// chaves Pix do cliente (spec 019, FR-014): lista, cadastro, edição e exclusão pelas regras da spec
// 002 (GET/POST/PATCH/DELETE /clientes/{id}/meios-pagamento); as chaves chegam mascaradas sem a
// permissão de ver dados completos
export default function PaymentMethodsList({ cliente, token, pode_editar, ao_salvar }) {
    const [meios, definir_meios] = useState(null);
    const [edicao, definir_edicao] = useState(null);
    const [erro, definir_erro] = useState(null);
    const [salvando, definir_salvando] = useState(false);
    const [recarga, definir_recarga] = useState(0);
    const autorizacao = { headers: { Authorization: `Bearer ${token}` } };

    useEffect(() => {
        let ativo = true;

        api.get(`/clientes/${cliente.id}/meios-pagamento`, autorizacao)
            .then(({ data }) => ativo && definir_meios(data.data))
            .catch((falha) => ativo && alerta_erro(falha));

        return () => {
            ativo = false;
        };
    }, [cliente.id, recarga]);

    if (!meios) return <Loading><Spinner tamanho={30} cor="currentColor" /></Loading>;

    const salvar = async () => {
        definir_salvando(true);
        definir_erro(null);

        try {
            // na edição só vão os campos preenchidos: a API completa o resto com o que está gravado
            // (a chave chega mascarada e não pode voltar assim)
            const corpo = edicao.id
                ? Object.fromEntries(Object.entries(edicao.dados).filter(([, valor]) => valor !== ''))
                : { ...edicao.dados, tipo: 'Pix' };

            if (edicao.id) await api.patch(`/clientes/${cliente.id}/meios-pagamento/${edicao.id}`, corpo, autorizacao);
            else await api.post(`/clientes/${cliente.id}/meios-pagamento`, corpo, autorizacao);

            definir_edicao(null);
            definir_recarga((atual) => atual + 1);
            ao_salvar('Chave Pix salva.');
        } catch (falha) {
            if (falha.response?.status === 422) definir_erro(falha);
            else alerta_erro(falha);
        } finally {
            definir_salvando(false);
        }
    };

    const excluir = async (meio) => {
        if (!(await confirmar(`Excluir a chave Pix ${meio.pix_chave}?`))) return;

        try {
            await api.delete(`/clientes/${cliente.id}/meios-pagamento/${meio.id}`, autorizacao);
            definir_recarga((atual) => atual + 1);
            ao_salvar('Chave Pix excluída.');
        } catch (falha) {
            alerta_erro(falha);
        }
    };

    const mudar = (mudanca) => definir_edicao((atual) => ({ ...atual, dados: { ...atual.dados, ...mudanca } }));

    return (
        <>
            {!meios.length && <Empty>Nenhuma chave Pix cadastrada.</Empty>}
            <Items>
                {meios.map((meio) => (
                    <Item key={meio.id}>
                        <div>
                            <strong>{meio.tipo === 'Pix' ? `${meio.pix_tipo_chave}: ${meio.pix_chave}` : `${meio.banco_nome} · ${meio.agencia}/${meio.conta}`}</strong>
                            <span>{meio.pix_nome_titular ?? ''}</span>
                        </div>
                        {meio.principal && <Tag>principal</Tag>}
                        {pode_editar && meio.tipo === 'Pix' && (
                            <Actions>
                                <GhostButton type="button" onClick={() => definir_edicao({ id: meio.id, dados: { pix_tipo_chave: meio.pix_tipo_chave, pix_chave: '', pix_nome_titular: meio.pix_nome_titular ?? '', principal: meio.principal } })}>Editar</GhostButton>
                                <GhostButton type="button" onClick={() => excluir(meio)}>Excluir</GhostButton>
                            </Actions>
                        )}
                    </Item>
                ))}
            </Items>
            {pode_editar && !edicao && <Button type="button" onClick={() => definir_edicao({ id: null, dados: vazio })}>Adicionar chave Pix</Button>}
            {edicao && (
                <Form>
                    <Fields>
                        <Field>
                            <Label htmlFor="pix_tipo">Tipo da chave</Label>
                            <Select id="pix_tipo" value={edicao.dados.pix_tipo_chave} onChange={(evento) => mudar({ pix_tipo_chave: evento.target.value })}>
                                {tipos_chave.map((tipo) => <option key={tipo} value={tipo}>{tipo}</option>)}
                            </Select>
                        </Field>
                        <Field>
                            <Label htmlFor="pix_chave">{edicao.id ? 'Nova chave (vazio mantém a atual)' : 'Chave'}</Label>
                            <Input id="pix_chave" value={edicao.dados.pix_chave} onChange={(evento) => mudar({ pix_chave: evento.target.value })} />
                            {erro_do_campo(erro, 'pix_chave') && <Error>{erro_do_campo(erro, 'pix_chave')}</Error>}
                        </Field>
                        <Field $inteiro>
                            <Label htmlFor="pix_titular">Nome do titular</Label>
                            <Input id="pix_titular" maxLength={150} value={edicao.dados.pix_nome_titular} onChange={(evento) => mudar({ pix_nome_titular: evento.target.value })} />
                            {erro_do_campo(erro, 'pix_nome_titular') && <Error>{erro_do_campo(erro, 'pix_nome_titular')}</Error>}
                        </Field>
                    </Fields>
                    <Actions>
                        <GhostButton type="button" onClick={() => definir_edicao(null)}>Cancelar</GhostButton>
                        <Button type="button" disabled={salvando} onClick={salvar}>{salvando ? 'Salvando…' : 'Salvar chave'}</Button>
                    </Actions>
                </Form>
            )}
        </>
    );
}
