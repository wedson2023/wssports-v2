import { useEffect, useRef } from 'react';
import { router } from '@inertiajs/react';

// confere a versão no servidor ao voltar para a aba; versão diferente faz o Inertia recarregar a
// página na versão nova (R-15, FR-050). Durante o envio do código a checagem fica pendente e roda
// logo depois, para nenhuma recarga cortar o envio (FR-050a)
export default function useAtualizacaoVersao(enviando = false) {
    const checagem_pendente = useRef(false);
    const enviando_agora = useRef(enviando);

    enviando_agora.current = enviando;

    useEffect(() => {
        const conferir_versao = () => {
            if (document.visibilityState !== 'visible') return;

            if (enviando_agora.current) {
                checagem_pendente.current = true;
                return;
            }

            router.reload({ only: ['versao'] });
        };

        document.addEventListener('visibilitychange', conferir_versao);

        return () => document.removeEventListener('visibilitychange', conferir_versao);
    }, []);

    useEffect(() => {
        if (!enviando && checagem_pendente.current) {
            checagem_pendente.current = false;
            router.reload({ only: ['versao'] });
        }
    }, [enviando]);
}
