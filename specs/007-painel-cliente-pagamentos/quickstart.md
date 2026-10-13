# Quickstart: validação manual do painel do cliente e dos pagamentos

**Feature**: `007-painel-cliente-pagamentos` | **Data**: 2026-10-12

Sem testes automatizados (constituição): cada seção é um roteiro manual ligado aos cenários da
[spec](spec.md). Rotas em [contracts/api.md](contracts/api.md); dados em
[data-model.md](data-model.md).

## 1. Preparação

1. No `.env` (credenciais **de teste** da aplicação do programador no Mercado Pago):

   ```dotenv
   MERCADO_PAGO_CLIENT_ID=
   MERCADO_PAGO_CLIENT_SECRET=
   MERCADO_PAGO_PUBLIC_KEY=
   MERCADO_PAGO_WEBHOOK_SECRET=
   MERCADO_PAGO_PAYOUTS_CHAVE_PRIVADA=
   ```

2. Cadastrar na aplicação do Mercado Pago a URL de retorno `{APP_URL}/mercado-pago/retorno` e a
   URL de notificações `{APP_URL}/api/webhooks/mercado-pago`. Localmente, expor o servidor por um
   túnel HTTPS (ex.: `cloudflared`) ou validar só pela conferência a cada minuto (seção 4.5).

3. Comandos:

   ```powershell
   php artisan migrate
   php artisan db:seed --class=PapeisPermissoesSeeder
   php artisan db:seed --class=ClientesPromocoesSeeder
   npm run build
   php artisan serve
   php artisan queue:work      # outro terminal
   php artisan schedule:work   # outro terminal
   ```

4. Contas de teste do Mercado Pago: um vendedor (conta da banca, para vincular) e um comprador;
   cartões de teste da documentação (aprovado, recusado `OTHE`, em análise `CONT`).

## 2. Vínculo do gateway (US8)

1. Como admin: `POST /api/configuracoes/gateway/mercado-pago/autorizar` → abrir a `url`, entrar com
   o vendedor de teste e autorizar → página "Conta do Mercado Pago vinculada com sucesso."
2. `GET /api/configuracoes/gateway` → `Vinculado` com a data, sem tokens.
3. Repetir cancelando na tela do Mercado Pago → mensagem de cancelamento; vínculo anterior mantido.
4. Abrir `/mercado-pago/retorno?code=x&state=forjado` → recusado.
5. Como supervisor, gerente e vendedor: as três rotas do gateway → `403`.
6. `php artisan mercado_pago:renovar_token` com `expira_em` ajustado para daqui a 10 dias → token
   renovado (`renovado_em`).

## 3. Tela e extrato (US1)

1. Logar um cliente com apostas, bônus e movimentações; abrir `/minha-conta` → saudação, logo,
   três cartões com anterior e meta, aviso das 24 h; comparar lado a lado com `screens/credits`
   do antigo (cores do tema, menu lateral no desktop, gaveta no mobile).
2. Filtros: Palpites + Vencedor; outra operação desabilita a situação; Saques mostra pendentes,
   pagos, recusados e devoluções.
3. Tocar num palpite → bilhete; num saque recusado → motivo.
4. Rolar até o fim com mais de 30 movimentações → próxima página.
5. Abrir `/minha-conta` deslogado → login e volta.
6. Desativar o cliente no painel e atualizar → "Pagamento pendente ou conta desativada."

## 4. Depósitos (US2, US4)

1. Cliente sem CPF/e-mail → Depositar → aviso, Meus dados abre, salvar → depósito continua.
2. Pix de R$ 50,00 com "Aceito receber bônus" → QR Code (desktop), copia e cola, copiar mostra
   "Código Pix copiado". Pagar com o comprador de teste → em até 10 s "Depósito realizado com
   sucesso!", saldo e extrato atualizados.
3. Conferir no Mercado Pago (painel da conta master) a comissão de 3% (R$ 1,50) no pagamento.
4. Reenviar a mesma notificação (Postman, com assinatura válida) → nada muda (SC-003). Assinatura
   inválida → `notificacoes_mercado_pago.assinatura_valida = false`, nada creditado.
5. Gerar Pix e não pagar com `minutos_expiracao_pix = 1` → após o comando `depositos:conferir`, a
   tela informa a expiração.
6. Cashback 5% e depósito de R$ 100,00 → extrato: depósito R$ 100,00 + cashback R$ 5,00; rollover
   do depósito R$ 105,00.
