import { useCallback, useState } from 'react';

import useTelaMobile from './useTelaMobile';
import api from '../utils/api';
import { alerta_atencao, alerta_erro } from '../utils/alerts';
import { bluetooth_disponivel, escrever } from '../utils/bluetooth';
import { imprimir_bilhete as imprimir_bilhete_navegador, imprimir_tabela as imprimir_tabela_navegador } from '../utils/print';
import { chave_impressao, gravar_json, ler_json } from '../utils/storage';
import { texto_bilhete, texto_tabela } from '../utils/thermal';

// preferências padrão: impressão Bluetooth (PADRÃO) na bobina de 80 mm, como no sistema antigo
const padrao = { modo: 'PADRÃO', largura: 80 };

const ler_preferencias = () => {
    const salvas = ler_json(chave_impressao);
    const modo = ['PADRÃO', 'APP'].includes(salvas?.modo) ? salvas.modo : padrao.modo;
    const largura = [58, 80].includes(salvas?.largura) ? salvas.largura : padrao.largura;

    return { modo, largura };
};

// todas as páginas da tabela (no máximo 100 jogos por página), com os campeonatos divididos entre
// duas páginas juntados de novo
const buscar_tabela = async (filtros) => {
    let pagina = 1;
    let tabela = null;

    do {
        const { data } = await api.get('/tabela-jogos', { params: { ...filtros, pagina, por_pagina: 100 } });

        if (!tabela) {
            tabela = { ...data, campeonatos: [] };
        }

        data.campeonatos.forEach((campeonato) => {
            const ultimo = tabela.campeonatos[tabela.campeonatos.length - 1];

            if (ultimo?.id === campeonato.id) ultimo.confrontos.push(...campeonato.confrontos);
            else tabela.campeonatos.push({ ...campeonato, confrontos: [...campeonato.confrontos] });
        });

        tabela.meta = data.meta;
        pagina += 1;
    } while (pagina <= tabela.meta.ultima_pagina);

    return tabela;
};

// impressão do vendedor (spec 006, US2): preferências do aparelho e impressão do bilhete e da tabela
// pelo navegador (computador), pela impressora Bluetooth (PADRÃO) ou pelo aplicativo (APP)
export default function useImpressao() {
    const e_mobile = useTelaMobile();
    const [preferencias, definir_preferencias] = useState(ler_preferencias);

    const alterar = useCallback((mudanca) => {
        definir_preferencias((atuais) => {
            const novas = { ...atuais, ...mudanca(atuais) };
            gravar_json(chave_impressao, novas);

            return novas;
        });
    }, []);

    const alternar_modo = useCallback(() => alterar(({ modo }) => ({ modo: modo === 'PADRÃO' ? 'APP' : 'PADRÃO' })), [alterar]);
    const alternar_largura = useCallback(() => alterar(({ largura }) => ({ largura: largura === 80 ? 58 : 80 })), [alterar]);

    // envia para a impressora Bluetooth; cancelar a escolha da impressora não mostra erro
    const imprimir_bluetooth = useCallback(async (bytes) => {
        if (!bluetooth_disponivel()) {
            alerta_atencao('Este aparelho não aceita impressão Bluetooth. Use o modo APP no menu.');
            return;
        }

        try {
            await escrever(bytes);
        } catch (erro) {
            if (!erro?.cancelado) alerta_atencao(`Não foi possível imprimir: ${erro?.message ?? 'verifique se a impressora está ligada e perto do aparelho.'}`);
        }
    }, []);

    const imprimir_bilhete = useCallback(async (comprovante) => {
        if (!e_mobile) {
            imprimir_bilhete_navegador(comprovante);
            return;
        }

        if (preferencias.modo === 'APP') {
            window.location.href = `app://${window.location.host}/${comprovante.codigo}/${preferencias.largura}/false`;
            return;
        }

        await imprimir_bluetooth(texto_bilhete(comprovante, preferencias.largura));
    }, [e_mobile, preferencias, imprimir_bluetooth]);

    // a tabela no celular vai sempre para o Bluetooth, qualquer que seja o modo (como no antigo)
    const imprimir_tabela = useCallback(async (filtros) => {
        try {
            const tabela = await buscar_tabela(filtros);

            if (!tabela.campeonatos.length) {
                alerta_atencao('Nenhum jogo encontrado.');
                return;
            }

            if (e_mobile) await imprimir_bluetooth(texto_tabela(tabela, preferencias.largura));
            else imprimir_tabela_navegador(tabela);
        } catch (erro) {
            alerta_erro(erro);
        }
    }, [e_mobile, preferencias.largura, imprimir_bluetooth]);

    return { impressao: preferencias, e_mobile, alternar_modo, alternar_largura, imprimir_bilhete, imprimir_tabela };
}
