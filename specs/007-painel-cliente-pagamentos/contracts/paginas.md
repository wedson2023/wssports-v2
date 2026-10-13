# Contrato das páginas: Minha conta e retorno do Mercado Pago

**Feature**: `007-painel-cliente-pagamentos` | **Data**: 2026-10-12

## 1. `GET /minha-conta` → página Inertia `Account`

Props do servidor (além das compartilhadas `tema`, `contatos`, `nome_sistema`):

```json
{ "acesso": "codigo-do-acesso-assistido-ou-null" }
```

O resto vem da API com o token (contracts/api.md seções 1 a 8):

1. Com `acesso` na URL: troca o código (`POST area-cliente/acesso-assistido`), guarda em
   `sessionStorage` e apaga o parâmetro da URL (`history.replaceState`).
2. Sem sessão de cliente nem assistida: `router.visit('/?entrar=1&voltar=/minha-conta')`.
3. Com sessão: `GET minha-conta`; `403` de conta inativa mostra `InactiveAccount`; `401`
   encerra a sessão e volta para `/`.

Layout: `PublicLayout` (tema, sessão). Tela igual a `screens/credits` do antigo; modais novos.

## 2. Home (`/`) — mudanças

- `?entrar=1&voltar=...`: abre o `AuthModal` na aba Entrar e, depois do login de cliente, vai
  para `voltar` (só caminhos internos começando por `/`).
- `?indicacao=CODIGO`: abre o cadastro com o código de indicação (campo oculto).
- `Header`: "Olá, nome" vira link para `/minha-conta` com sessão de cliente.

## 3. `GET /mercado-pago/retorno` → página Inertia `GatewayReturn`

Props: `{ "sucesso": true, "mensagem": "Conta do Mercado Pago vinculada com sucesso." }` ou
`{ "sucesso": false, "mensagem": "Vínculo cancelado no Mercado Pago." }`. Página simples com a
logo, a mensagem e o botão "Fechar" (o painel abriu o fluxo em nova aba).
