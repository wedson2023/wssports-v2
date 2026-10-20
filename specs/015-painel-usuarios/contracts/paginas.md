# Contrato das telas — Usuários do painel

Referências visuais do antigo (Princípio VIII): `screens/adm/register/*`, `modals/register/*` e o menu
"Usuários" de `screens/adm/components/header`. Visual do painel da spec 014 (barra, menu lateral,
seções escuras, cartões, temas). Diferenças aprovadas: Clarifications.

## 1. Menu (PanelShell)

```text
▸ Dashboard
▾ Usuários
    Supervisores   (Admin)
    Gerentes       (Admin, Supervisor)
    Vendedores     (Admin, Supervisor, Gerente)
```

Grupo aparece com `usuarios.listar`; subitem pela função do usuário logado. Vendedor: sem o grupo.

## 2. Páginas `/painel/supervisores`, `/painel/gerentes`, `/painel/vendedores` (`PanelUsers`)

```text
┌ Lista de vendedores – total 42 ──────────────── [🔍 busca] [+ Novo Vendedor] ┐
│ [Ativos] [Lixeira]                                                           │
│ ID │ Nome │ Login │ Comissão │ Gerente │ Telefone │ Ativo │ Criado │ Alterado │ Ações │
│ 12 │ Ana  │ ana   │ 5/7/10   │ Bruno   │ (82)...  │ ● Sim │ 20/10  │ 20/10    │ ✎ 🔑 ⏻ 🗑 │
└───────────────────────────────── ‹ 1 2 3 › ──────────────────────────────────┘
```

- Colunas por função (FR-006); Comissão e Gerente só em vendedores; Vendedores em gerentes;
  Gerentes e Vendedores em supervisores; coluna do superior só para quem está acima dele.
- Ações: ✎ editar, 🔑 permissões, ⏻ ativar/desativar (confirmação "Isso também vai desativar N
  usuários abaixo dele.", N = vendedores do gerente ou gerentes + vendedores do supervisor), 🗑 excluir (confirmação); Lixeira: ↺ restaurar. Cada ação só com a
  permissão (R-08).
- Celular (≤ 900 px): cartões com nome, login, função do superior, ativo e ações.
- Estados: carregando (esqueleto), vazio ("Nenhum usuário encontrado."), erro com tentar de novo.

## 3. Modal de cadastro/edição (`UserFormModal`)

Abas por função (cadastro só mostra Dados; Permissões só na edição):

| Função | Abas |
|---|---|
| Vendedor | Dados · Comissões · Limites e regras · Permissões |
| Gerente | Dados · Regras da equipe · Permissões |
| Supervisor | Dados · Regras da rede · Permissões |

- **Dados**: Nome, Login (só leitura na edição; letras sem acento, números, ponto, hífen ou
  sublinhado), Senha, Confirme a senha, Superior (campo com busca paginada, "(inativo)" ao lado do
  nome, quando aplicável), Telefone (máscara), Endereço, Ativo (edição). No cadastro de vendedor, Comissão 1, 2 e
  3 jogos.
- **Comissões**: duas grades (Pré-jogo e Ao vivo) de 1 a 12 jogos, em %, e Comissão por prêmio.
- **Limites e regras**: grupos Limites (geral, duplo, simples), Apostas (realizar, cancelar e tempo,
  valor mínimo e máximo, odd mínima, prêmio máximo, multiplicador, opções mínimas e máximas, ganho
  múltiplo), Ao vivo (habilitado, minuto limite, cotação máxima, delay), Jogos (período, data de
  travamento, jogadores, esportes permitidos, outros esportes), Criar Aposta e Bilhete (mensagem).
- **Regras da equipe / da rede**: os campos de Comissões e Limites e regras em branco; só os
  alterados são enviados; na rede, confirmação "Isso vai alterar N vendedores".
- Sem `usuarios_configuracoes.editar`: abas de regras só para leitura (equipe/rede escondidas).
- Erro por campo, carregando no salvar, Esc/fundo/voltar fecham.

## 4. Permissões (`PermissionsModal` e aba Permissões)

```text
Usuários                         [Marcar todas] [Desmarcar todas]
  Cadastrar vendedor   Cria vendedores na sua rede          ( ●)
  Editar vendedor      Altera dados e superior do vendedor  (● )
Apostas ...
```

Só as permissões que quem edita tem e a função do alvo pode usar, vindas do servidor em `disponiveis`
(contracts/api §9); cada chave grava na hora (volta em erro).

## 5. Componentes e hooks novos

| Nome | Pasta | Papel |
|---|---|---|
| `PanelUsers` | `pages/PanelUsers` | página das três listas |
| `UsersTable` | `components/UsersTable` | tabela/cartões e ações |
| `PanelTabs` | `components/PanelTabs` | abas (Ativos/Lixeira e abas dos modais) |
| `Pagination` | `components/Pagination` | paginação |
| `UserFormModal` | `components/UserFormModal` | cadastro e edição |
| `CommissionFields` | `components/CommissionFields` | grades de comissão |
| `BettingRulesFields` | `components/BettingRulesFields` | limites e regras |
| `PermissionsPanel` | `components/PermissionsPanel` | grupos e chaves |
| `PermissionsModal` | `components/PermissionsModal` | modal de permissões |
| `ToggleSwitch` | `components/ToggleSwitch` | chave liga/desliga |
| `useUsuariosPainel` | `hooks/useUsuariosPainel.js` | lista, busca, paginação |
| — | `utils/permissions.js` | nomes e grupos das permissões |
