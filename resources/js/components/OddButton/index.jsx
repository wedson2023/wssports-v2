import { Button, Letter, Lock, Odd } from './styles';

// botão de cotação: normal, selecionado, bloqueado (cadeado) e piscando na variação (FR-023)
export default function OddButton({ letra = null, cotacao, selecionado = false, variacao = null, largo = false, titulo, ao_clicar }) {
    const valor = Number(cotacao);
    const bloqueado = !(valor > 0);

    return (
        <Button
            role="button"
            title={titulo}
            $largo={largo}
            $selecionado={selecionado}
            $bloqueado={bloqueado}
            $variacao={variacao}
            onClick={bloqueado ? undefined : ao_clicar}
        >
            {bloqueado ? (
                <Lock $selecionado={selecionado}>lock</Lock>
            ) : (
                <>
                    {letra && (
                        <Letter>
                            <strong>{letra}</strong>
                        </Letter>
                    )}
                    <Odd>
                        <span>{valor.toFixed(2)}</span>
                    </Odd>
                </>
            )}
        </Button>
    );
}
