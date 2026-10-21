import { useCallback, useEffect, useMemo, useState } from 'react';
import { router } from '@inertiajs/react';

import { ler_numero } from '../utils/quotes';

// faixa aceita por modo (spec 003): porcentagem de −100 a 100; cotação do jogo e teto a partir de 1,00
const validar = (modo, numero) => {
    if (Number.isNaN(numero)) return 'Informe um número.';
    if (numero === null) return null;
    if (modo === 'porcentagem' && (numero < -100 || numero > 100)) return 'A porcentagem deve estar entre -100 e 100.';
    if (modo !== 'porcentagem' && numero < 1) return 'O valor mínimo é 1,00.';
    if (Math.round(numero * 100) !== numero * 100) return 'Use até 2 casas decimais.';

    return null;
};

// estado do editor de cotações (spec 016, FR-006/FR-007): valores gravados, alterações pendentes
// (texto do campo), erros por campo, códigos trancados e aviso ao sair com alterações não salvas
export default function useGradeCotacoes(modo = 'porcentagem') {
    const [original, definir_original] = useState({});
    const [pendentes, definir_pendentes] = useState({});
    const [erros, definir_erros] = useState({});
    const [trancados, definir_trancados] = useState({});

    const quantidade = Object.keys(pendentes).length;

    // novos valores gravados (abertura ou resposta da gravação): sem pendentes nem erros
    const carregar = useCallback((valores) => {
        definir_original(valores ?? {});
        definir_pendentes({});
        definir_erros({});
    }, []);

    const mudar = useCallback((codigo, texto) => {
        definir_pendentes((atuais) => {
            const numero = ler_numero(texto);
            const gravado = original[codigo] ?? null;
            const proximos = { ...atuais };

            // voltar ao valor gravado tira a pendência
            if (numero === gravado || (numero === null && gravado === null)) delete proximos[codigo];
            else proximos[codigo] = texto;

            return proximos;
        });
        definir_erros((atuais) => {
            const mensagem = validar(modo, ler_numero(texto));
            const proximos = { ...atuais };

            if (mensagem) proximos[codigo] = mensagem;
            else delete proximos[codigo];

            return proximos;
        });
    }, [original, modo]);

    // mesmo valor em vários códigos, pulando os trancados; devolve quantos pulou
    const aplicar_a_todas = useCallback((codigos, texto) => {
        const livres = codigos.filter((codigo) => !trancados[codigo]);

        livres.forEach((codigo) => mudar(codigo, texto));

        return codigos.length - livres.length;
    }, [trancados, mudar]);

    const desfazer = useCallback(() => {
        definir_pendentes({});
        definir_erros({});
    }, []);

    // valor que vale agora no campo (pendente válido ou gravado), para a prévia e a contagem
    const valor_atual = useCallback((codigo) => {
        if (codigo in pendentes) {
            const numero = ler_numero(pendentes[codigo]);

            return Number.isNaN(numero) ? (original[codigo] ?? null) : numero;
        }

        return original[codigo] ?? null;
    }, [pendentes, original]);

    // alterações para a API (vazio = remover, enviado como 0); null quando há erro
    const alteracoes_para_salvar = useCallback(() => {
        if (Object.keys(erros).length) return null;

        return Object.fromEntries(Object.entries(pendentes).map(([codigo, texto]) => [codigo, ler_numero(texto) ?? 0]));
    }, [pendentes, erros]);

    // aviso do navegador ao fechar ou recarregar com alterações não salvas
    useEffect(() => {
        if (!quantidade) return undefined;

        const avisar = (evento) => {
            evento.preventDefault();
            evento.returnValue = '';
        };

        window.addEventListener('beforeunload', avisar);

        // navegação do Inertia (menu do painel) pede confirmação
        const parar = router.on('before', (evento) => {
            if (evento.detail.visit.method === 'get' && !window.confirm(`Você tem ${quantidade} alterações não salvas. Sair mesmo assim?`)) {
                evento.preventDefault();
            }
        });

        return () => {
            window.removeEventListener('beforeunload', avisar);
            parar();
        };
    }, [quantidade]);

    return useMemo(() => ({
        original,
        pendentes,
        erros,
        trancados,
        quantidade,
        carregar,
        mudar,
        aplicar_a_todas,
        desfazer,
        valor_atual,
        alteracoes_para_salvar,
        definir_trancados,
        definir_erros,
    }), [original, pendentes, erros, trancados, quantidade, carregar, mudar, aplicar_a_todas, desfazer, valor_atual, alteracoes_para_salvar]);
}
