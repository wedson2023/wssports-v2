# Contrato das telas — Painel e Dashboard

Referências visuais do sistema antigo (Princípio VIII): `screens/adm/components/header` (barra
superior), `screens/adm/components/bar` (barra de título), `screens/adm/dashboard` (seções),
`modals/Combination` e `modals/ticket`. Diferenças aprovadas: menu lateral, cartões e gráficos
(research R-02).

## 1. Login e entrada no painel

| Quem | Depois do login (AuthModal) | Acesso ao painel |
|---|---|---|
| Admin, Supervisor, Gerente | `router.visit('/painel')` | — |
| Vendedor | continua na tela de apostas | saudação do cabeçalho ("Olá, Nome") vira link para `/painel` |
| Cliente / afiliado | como hoje | `/painel` leva de volta para `/` |

A sessão de usuário passa a guardar `nome` e `funcao` (de `GET /auth/eu`).

## 2. `/painel` — página `Dashboard`

```text
┌───────────────────────────────────────────────────────────────┐
│ [logo]  ☰                         [APOSTAR]  [SAIR]           │  PanelShell (barra preta)
├──────────────┬────────────────────────────────────────────────┤
│ Menu         │ Dashboard                     Olá, Nome · Função│  barra de título
│ ▸ Dashboard  │ ┌ Apurado do dia ─────────────────────── ⟳ ┐   │
│              │ │ [cartões]          [gráfico de barras]   │   │
│              │ └──────────────────────────────────────────┘   │
│              │ ┌ Situação dos jogos ⟳ ┐ ┌ Apostas ⟳ ┐        │
│              │ │ rosca + legenda       │ │ rosca + lista │      │
│              │ ┌ Combinação de risco ──────────────── 🔔 ⟳ ┐  │
│              │ │ tabela com barra de risco · ver bilhetes  │  │
│              │ ┌ Apostas por um jogo ─────────────────── ⟳ ┐  │
│              │ ┌ Vendedores ┐ ┌ Jogos + apostados ┐ ┌ Opções ┐│
│              │ ┌ Caixa online ─────────────────────────── ⟳ ┐ │
└──────────────┴────────────────────────────────────────────────┘
```

- Ordem fixa das seções = ordem do antigo + Caixa online no fim. Só aparecem as de `secoes`.
- Celular (≤ 900 px, breakpoint do antigo): menu vira gaveta (☰), seções em uma coluna, tabelas com
  rolagem horizontal só dentro da seção; cartões do Apurado em 2 colunas (SC-008).
- Tema: cor principal e fundo do `ThemeProvider` atual; gráficos usam as cores do tema.
- Sem sessão de usuário: `router.visit('/')`.

## 3. Estados de cada seção (`DashboardSection`)

| Estado | Exibição |
|---|---|
| Carregando (1ª vez) | esqueleto no formato da seção |
| Atualizando | dados atuais + ícone ⟳ girando |
| Sem dados | ícone + "Sem movimento hoje." (sem gráfico) |
| Erro | mensagem do backend + botão "Tentar novamente" (só esta seção) |
| 403 | a seção some |

Atualização automática a cada 60 s com a aba visível; para com a aba oculta e atualiza ao voltar.

## 4. Apurado do dia

- Cartões (ícone, rótulo, valor): na ordem do antigo — Limite geral, Limite duplo, Limite simples
  (Vendedor, cadeado aberto/fechado), Entradas, Depósitos online (Admin/Supervisor), Em aberto,
  Premiadas, Perdidas, Saídas, Comissão do vendedor, Lançamentos, Retiradas, Comissão do gerente
  (Gerente+), **Saldo líquido** em destaque.
- Cartões em `provisorios`: valor R$ 0,00 + selo "em breve".
- Gráfico de barras: Entradas × Saídas × Comissões (vendedor + gerente) × Saldo líquido.
- `title` de cada cartão com o texto explicativo do antigo.

## 5. Situação dos jogos e Apostas

Gráfico de rosca + legenda com quantidade (e valor em Apostas), mesmos rótulos e ícones do antigo
(`bar_chart`, `access_time`, `watch_later`/`emoji_events`, `refresh`, `clear`/`delete`).

## 6. Combinação de risco

- Colunas: Jogos · Opções · Bilhetes · Valor · Prêmio (barra proporcional ao maior prêmio) · 👁.
- 👁 abre `CombinationModal` com a lista de códigos; cada código abre o `TicketModal`.
- Aviso só do agendador: a cada minuto (aba visível) a página consulta
  `GET /dashboard/combinacoes-risco/avisos?depois_de=<último id visto>`; cada aviso devolvido gera
  balão "Nova combinação de risco" + som e destaca por 60 s a linha com a mesma `chave`.
  Recarregar a página ou tocar em ⟳ não gera aviso. Ícone 🔔 no título liga/desliga o som
  (guardado no aparelho).

## 7. Apostas por um jogo

Lista: posição · Apostador · Prêmio (barra) · 👁 (abre `TicketModal`).

## 8. Rankings

Barras horizontais (Chart.js) com posição, nome, quantidade e valor; até 10 itens.

## 9. Caixa online

Cartões: Entradas, Em aberto, Premiadas, Perdidas, Saídas, Encerramentos, Depósitos, Saques pagos e
Saldo líquido online (destaque); gráfico de barras Entradas × Saídas × Encerramentos × Saldo e,
ao lado, Depósitos × Saques.

## 10. Componentes e hooks novos

| Nome | Pasta | Papel |
|---|---|---|
| `Dashboard` | `pages/Dashboard` | página `/painel` |
| `PanelShell` | `components/PanelShell` | barra superior, menu lateral/gaveta, título |
| `DashboardSection` | `components/DashboardSection` | moldura da seção e estados |
| `StatCard` | `components/StatCard` | cartão de valor (selo "em breve", cadeado) |
| `ChartCanvas` | `components/ChartCanvas` | Chart.js com `import()` dinâmico |
| `RiskCombinations` | `components/RiskCombinations` | tabela da combinação |
| `SingleGameBets` | `components/SingleGameBets` | Apostas por um jogo |
| `RankingChart` | `components/RankingChart` | rankings em barras horizontais |
| `CombinationModal` | `components/CombinationModal` | bilhetes da combinação |
| `useSecaoDashboard` | `hooks/useSecaoDashboard.js` | busca, cache, atualizar e intervalo |
| `usePainel` | `hooks/usePainel.js` | `GET /auth/eu`, guarda de acesso |
| — | `utils/charts.js` | cores e opções padrão dos gráficos |
