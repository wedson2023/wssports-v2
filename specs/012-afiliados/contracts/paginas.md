# Contrato das páginas: Afiliados

Páginas Inertia no `PublicLayout` (tema e sessão). Nenhuma prop de dados vinda do servidor: o
painel busca tudo na API com o token do afiliado (`contracts/api.md` §1).

## 1. `/cadastro-afiliado` → `pages/AffiliateLanding`

**Referência visual**: `wssports.bet/resources/js/screens/new-affiliated` (`index.js` e `styles.js`).

- Cabeçalho escuro (`#222`) com a logo e os botões **Cadastrar-se** (fundo na cor do tema) e
  **Entrar** (contorno na cor do tema); grade de duas colunas no desktop, coluna única abaixo de
  900 px, como no antigo.
- Texto "Seja um afiliado!", parágrafo do antigo (com os erros de digitação corrigidos:
  "excelentes") e botão **Quero me afiliar**.
- Imagem `/images/afiliado.jpg` (copiada do antigo).
- **Cadastrar-se** e **Quero me afiliar** abrem o `AffiliateSignupModal`.
- **Entrar** abre o `AuthModal` existente na aba "entrar"; com sessão de afiliado, vai para
  `/afiliados`.
- Link "Esqueci minha senha" abaixo dos botões do cabeçalho (desktop) e no rodapé da descrição
  (celular), abrindo o `AffiliateRecoveryModal`.
- Com sessão de afiliado já aberta, os botões do cabeçalho viram **Meu painel**.

### `AffiliateSignupModal`

**Referência visual**: `wssports.bet/resources/js/modals/affiliated` (título "Cadastro afiliados",
lista de campos com ícone Material à esquerda na cor do tema, botão "CRIAR CONTA").

Campos, na ordem do antigo: nome, login, senha, **confirmação de senha** (novo), e-mail, CPF
(máscara `000.000.000-00`), cidade, estado (lista de UFs + Estrangeiro), endereço, telefone
(máscara `(00) 00000-0000`), tipo de chave Pix e chave (máscara conforme o tipo, desabilitada sem
tipo).

Melhorias aprovadas (padrão do `AuthModal`): rótulo visível; erro embaixo do campo (422) com o
campo em destaque; dados mantidos após erro; olho para mostrar/ocultar a senha; Esc, fundo e botão
Voltar do navegador fecham (`useVoltarFecha`); spinner no botão durante o envio; 429 em alerta.
Sucesso: alerta "Cadastro recebido! Vamos analisar e avisaremos por e-mail." e o modal fecha.

### `AffiliateRecoveryModal`

Duas etapas no mesmo modal: (1) login ou e-mail → "Enviar código"; (2) código de 6 dígitos, nova
senha e confirmação → "Alterar senha". Mesmo padrão de campos e erros. Sucesso: alerta e abre o
`AuthModal` na aba "entrar".

## 2. `/afiliados` → `pages/AffiliatePanel`

**Referência visual**: `wssports.bet/resources/js/screens/affiliated` (`index.js` e `styles.js`).

Sem sessão `tipo: 'afiliado'` válida → `router.visit('/?entrar=1')`. 401/403 da API → sessão
encerrada e volta para `/?entrar=1`.

Do antigo, mantidos:
- cabeçalho com seta de voltar (vai para `/`) e "Olá, {nome}!";
- logo; "Meu Saldo" com o `valor_a_receber` (negativo aparece com sinal);
- linha abaixo do saldo: "Última prestação R$ X em dd/mm hh:mm" (no antigo, "Saldo anterior");
  sem prestação: "--,--";
- mensagem "Obs.: Sua comissão é de X% em cima de {tipo}." (sem a parte da data da prestação, que
  agora está na linha acima);
- tabela do extrato com as colunas ID, OP., VALOR, COMISSÃO, DATA (comissão negativa com "-") e
  "Nenhum registro encontrado." sem itens.

Acréscimos aprovados:
- **Copiar meu link**: botão abaixo do saldo; copia `link` e mostra alerta "Link copiado!".
- **Abas** acima da tabela: Extrato | Clientes | Prestações (cor do tema na aba ativa).
  - Extrato: filtro de período (duas datas + "Filtrar") e filtro Em aberto/Prestados; paginação
    "Carregar mais" (30 por vez).
  - Clientes: tabela NOME, CADASTRO, DEPOSITADO.
  - Prestações: tabela PERÍODO, APURADO, PAGO, OBS.
- **Menu** (ícone `more_vert` no cabeçalho, como o menu comentado no antigo): Meus dados, Alterar
  senha, Sair.

### `AffiliateProfileModal` (sobre o `ModalCard`)

Nome, login, e-mail e CPF só leitura (com aviso "Para alterar, fale com a banca."); telefone,
cidade, estado, endereço, tipo de chave Pix e chave editáveis, com máscaras e erro por campo.

### `AffiliatePasswordModal` (sobre o `ModalCard`)

Senha atual, nova senha e confirmação, com olho e erro por campo. Sucesso: troca o token da sessão
pelo devolvido pela API e alerta "Senha alterada!".

## 3. Alterações em telas existentes

| Arquivo | Alteração |
|---|---|
| `components/Footer` | "Afiliados" vira link (`Link`) para `/cadastro-afiliado` |
| `components/AuthModal` | depois de entrar, sessão `tipo: 'afiliado'` vai para `/afiliados` |
| `hooks/useSessao.js` | login e logout do tipo `afiliado`; ordem de tentativa (research R-04); nome do afiliado pelo `GET /area-afiliado/meus-dados` |
| `utils/session.js` | comentário do formato: `tipo` aceita `'afiliado'` |
