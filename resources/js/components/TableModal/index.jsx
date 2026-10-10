import { useEffect, useState } from 'react';
import { useTheme } from 'styled-components';

import Backdrop from '../Backdrop';
import useVoltarFecha from '../../hooks/useVoltarFecha';
import { confirmar } from '../../utils/alerts';
import { Button, Buttons, CheckBox, Container, Counter, Header, Icon, Label, List, Message, Text, Title } from './styles';

// escolha dos campeonatos da tabela do vendedor (modals/table do sistema antigo): marca os
// campeonatos e imprime os jogos deles de hoje ou de amanhã
export default function TableModal({ aberto, paises, ao_fechar, ao_imprimir }) {
    const tema = useTheme();
    const [marcados, definir_marcados] = useState([]);
    useVoltarFecha(aberto, ao_fechar);

    // campeonatos de todos os países do menu, numa lista só
    const campeonatos = paises.flatMap(({ campeonatos: do_pais }) => do_pais);

    useEffect(() => {
        if (!aberto) return undefined;

        const ao_teclar = (evento) => { if (evento.key === 'Escape') ao_fechar(); };
        window.addEventListener('keydown', ao_teclar);

        return () => window.removeEventListener('keydown', ao_teclar);
    }, [aberto, ao_fechar]);

    const alternar = (id) => definir_marcados((atuais) => (atuais.includes(id) ? atuais.filter((marcado) => marcado !== id) : [...atuais, id]));

    const limpar = async () => {
        if (await confirmar('Deseja remover os campeonatos já marcados?', tema.principal)) definir_marcados([]);
    };

    const imprimir = (dia) => {
        ao_imprimir(dia, marcados);
        ao_fechar();
    };

    return (
        <>
            <Container $visivel={aberto} role="dialog" aria-modal="true" aria-label="Marque os campeonatos">
                <Header>
                    <Title>
                        Marque os campeonatos
                        <Counter>
                            {marcados.length
                                ? `${marcados.length} ${marcados.length === 1 ? 'campeonato marcado' : 'campeonatos marcados'}`
                                : 'Nenhum campeonato marcado'}
                        </Counter>
                    </Title>
                    <Icon $cor={tema.principal} onClick={ao_fechar} title="Fechar">close</Icon>
                </Header>
                <List>
                    {campeonatos.length ? campeonatos.map((campeonato) => (
                        <Label key={campeonato.id} $marcado={marcados.includes(campeonato.id)}>
                            <CheckBox checked={marcados.includes(campeonato.id)} onChange={() => alternar(campeonato.id)} />
                            <Text>{campeonato.nome}</Text>
                        </Label>
                    )) : <Message>Nenhum campeonato encontrado.</Message>}
                </List>
                <Buttons>
                    <Button type="button" $cor={tema.principal} $secundario onClick={limpar}>
                        <i className="material-icons" aria-hidden="true">delete_outline</i>
                        LIMPAR
                    </Button>
                    <Button type="button" $cor={tema.principal} onClick={() => imprimir('hoje')}>
                        <i className="material-icons" aria-hidden="true">print</i>
                        IMPR. DE HOJE
                    </Button>
                    <Button type="button" $cor={tema.principal} onClick={() => imprimir('amanha')}>
                        <i className="material-icons" aria-hidden="true">print</i>
                        IMPR. DE AMANHÃ
                    </Button>
                </Buttons>
            </Container>
            <Backdrop visivel={aberto} ao_fechar={ao_fechar} />
        </>
    );
}
