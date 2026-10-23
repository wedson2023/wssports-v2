import { useEffect, useRef, useState } from 'react';

import api from '../../utils/api';
import { Box, Clear, Input, Option, Options, Selected, Tag, Wrapper } from './styles';

// espera depois da última tecla antes de buscar
const ATRASO_BUSCA = 300;

// escolha do afiliado (spec 019: filtro de clientes e edição do cliente): busca com resultados da rede
// do usuário (GET /afiliados, já com o alcance); só grava um afiliado da lista, nunca o texto
// digitado (constituição, "Campos de itens existentes"). valor = { id, nome } | null; sem
// pode_limpar, o afiliado só é trocado (Supervisor e Gerente não deixam o cliente sem afiliado)
export default function AffiliatePicker({ id, valor, token, ao_escolher, pode_limpar = true }) {
    const [texto, definir_texto] = useState('');
    const [opcoes, definir_opcoes] = useState([]);
    const [aberto, definir_aberto] = useState(false);
    const [trocando, definir_trocando] = useState(false);
    const [carregando, definir_carregando] = useState(false);
    const espera = useRef(null);

    useEffect(() => {
        if (!aberto || !token) return undefined;

        let ativo = true;
        window.clearTimeout(espera.current);
        espera.current = window.setTimeout(() => {
            definir_carregando(true);
            api.get('/afiliados', { params: { por_pagina: 20, ...(texto.trim() ? { busca: texto.trim() } : {}) }, headers: { Authorization: `Bearer ${token}` } })
                .then(({ data }) => ativo && definir_opcoes(data.data))
                .catch(() => ativo && definir_opcoes([]))
                .finally(() => ativo && definir_carregando(false));
        }, ATRASO_BUSCA);

        return () => {
            ativo = false;
            window.clearTimeout(espera.current);
        };
    }, [aberto, texto, token]);

    const escolher = (afiliado) => {
        ao_escolher({ id: afiliado.id, nome: afiliado.nome });
        definir_texto('');
        definir_aberto(false);
        definir_trocando(false);
    };

    if (valor && !trocando) {
        return (
            <Selected>
                <span>{valor.nome}</span>
                <Clear
                    type="button"
                    title={pode_limpar ? 'Tirar o afiliado' : 'Trocar o afiliado'}
                    aria-label={pode_limpar ? 'Tirar o afiliado' : 'Trocar o afiliado'}
                    onClick={() => (pode_limpar ? ao_escolher(null) : definir_trocando(true))}
                >
                    <i className="material-icons" aria-hidden="true">{pode_limpar ? 'close' : 'swap_horiz'}</i>
                </Clear>
            </Selected>
        );
    }

    return (
        <Wrapper onBlur={(evento) => !evento.currentTarget.contains(evento.relatedTarget) && definir_aberto(false)}>
            <Box>
                <i className="material-icons" aria-hidden="true">handshake</i>
                <Input
                    id={id}
                    type="search"
                    value={texto}
                    placeholder="Buscar afiliado por nome, login ou código"
                    autoComplete="off"
                    onFocus={() => definir_aberto(true)}
                    onChange={(evento) => {
                        definir_texto(evento.target.value);
                        definir_aberto(true);
                    }}
                />
            </Box>
            {aberto && (
                <Options role="listbox">
                    {carregando && !opcoes.length && <Option as="div">Buscando...</Option>}
                    {!carregando && !opcoes.length && <Option as="div">Nenhum afiliado encontrado.</Option>}
                    {opcoes.map((afiliado) => (
                        <Option key={afiliado.id} type="button" role="option" onClick={() => escolher(afiliado)}>
                            <strong>{afiliado.nome}</strong>
                            <span>{afiliado.codigo}</span>
                            {afiliado.gerente && <Tag>{afiliado.gerente.nome}</Tag>}
                        </Option>
                    ))}
                </Options>
            )}
        </Wrapper>
    );
}
