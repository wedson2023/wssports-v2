import { useEffect, useRef, useState } from 'react';

import api from '../../utils/api';
import { Box, Chip, Chips, Input, Option, Options, Tag, Wrapper } from './styles';

// espera depois da última tecla antes de buscar
const ATRASO_BUSCA = 300;

// escolha de vários clientes (spec 019: público "Por ID" da notificação): busca com resultados da
// rede (GET /clientes) e chips dos escolhidos; nunca grava texto digitado. valor = [{ id, nome }]
export default function ClientPicker({ id, valor, token, ao_mudar, maximo = 500 }) {
    const [texto, definir_texto] = useState('');
    const [opcoes, definir_opcoes] = useState([]);
    const [aberto, definir_aberto] = useState(false);
    const [carregando, definir_carregando] = useState(false);
    const espera = useRef(null);

    useEffect(() => {
        if (!aberto || !token || !texto.trim()) return undefined;

        let ativo = true;
        window.clearTimeout(espera.current);
        espera.current = window.setTimeout(() => {
            definir_carregando(true);
            api.get('/clientes', { params: { busca: texto.trim(), por_pagina: 20 }, headers: { Authorization: `Bearer ${token}` } })
                .then(({ data }) => ativo && definir_opcoes(data.data))
                .catch(() => ativo && definir_opcoes([]))
                .finally(() => ativo && definir_carregando(false));
        }, ATRASO_BUSCA);

        return () => {
            ativo = false;
            window.clearTimeout(espera.current);
        };
    }, [aberto, texto, token]);

    const escolhido = (cliente) => valor.some((item) => item.id === cliente.id);

    const escolher = (cliente) => {
        if (!escolhido(cliente) && valor.length < maximo) ao_mudar([...valor, { id: cliente.id, nome: cliente.nome }]);
        definir_texto('');
    };

    return (
        <>
            <Wrapper onBlur={(evento) => !evento.currentTarget.contains(evento.relatedTarget) && definir_aberto(false)}>
                <Box>
                    <i className="material-icons" aria-hidden="true">person_search</i>
                    <Input
                        id={id}
                        type="search"
                        value={texto}
                        placeholder={valor.length >= maximo ? `No máximo ${maximo} clientes` : 'Buscar cliente por nome, telefone ou e-mail'}
                        autoComplete="off"
                        disabled={valor.length >= maximo}
                        onFocus={() => definir_aberto(true)}
                        onChange={(evento) => {
                            definir_texto(evento.target.value);
                            definir_aberto(true);
                        }}
                    />
                </Box>
                {aberto && texto.trim() && (
                    <Options role="listbox">
                        {carregando && !opcoes.length && <Option as="div">Buscando...</Option>}
                        {!carregando && !opcoes.length && <Option as="div">Nenhum cliente encontrado.</Option>}
                        {opcoes.map((cliente) => (
                            <Option key={cliente.id} type="button" role="option" disabled={escolhido(cliente)} onClick={() => escolher(cliente)}>
                                <strong>{cliente.nome}</strong>
                                <span>{`ID ${cliente.id}`}</span>
                                {escolhido(cliente) && <Tag>escolhido</Tag>}
                            </Option>
                        ))}
                    </Options>
                )}
            </Wrapper>
            {valor.length > 0 && (
                <Chips>
                    {valor.map((cliente) => (
                        <Chip key={cliente.id}>
                            {cliente.nome}
                            <button type="button" aria-label={`Tirar ${cliente.nome}`} onClick={() => ao_mudar(valor.filter((item) => item.id !== cliente.id))}>
                                <i className="material-icons" aria-hidden="true">close</i>
                            </button>
                        </Chip>
                    ))}
                </Chips>
            )}
        </>
    );
}
