import useVoltarFecha from '../../hooks/useVoltarFecha';
import { rotulo_tipo } from '../../utils/casino_types';
import { Count, Divider, DividerText, Header, Icon, Item, Menu, Screen, Text, Title } from './styles';

// menu lateral do cassino (Menu de screens/casino): Esportes, Populares, Tipos e Provedores com
// a quantidade de jogos; o voltar do aparelho fecha
export default function CasinoMenu({ aberto, mostrar_esportes, populares, tipos, provedores, ao_fechar, ao_esportes, ao_jogar, ao_tipo, ao_provedor }) {
    useVoltarFecha(aberto, ao_fechar);

    const escolher = (acao) => () => {
        acao();
        ao_fechar();
    };

    return (
        <>
            <Menu $aberto={aberto}>
                <Header>
                    <Title>Menu</Title>
                    <Icon $colorido onClick={ao_fechar}>close</Icon>
                </Header>
                {mostrar_esportes && (
                    <Item onClick={ao_esportes}>
                        <Text>Esportes</Text>
                        <Icon>sports_soccer</Icon>
                    </Item>
                )}
                <Divider>
                    <Icon>star</Icon>
                    <DividerText>Populares</DividerText>
                </Divider>
                {populares.map((jogo) => (
                    <Item key={jogo.id} onClick={escolher(() => ao_jogar(jogo))}>
                        <Text>{jogo.nome}</Text>
                    </Item>
                ))}
                <Divider>
                    <Icon>checklist</Icon>
                    <DividerText>Tipos</DividerText>
                </Divider>
                {tipos.map(({ tipo, quantidade }) => (
                    <Item key={tipo} onClick={escolher(() => ao_tipo(tipo))}>
                        <Text>{rotulo_tipo(tipo)}</Text>
                        <Count>{quantidade}</Count>
                    </Item>
                ))}
                <Divider>
                    <Icon>factory</Icon>
                    <DividerText>Provedores</DividerText>
                </Divider>
                {provedores.map((provedor) => (
                    <Item key={provedor.id} onClick={escolher(() => ao_provedor(provedor))}>
                        <Text>{provedor.nome}</Text>
                        <Count>{provedor.quantidade}</Count>
                    </Item>
                ))}
            </Menu>
            <Screen $aberto={aberto} onClick={ao_fechar} />
        </>
    );
}
