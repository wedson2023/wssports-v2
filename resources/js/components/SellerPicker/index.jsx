import { useEffect, useRef, useState } from 'react';

import api from '../../utils/api';
import { Box, Clear, Input, Option, Options, Selected, Tag, Wrapper } from './styles';

// espera depois da última tecla antes de buscar
const ATRASO_BUSCA = 300;

const situacoes = { inativo: 'inativo', excluido: 'excluído' };

// escolha do vendedor do filtro de apostas (spec 018, R-10): busca com resultados da rede do
// usuário (GET /apostas/vendedores); só grava um vendedor da lista, nunca o texto digitado
// (constituição, "Campos de itens existentes"). valor = { id, nome } | null
export default function SellerPicker({ id, valor, token, ao_escolher }) {
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
            api.get('/apostas/vendedores', { params: texto.trim() ? { busca: texto.trim() } : {}, headers: { Authorization: `Bearer ${token}` } })
                .then(({ data }) => ativo && definir_opcoes(data.data))
                .catch(() => ativo && definir_opcoes([]))
                .finally(() => ativo && definir_carregando(false));
        }, ATRASO_BUSCA);

        return () => {
            ativo = false;
            window.clearTimeout(espera.current);
        };
    }, [aberto, texto, token]);

    const escolher = (vendedor) => {
        ao_escolher({ id: vendedor.id, nome: vendedor.nome });
        definir_texto('');
        definir_aberto(false);
    };

    if (valor) {
        return (
            <Selected>
                <span>{valor.nome}</span>
                <Clear type="button" title="Trocar o vendedor" aria-label="Trocar o vendedor" onClick={() => ao_escolher(null)}>
                    <i className="material-icons" aria-hidden="true">close</i>
                </Clear>
            </Selected>
        );
    }

    return (
        <Wrapper onBlur={(evento) => !evento.currentTarget.contains(evento.relatedTarget) && definir_aberto(false)}>
            <Box>
                <i className="material-icons" aria-hidden="true">person_search</i>
                <Input
                    id={id}
                    type="search"
                    value={texto}
                    placeholder="Buscar vendedor por nome ou login"
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
                    {!carregando && !opcoes.length && <Option as="div">Nenhum vendedor encontrado.</Option>}
                    {opcoes.map((vendedor) => (
                        <Option key={vendedor.id} type="button" role="option" onClick={() => escolher(vendedor)}>
                            <strong>{vendedor.nome}</strong>
                            <span>{vendedor.login}</span>
                            {situacoes[vendedor.situacao] && <Tag>{situacoes[vendedor.situacao]}</Tag>}
                        </Option>
                    ))}
                </Options>
            )}
        </Wrapper>
    );
}
