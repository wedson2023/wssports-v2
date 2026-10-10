import { useEffect, useState } from 'react';
import { telas } from '../theme/tokens';

const consulta = `(max-width: ${telas.mobile}px)`;

// true quando a largura atual é de mobile (até 900px); acompanha girar a tela e redimensionar
export default function useTelaMobile() {
    const [e_mobile, definir_e_mobile] = useState(() => window.matchMedia(consulta).matches);

    useEffect(() => {
        const media = window.matchMedia(consulta);
        const ao_mudar = (evento) => definir_e_mobile(evento.matches);

        media.addEventListener('change', ao_mudar);

        return () => media.removeEventListener('change', ao_mudar);
    }, []);

    return e_mobile;
}
