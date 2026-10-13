import { Close, Container, Header, Item, Title } from './styles';

// itens do menu com os ícones e textos de screens/credits do antigo
const itens = [
    { chave: 'meus_dados', icone: 'edit_calendar', titulo: 'Meus dados', dica: 'Clique para visualizar e editar seus dados.' },
    { chave: 'apostar', icone: 'arrow_back', titulo: 'Realizar aposta', dica: 'Clique para ir para lista de jogos.' },
    { chave: 'sacar', icone: 'download', titulo: 'Solicitar saque', dica: 'Clique para realizar o saque em seguida aguarde o prazo estabelecido pela banca para ir pegar seu saldo.' },
    { chave: 'depositar', icone: 'account_balance_wallet', titulo: 'Realizar depósito', dica: 'Clique para realizar um depósito.' },
    { chave: 'indicar', icone: 'group_add', titulo: 'Indique um amigo', dica: 'Faça indicações e ganhe com isso.' },
    { chave: 'atendimento', icone: 'support_agent', titulo: 'Atendimento', dica: 'Clique para ser direcionado para o suporte.' },
    { chave: 'regras', icone: 'receipt', titulo: 'Regras', dica: 'Clique para ir para página de regras.' },
    { chave: 'sair', icone: 'exit_to_app', titulo: 'Sair', dica: 'Clique para fazer o logout.' },
];

// menu da tela de créditos (Menu de screens/credits): coluna à direita no desktop e gaveta no
// mobile; itens_ocultos esconde Indique um amigo e Atendimento quando não se aplicam
export default function AccountMenu({ aberto, ao_fechar, itens_ocultos = [], ao_escolher }) {
    return (
        <Container $aberto={aberto}>
            <Header>
                <Title>Menu</Title>
                <Close className="material-icons" onClick={ao_fechar} role="button" aria-label="Fechar menu">close</Close>
            </Header>
            {itens.filter(({ chave }) => !itens_ocultos.includes(chave)).map(({ chave, icone, titulo, dica }) => (
                <Item key={chave} type="button" title={dica} onClick={() => ao_escolher(chave)}>
                    <i className="material-icons" aria-hidden="true">{icone}</i>
                    <span>{titulo}</span>
                </Item>
            ))}
        </Container>
    );
}
