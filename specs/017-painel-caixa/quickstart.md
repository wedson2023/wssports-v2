# Quickstart: Painel — Caixa (validação manual)

**Spec**: [spec.md](spec.md) · **Contratos**: [contracts/api.md](contracts/api.md),
[contracts/paginas.md](contracts/paginas.md) · **Dados**: [data-model.md](data-model.md)

## 0. Preparação

```bash
composer install             # traz phpoffice/phpspreadsheet
php -m | grep -E "zip|xml|xmlwriter|gd"   # extensões exigidas pela planilha (também no servidor)
php artisan migrate          # tabelas de caixa, coluna da comissão do gerente e permissões
npm run build                # ou npm run dev
```

- Usuários: Admin; Supervisor S; Gerentes G1 e G2 (de S); Vendedores V1 e V2 (de G1) e V3 (de G2).
- Conferir que todos receberam as permissões `caixa.*` da função (tela de permissões, spec 015).
- Validar nos temas escuro e claro e em 360 px de largura.

## 1. Acesso e alcance (FR-001, FR-002, FR-026, FR-032)

1. Admin: menu "Caixa" com as abas Rede e Clientes; a lista mostra S.
2. G1: aba Rede com V1 e V2; sem aba Clientes; `GET /api/caixa/clientes/resumo` → 403.
3. V1: só o próprio caixa; `GET /api/caixa/usuarios` → 403; `?usuarios_id=` de V2 → 403.
4. G1 pede `GET /api/caixa/vendedores/{V3}/transacoes` → 403.

## 2. Consulta do período (US1, FR-004 a FR-012)

1. Como V1, fazer hoje: aposta A de R$ 100,00 (2 palpites), aposta B de R$ 50,00 (1 palpite, ao vivo)
   e cancelar uma aposta C. Apurar A como vencedora (prêmio R$ 300,00) e deixar B em aberto.
2. Admin → Hoje: Entradas R$ 150,00; Em aberto R$ 50,00; Saídas R$ 300,00; C não aparece.
3. Conferir que os cartões de Hoje são iguais ao Apurado da Dashboard (SC-002).
4. Aba Ao vivo: só B; aba Pré-jogo: só A; lançamentos e saldo somem fora de Todas.
5. Descer S → G1 → V1 pela lista; a trilha mostra o caminho; clicar em "Banca" volta.
6. A soma das linhas de G1 bate com os cartões de G1 (SC-003).
7. Personalizado com "De" > "Até" → erro no campo; período de 2 anos → erro.

## 3. Comissão do gerente (US5, FR-007, FR-027)

1. Admin → linha de G1 → Comissão do gerente 10% → salvar.
2. Com líquido de G1 positivo no período, a comissão é 10% dele; com líquido ≤ 0, R$ 0,00.
3. Escolher "Ontem" e "Hoje" separados e "Esta semana": a dica explica que a semana pode diferir da
   soma dos dias.
4. G1 não vê a ação Comissão do gerente; via API → 403.

## 4. Lançamentos (US3, FR-014 a FR-016)

1. G1 → V1 → Lançamento: Suprimento R$ 50,00 agora; Retirada R$ 20,00 agora.
2. Saldo de caixa de V1 sobe R$ 30,00; cartões Lançamentos/Retiradas mostram os valores.
3. Lançar com data futura → erro; valor 0 → erro.
4. Transações → excluir a retirada → saldo volta; o item aparece riscado com "excluído por G1".

## 5. Prestação de contas (US2, FR-017 a FR-022a)

1. G1 → V1 → Prestar contas: o resumo soma com o apurado (SC-005); "Apostas em aberto: 1 (R$ 50,00)".
2. Informar recebido = apurado, senha errada → "Senha incorreta."; senha certa → saldo de caixa zera.
3. Nova prestação logo depois sem movimento → "Nenhum movimento a prestar no período.".
4. Apurar B como vencedora depois da prestação: o prêmio aparece no saldo de caixa de V1 (próxima
   prestação), não some (Clarifications Q1).
5. Prestar contas informando R$ 20,00 a menos que o apurado: o aviso "Fica para a próxima prestação:
   R$ 20,00" aparece; depois o saldo de caixa mostra R$ 20,00 (Clarifications Q2).
6. Transações → Desfazer a última prestação com motivo e senha: fica "desfeita" com o motivo; o
   saldo volta; a prestação anterior não tem a opção de desfazer.
7. Lote: G1 → botão "Prestar contas de todos" no cabeçalho (ou S → linha de G1 → Prestar contas de
   todos): V1 e V2 com valores; um sem movimento é pulado; as prestações saem com o mesmo lote.
8. Abrir duas abas e confirmar a mesma prestação nas duas: a segunda recebe 409.
9. Lançamento já prestado: sem a opção de excluir; via API → 422.

## 6. Limites e gráfico (US5, US6)

1. G1 → V1 → Adicionar limite: +500 no geral → a linha mostra o novo limite; o Apurado de V1 também.
2. V1 → Gráfico: barras "1" e "2" com uma aposta cada.

## 7. Caixa clientes (US4, FR-023 a FR-026)

1. Com um cliente: aposta online de R$ 20,00 com saldo real, outra com saldo de promoção, um depósito
   aprovado, um saque pago, rodadas de cassino com saldo real, uma promoção concedida e uma
   conversão de bônus.
2. Admin → Clientes → Hoje: Esportes igual à Caixa online da Dashboard; a aposta com promoção não
   entra nas Entradas.
3. Cassino igual ao relatório do cassino no mesmo período (sem rodadas com saldo de promoção).
4. Resultado do site = saldo líquido + resultado do cassino − comissão dos provedores − convertidos.
5. A lista mostra o cliente com as colunas; busca por login encontra.

## 8. Exportação (US6, FR-030)

1. Admin → Baixar planilha (rede, Hoje): o .xlsx abre com período, totais e uma linha por supervisor,
   valores em moeda e iguais à tela.
2. Linha de V1 → Baixar planilha; aba Clientes → Baixar planilha.
3. G1 sem `caixa.exportar` (retirar na tela de permissões): o botão some; via API → 403.

## 9. Dashboard (FR-034)

1. Abrir a Dashboard como G1: Lançamentos, Retiradas e Comissão do gerente com os valores reais do
   dia e sem o selo "em breve".
2. `app/Fakes/DadosFake.php` não tem mais `caixa_usuario` nem `percentual_comissao_gerente`.

## 10. Celular e estados (FR-035 a FR-038, SC-006)

1. 360 px: cartões em 2 colunas, linhas viram cartões, trilha vira "‹ Voltar", modais em tela cheia,
   nenhuma rolagem horizontal, alvos de toque ≥ 40 px.
2. Período sem movimento → mensagem de vazio; derrubar a rede → erro com "Tentar novamente".
3. Retirar uma permissão com a tela aberta → a próxima ação mostra a mensagem do servidor.
