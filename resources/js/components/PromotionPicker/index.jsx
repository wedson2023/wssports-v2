import { useEffect, useRef, useState } from 'react';

import api from '../../utils/api';
import { Box, Clear, Input, Option, Options, Selected, Tag, Wrapper } from './styles';

// espera depois da última tecla antes de buscar
const ATRASO_BUSCA = 300;

// escolha da promoção (spec 020: filtro da aba Bônus recebidos): busca com resultados das promoções
// visíveis ao usuário (GET /clientes-promocoes, já com o alcance); só grava uma promoção da lista,
// nunca o texto digitado (constituição, "Campos de itens existentes"). valor = { id, nome } | null
export default function PromotionPicker({ id, valor, token, ao_escolher }) {
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
            api.get('/clientes-promocoes', { params: { por_pagina: 20, ...(texto.trim() ? { busca: texto.trim() } : {}) }, headers: { Authorization: `Bearer ${token}` } })
                .then(({ data }) => ativo && definir_opcoes(data.data))
                .catch(() => ativo && definir_opcoes([]))
                .finally(() => ativo && definir_carregando(false));
        }, ATRASO_BUSCA);

        return () => {
            ativo = false;
            window.clearTimeout(espera.current);
        };
    }, [aberto, texto, token]);

    const escolher = (promocao) => {
        ao_escolher({ id: promocao.id, nome: promocao.nome });
        definir_texto('');
        definir_aberto(false);
    };

    if (valor) {
        return (
            <Selected>
                <span>{valor.nome}</span>
                <Clear type="button" title="Tirar a promoção" aria-label="Tirar a promoção" onClick={() => ao_escolher(null)}>
                    <i className="material-icons" aria-hidden="true">close</i>
                </Clear>
            </Selected>
        );
    }

    return (
        <Wrapper onBlur={(evento) => !evento.currentTarget.contains(evento.relatedTarget) && definir_aberto(false)}>
            <Box>
                <i className="material-icons" aria-hidden="true">card_giftcard</i>
                <Input
                    id={id}
                    type="search"
                    value={texto}
                    placeholder="Buscar promoção pelo nome"
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
                    {!carregando && !opcoes.length && <Option as="div">Nenhuma promoção encontrada.</Option>}
                    {opcoes.map((promocao) => (
                        <Option key={promocao.id} type="button" role="option" onClick={() => escolher(promocao)}>
                            <strong>{promocao.nome}</strong>
                            <span>{`${promocao.categoria} · ${promocao.modalidade}`}</span>
                            <Tag>{promocao.afiliado ? promocao.afiliado.codigo : 'Geral'}</Tag>
                        </Option>
                    ))}
                </Options>
            )}
        </Wrapper>
    );
}
