# Quickstart (validação manual): Painel — Clientes

**Spec**: [spec.md](spec.md) · Contratos: [api.md](contracts/api.md), [paginas.md](contracts/paginas.md)

Sem testes automatizados (constituição). Validar no navegador (computador e celular, 360 px) e pela
coleção do Postman.

## 0. Preparação

```bash
php artisan migrate
npm run dev   # ou build
php artisan queue:work --queue=notificacoes,default   # envio das notificações
```

Massa mínima:

- usuários: Admin; Supervisor S1 com Gerente G1; Supervisor S2 com Gerente G2;
- afiliados: A1 (gerente G1), A2 (gerente G2), A3 (sem gerente, antigo);
- clientes: C1 e C2 de A1, C3 de A2, C4 de A3, C5 sem afiliado; C1 com depósito Pix aprovado de
  R$ 100, crédito manual de R$ 20 na carteira Saldo e saque pago de R$ 30; C2 sem depósito e com um
  Pix expirado; C3 com aniversário hoje; C1 com aposta premiada há 1 minuto (janela de 10 min) e um
  encerramento `Registrado` com `creditar_em` futuro;
- saques pendentes de C1 e C3; um aparelho de C1 inscrito nas notificações.

## 1. Alcance (FR-003, FR-003a, FR-003c, SC-005)

1. Admin abre `/painel/clientes`: vê C1 a C5.
2. S1 vê só C1 e C2; G1 vê só C1 e C2; S2 e G2 veem só C3. Ninguém além do Admin vê C4 e C5.
3. Como S1, chamar `GET /api/clientes/{C3}` e `POST /api/saques/{saque de C3}/pagar` → 404.
4. Como S1, aba Saques: só o saque de C1. Caixa clientes de S1: só C1/C2; Apostas: apostas do site só
   de C1/C2.
5. Mudar o gerente de A1 para G2: na hora, C1/C2 passam para S2/G2.

## 2. Cadastros e filtros (US1)

1. Colunas e total; C1 com saldo líquido R$ 90,00 em verde (100 + 20 − 30); C2 em cinza.
2. Sem `clientes.ver_dados_completos`: contatos mascarados; busca pelo telefone completo acha.
3. Filtro "Nunca depositou" → C2; "Gerou Pix e não pagou" → C2; período de hoje + "Aniversariantes"
   → C3; período 25/12 a 05/01 acha aniversário em 02/01.
4. Trocar de página e voltar: filtros mantidos; recarregar: mesma lista (endereço).

## 3. Detalhe e ações (US2)

1. Tocar em C1: gaveta com Dados, Configurações, Pagamentos e Extrato.
2. Editar como G1 tentando tirar o afiliado → "Escolha um afiliado da sua rede."
3. Desativar C1 → sessões do cliente caem.
4. Ajustar saldo +R$ 10 com senha errada → "Senha incorreta." e nada muda; com a senha certa → saldo e
   extrato com autor e motivo.
5. "Ver apostas" → `/painel/apostas` com a etiqueta "Cliente: C1".

## 4. Saques (US3)

1. Pendentes do mais antigo para o mais novo; cartões batem com a lista.
2. Dar baixa manual → Pago com quem e quando; clicar duas vezes rápido em outra aba → "Este saque já
   foi decidido.".
3. Recusar com motivo → saldo do cliente volta.

## 5. Lixeira (US4)

1. Excluir C2 (Admin) → some de Cadastros e aparece na Lixeira; G1 não vê a aba nem a ação.
2. Cadastrar outro cliente com o telefone de C2; restaurar C2 → modal pede telefone novo.

## 6. Ranking (US5)

1. 30 dias / Depósitos: C1 em 1º com 1 depósito de R$ 100.
2. Resultado para a banca: bate com a coluna Resultado do Caixa clientes no mesmo período.
3. Como G1: só C1/C2.

## 7. Antecipar pendentes (US6, SC-004)

1. Como S1, C1: modal mostra 1 prêmio e 1 encerramento com valores.
2. Senha errada → "Senha incorreta."; certa → crédito imediato, extrato com o autor,
   `clientes_antecipacoes` gravado.
3. De novo → "Nenhum prêmio ou encerramento pendente."; rodar `apostas:liquidar_premios` → nada pago
   duas vezes.
4. G1 não vê a ação; `POST .../antecipar` → 403.

## 8. Exportação e notificação (US7)

1. Baixar planilha de cada aba; linhas e valores batem com a tela; contatos mascarados sem a
   permissão.
2. Notificação "Por ID" para C1 → chega no aparelho; "N envios restantes hoje" diminui.
3. Após 3 envios: botão desabilitado e API 422 "Limite de envios de hoje atingido."; Admin muda o
   limite para 5 e consegue enviar.
4. C1 desliga "Avisos da banca" no site → não recebe o próximo.

## 9. Afiliados (FR-003d)

1. `POST /api/afiliados` como S1 sem `usuarios_id` → 422; com G2 (fora da rede) → "O gerente
   escolhido não é da sua rede."
2. `GET /api/afiliados?sem_gerente=1` (Admin) → A3.
3. Aprovar um cadastro público sem gerente → 422.

## 10. Visual e desempenho

1. 360 px: sem rolagem horizontal; cartões; filtros e detalhe em tela cheia.
2. Com 100.000 clientes gerados só no ambiente local (`php artisan tinker` →
   `App\Models\Clientes::factory()->count(100000)->create()`), abrir Cadastros e trocar filtro em até
   2 s (SC-008).
3. Postman: rotas novas e alteradas presentes.
