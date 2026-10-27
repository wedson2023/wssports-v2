import { useEffect, useRef, useState } from 'react';

import api from '../../utils/api';
import { Box, Clear, Input, Option, Options, Selected, Tag, Wrapper } from './styles';

// espera depois da última tecla antes de buscar
const ATRASO_BUSCA = 300;

// escolha do campeonato do filtro e do cadastro de jogo da tela Confrontos (spec 023), no padrão do
// SellerPicker: busca em GET /campeonatos e só grava um campeonato da lista, nunca o texto digitado
// (constituição, "Campos de itens existentes"). so_manuais limita aos cadastrados à mão. valor =
// { id, nome } | null
export default function ChampionshipPicker({ id, valor, token, so_manuais = false, ao_escolher }) {
    const [texto, definir_texto] = useState('');
    const [opcoes, definir_opcoes] = useState([]);
    const [aberto, definir_aberto] = useState(false);
    const [carregando, definir_carregando] = useState(false);
    const espera = useRef(null);

    useEffect(() => {
        if (!aberto || !token) return undefined;

        let ativo = true;
        window.clearTimeout(espera.current);
        espera.current = window.setTimeout(() => {
            definir_carregando(true);
            api.get('/campeonatos', {
                params: { ...(texto.trim() ? { busca: texto.trim() } : {}), ...(so_manuais ? { manual: 1 } : {}), por_pagina: 20 },
                headers: { Authorization: `Bearer ${token}` },
            })
                .then(({ data }) => ativo && definir_opcoes(data.data))
                .catch(() => ativo && definir_opcoes([]))
                .finally(() => ativo && definir_carregando(false));
        }, ATRASO_BUSCA);

        return () => {
            ativo = false;
            window.clearTimeout(espera.current);
        };
    }, [aberto, texto, token]);

    const escolher = (campeonato) => {
        ao_escolher({ id: campeonato.id, nome: campeonato.nome });
        definir_texto('');
        definir_aberto(false);
    };

    if (valor) {
        return (
            <Selected>
                <span>{valor.nome}</span>
                <Clear type="button" title="Trocar o campeonato" aria-label="Trocar o campeonato" onClick={() => ao_escolher(null)}>
                    <i className="material-icons" aria-hidden="true">close</i>
                </Clear>
            </Selected>
        );
    }

    return (
        <Wrapper onBlur={(evento) => !evento.currentTarget.contains(evento.relatedTarget) && definir_aberto(false)}>
            <Box>
                <i className="material-icons" aria-hidden="true">emoji_events</i>
                <Input
                    id={id}
                    type="search"
                    value={texto}
                    placeholder="Buscar campeonato pelo nome"
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
                    {!carregando && !opcoes.length && <Option as="div">Nenhum campeonato encontrado.</Option>}
                    {opcoes.map((campeonato) => (
                        <Option key={campeonato.id} type="button" role="option" onClick={() => escolher(campeonato)}>
                            <strong>{campeonato.nome}</strong>
                            <span>{campeonato.pais}</span>
                            {!campeonato.ativo && <Tag>desativado</Tag>}
                        </Option>
                    ))}
                </Options>
            )}
        </Wrapper>
    );
}