7. Promoção de primeiro depósito com rollover 5x e "rollover primeiro depósito" ligado → primeiro
   depósito de R$ 100,00: bônus creditado, rollover do depósito R$ 505,00 (com cashback 5%).
8. Promoção do afiliado `AFI10` e outra geral → cliente `AFI10` recebe a do afiliado; cliente sem
   afiliado, a geral.
9. Cartão aprovado em 3x de R$ 300,00 → parcelas com total exibidas antes; saldo +R$ 300,00.
10. Cartão recusado → mensagem clara, nada creditado. Cartão em análise → crédito só na aprovação.
11. Estornar o depósito de cartão no Mercado Pago com o cliente sem saldo → saldo negativo em
    vermelho, "Estorno de depósito" no extrato; tentar apostar e sacar → recusado; novo depósito
    cobre o negativo primeiro.
12. Desvincular o gateway → depósito mostra "Depósitos indisponíveis no momento. Tente mais tarde."

## 5. Saques (US3)

1. Cliente com R$ 200,00, cadastro completo e rollover cumprido → saque de R$ 100,00 → saldo
   R$ 100,00, saque pendente, mensagem das 24 h.
2. Modo manual: `POST /api/saques/{id}/pagar {"forma":"manual"}` → Pago; outro saque recusado com
   motivo → devolução no saldo e motivo no extrato.
3. Rollover pendente R$ 30,00 → recusa com o valor. Limites diários de `clientes_configuracoes`
   (quantidade e valor) → recusa com o limite. `bloquear_saque` ou saldo negativo → recusa.
4. Cadastro sem chave Pix → aviso, Meus dados abre, ao salvar o saque é retomado.
5. Dois saques ao mesmo tempo (duas abas) somando mais que o saldo → só um passa.
6. Modo automático, limite R$ 1.000,00 (Payouts liberado no teste): R$ 300,00 → Pago pelo
   Mercado Pago; R$ 2.500,00 → fila manual. Sem Payouts liberado → falha vai para a fila com
   `motivo_falha`, sem devolver.

## 6. Bônus (US5)

1. Bônus cumprido, sem outro pendente, liberado R$ 80,00 e conversão máxima R$ 50,00 → Converter →
   saldo +R$ 50,00, carteira de bônus zerada.
2. Mensagens de recusa: sem liberado; excluir com liberado; excluir cumprido/excluído; expirado.
3. Promoção com 1 dia para cumprir; ajustar `expira_em` para o passado → `php artisan
   bonus:expirar` → "Bônus expirado" no extrato, "Expirado" na coluna.
4. Bônus cumprido antes do prazo → não expira.
5. Painel: cadastrar promoção com dias, rollover primeiro depósito (só em Primeiro depósito),
   afiliado e quantidade de indicações (só em Indicação).

## 7. Meus dados (US6)

CPF inválido/duplicado, menor de 18 anos, máscara da chave Pix por tipo, chave e-mail inválida,
senha fraca → mensagens do FR-042.

## 8. Acesso assistido (US7)

1. Supervisor: `POST /api/clientes/{id}/acesso-assistido` → abrir a `url` → tarja "Você está
   acessando a conta de {nome}" e "Voltar ao painel".
2. Fazer um depósito e um saque → `usuarios_id` do supervisor no depósito, no saque e nas
   transações.
3. Gerente e vendedor → `403`. Abrir a mesma `url` de novo → "Acesso expirado".
4. "Voltar ao painel" ou esperar `minutos_acesso_assistido` → acesso encerrado; a sessão do
   cliente no aparelho não muda.

## 9. Indicações (US9)

1. Promoção de Indicação vigente, 5 indicações → menu mostra "Indique um amigo"; "Indicar" abre o
   WhatsApp com `?indicacao=CODIGO`.
2. Cadastrar 6 clientes pelo link → "Minhas indicações" com 6 pendentes (sem telefone/e-mail).
3. Primeiro depósito de 5 deles → bônus ao indicador; 10 válidas → segundo bônus (2 recebidos).
4. Estornar o primeiro depósito de um indicado de um grupo pago → indicação pendente, bônus do
   grupo cancelado (saldo de bônus pode ficar negativo).

## 10. Postman e revisão

- Coleção `docs/postman/wssports_api.postman_collection.json` regenerada com as 21 rotas novas e
  as alteradas.
- Revisão de legibilidade (Princípio V) em cada tarefa.
