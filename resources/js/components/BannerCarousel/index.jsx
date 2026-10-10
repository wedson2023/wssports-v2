import { useEffect, useState } from 'react';
import { Arrow, Container, Image, Slide, Track } from './styles';

const intervalo_ms = 5000;

// carrossel de banners: troca a cada 5s, em loop, sem miniaturas, status nem indicadores (FR-019)
export default function BannerCarousel({ banners }) {
    const [indice, definir_indice] = useState(0);
    const quantidade = banners.length;

    useEffect(() => {
        if (quantidade < 2) return undefined;

        const temporizador = setInterval(() => definir_indice((atual) => (atual + 1) % quantidade), intervalo_ms);

        return () => clearInterval(temporizador);
    }, [quantidade, indice]);

    if (!quantidade) return null;

    const ir_para = (passo) => definir_indice((atual) => (atual + passo + quantidade) % quantidade);

    return (
        <Container>
            <Track $indice={indice}>
                {banners.map(({ imagem, link }) => (
                    <Slide
                        key={imagem}
                        href={link ?? undefined}
                        target={link ? '_blank' : undefined}
                        rel={link ? 'noopener noreferrer' : undefined}
                        title={link ? 'Clique para acessar o link.' : undefined}
                        $com_link={Boolean(link)}
                    >
                        <Image src={imagem} alt="" />
                    </Slide>
                ))}
            </Track>
            {quantidade > 1 && (
                <>
                    <Arrow type="button" $lado="anterior" onClick={() => ir_para(-1)} aria-label="Banner anterior" />
                    <Arrow type="button" $lado="proximo" onClick={() => ir_para(1)} aria-label="Próximo banner" />
                </>
            )}
        </Container>
    );
}
