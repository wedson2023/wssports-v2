import { useEffect, useState } from 'react';
import { centavos_para_texto, para_centavos } from '../utils/money';

// texto do campo "valor" ligado ao valor em centavos do cupom: o visitante digita livremente e
// o campo acompanha mudanças de fora (botões de valor rápido, Limpar)
export default function useCampoValor(valor_centavos, definir_valor) {
    const [texto, definir_texto] = useState(() => (valor_centavos ? centavos_para_texto(valor_centavos) : ''));

    useEffect(() => {
        if (para_centavos(texto) !== valor_centavos) {
            definir_texto(valor_centavos ? centavos_para_texto(valor_centavos) : '');
        }
        // só reage ao valor do cupom; o texto digitado não dispara a sincronização
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [valor_centavos]);

    const ao_mudar = (evento) => {
        definir_texto(evento.currentTarget.value);
        definir_valor(para_centavos(evento.currentTarget.value));
    };

    return { texto, ao_mudar };
}
