import { Button, Row } from './styles';

const valores = [2, 3, 5, 10, 20, 50];

// botões de valor rápido: substituem o valor da aposta (como addValue do sistema antigo)
export default function QuickValues({ ao_escolher }) {
    return (
        <Row>
            {valores.map((valor) => (
                <Button
                    key={valor}
                    type="button"
                    onClick={() => ao_escolher(valor * 100)}
                    title={`Pressione esse botão para inserir ${valor}.00 reais.`}
                >
                    {valor}
                </Button>
            ))}
        </Row>
    );
}
