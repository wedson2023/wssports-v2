import { useEffect, useRef, useState } from 'react';

const codigos = ['odd1', 'odd2', 'odd3', 'odd4'];

// tempo da animação de variação: 0,5s × 4 repetições
const duracao_ms = 2000;

// compara as cotações de cada jogo com as da atualização anterior (só no ao vivo) e devolve
// { [id]: { odd1: 'subiu' | 'desceu' } } durante a animação; nada é guardado no aparelho
export default function useVariacaoCotacoes(campeonatos, ativo) {
    const anteriores = useRef({});
    const [variacoes, definir_variacoes] = useState({});

    useEffect(() => {
        if (!ativo) {
            anteriores.current = {};
            definir_variacoes({});
            return undefined;
        }

        const novas = {};

        campeonatos.forEach(({ confrontos }) => confrontos.forEach(({ id, cotacoes }) => {
            const antes = anteriores.current[id];

            if (antes) {
                codigos.forEach((codigo) => {
                    const atual = Number(cotacoes?.[codigo] ?? 0);
                    const anterior = Number(antes[codigo] ?? 0);

                    if (atual > anterior) (novas[id] ??= {})[codigo] = 'subiu';
                    if (atual < anterior) (novas[id] ??= {})[codigo] = 'desceu';
                });
            }

            anteriores.current[id] = { ...cotacoes };
        }));

        definir_variacoes(novas);

        const temporizador = setTimeout(() => definir_variacoes({}), duracao_ms);

        return () => clearTimeout(temporizador);
    }, [campeonatos, ativo]);

    return variacoes;
}
