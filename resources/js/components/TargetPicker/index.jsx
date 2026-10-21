import { useEffect, useState } from 'react';

import api from '../../utils/api';
import { Field, Hint, Label, Select } from './styles';

// valor do select: "Todos", "Clientes" ou "Vendedores:<id>"
const para_valor = (alvo) => (alvo?.alvo === 'Vendedores' ? `Vendedores:${alvo.usuarios_id}` : (alvo?.alvo ?? ''));

// alvo de uma regra de campeonato ou jogo (spec 016, FR-003/FR-014): Todos e Clientes só para Admin e
// Supervisor; "Vendedores de" um usuário da rede para todos que editam; escolhido de lista
export default function TargetPicker({ alvo, ao_escolher, gestor_geral, token, desabilitado = false }) {
    const [usuarios, definir_usuarios] = useState([]);

    useEffect(() => {
        let ativo = true;

        api.get('/cotacoes/publicos', { params: { por_pagina: 100 }, headers: { Authorization: `Bearer ${token}` } })
            .then(({ data }) => ativo && definir_usuarios(data.data))
            .catch(() => ativo && definir_usuarios([]));

        return () => {
            ativo = false;
        };
    }, [token]);

    const escolher = (valor) => {
        if (valor === 'Todos' || valor === 'Clientes') {
            ao_escolher({ alvo: valor, usuarios_id: null, nome: null });
            return;
        }

        const usuario = usuarios.find((item) => `Vendedores:${item.id}` === valor);

        ao_escolher({ alvo: 'Vendedores', usuarios_id: usuario.id, nome: usuario.nome });
    };

    return (
        <Field>
            <Label htmlFor="alvo-regra">Vale para</Label>
            <Select id="alvo-regra" value={para_valor(alvo)} disabled={desabilitado} onChange={(evento) => escolher(evento.target.value)}>
                {gestor_geral && (
                    <optgroup label="Site e todos">
                        <option value="Clientes">Clientes do site</option>
                        <option value="Todos">Todos (visitantes, clientes e todos os vendedores)</option>
                    </optgroup>
                )}
                <optgroup label="Vendedores de">
                    {usuarios.map((usuario) => (
                        <option key={usuario.id} value={`Vendedores:${usuario.id}`}>
                            {usuario.nome} · {usuario.funcao}
                        </option>
                    ))}
                </optgroup>
            </Select>
            {alvo?.alvo === 'Vendedores' && <Hint>Vale para {alvo.nome} e para todos abaixo dele.</Hint>}
            {alvo?.alvo === 'Todos' && <Hint>Vale para visitantes, clientes e todos os vendedores.</Hint>}
        </Field>
    );
}
