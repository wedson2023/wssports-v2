# Quickstart: validação manual dos afiliados

Sem testes automatizados (constituição). Validar pelos passos abaixo, usando a coleção do Postman
(`docs/postman/wssports_api.postman_collection.json`) e o navegador. Rotas e formatos em
[contracts/api.md](contracts/api.md) e [contracts/paginas.md](contracts/paginas.md); tabelas em
[data-model.md](data-model.md).

## Preparação

1. `php artisan migrate` e `php artisan db:seed --class=AfiliadosSeeder` (permissões e 2 afiliados
   de exemplo).
2. `.env` com `MAIL_MAILER=log` (os e-mails aparecem em `storage/logs/laravel.log`) e a fila
   rodando: `php artisan queue:work`.
3. `npm run dev` e `php artisan serve`.
4. Tokens no Postman: Admin, Supervisor, um Gerente e um Vendedor (`POST /auth/login`).

## 1. Cadastro pelo painel (US1)

- Admin: `POST /afiliados` com `tipo_ganho` "Qualquer depósito" e `percentual` 10 → 201, `Ativo`,
  `codigo` de 8 caracteres.
- Gerente sem `afiliados.editar_comissao`: cadastra com 50% → regra = padrão da configuração;
  responsável = o próprio Gerente mesmo enviando outro `usuarios_id`.
- Login igual a um usuário do painel, CPF inválido, CPF repetido → 422 no campo.
- Vendedor → 403. `POST /usuarios` com o login de um afiliado → 422.

## 2. Cadastro público e aprovação (US2)

- Rodapé → "Afiliados" abre `/cadastro-afiliado` com o visual do antigo.
- "Quero me afiliar": enviar com senhas diferentes → erro no campo; corrigir e enviar → alerta de
  análise; log com o e-mail "recebido".
- Via Postman, enviar o cadastro com `situacao: "Ativo"` → fica `Em análise`.
- Gerente: `POST /afiliados/{id}/aprovar` → 403. Supervisor: aprovar → `Ativo` e e-mail de
  aprovação; outro cadastro: recusar com motivo → `Recusado` e e-mail de recusa.

## 3. Login e recuperação (US3)

- No `AuthModal` do site, entrar com o afiliado aprovado → vai para `/afiliados`.
- Afiliado em análise, recusado e inativo → mensagem própria, sem entrar.
- 6 senhas erradas seguidas → 429.
- Token de afiliado em `GET /area-cliente/meus-dados` e em `GET /afiliados` → 401/403; token de
  cliente em `GET /area-afiliado/painel` → 401.
- "Esqueci minha senha" com o e-mail → código no log; redefinir → entra com a senha nova e a sessão
  antiga deixa de valer. E-mail inexistente → mesma resposta, nada no log.

## 4. Indicação (US4)

- Abrir `/?user=<codigo em minúsculas>` e cadastrar um cliente → `GET /clientes/{id}` mostra o
  `afiliado`.
- Código inexistente → 422 "Código de afiliado não encontrado.".
- Inativar o afiliado, cadastrar outro cliente com o código → cliente sem afiliado.
- Abrir `/?user=maria.souza` (afiliado do seeder com código `MARIA.SOUZA`, como um login antigo) e
  cadastrar → cliente ligado a ele (SC-006).
- Promoção de "Qualquer depósito" com `afiliados_id` do afiliado e outra geral → o cliente dele
  recebe a do afiliado; um cliente sem afiliado recebe a geral.

## 5. Comissão (US5)

Com o gateway de testes da spec 007 (ou aprovando o depósito pelo fluxo de conferência):

| Afiliado | Ação do cliente | Lançamento esperado |
|---|---|---|
| Qualquer depósito 10% | depósito de 100,00 aprovado | +10,00 |
| Primeiro depósito 20% | 1º depósito 50,00; 2º depósito 80,00 | +10,00 só no 1º |
| Saldo líquido 30% | depósito 200,00; saque 50,00 pago | +60,00 e −15,00 |
| Qualquer depósito 10% | estorno parcial de 40,00 do depósito de 100,00 | −4,00 |
| Qualquer depósito 10% | saque pago | nenhum |

- Repetir os casos da tabela com clientes diferentes até somar 20 movimentos ou mais e conferir
  centavo a centavo (SC-003).
- Reprocessar a mesma notificação do depósito → nenhum lançamento a mais.
- Trocar a regra para 15% (`PUT /afiliados/{id}/comissao`) → depósitos seguintes com 15%, anteriores
  com 10%.
- Afiliado inativo → lançamentos continuam; afiliado na lixeira → nenhum lançamento novo.

## 6. Prestação de contas (US6)

- `POST /afiliados/{id}/prestacoes` com a senha certa → `valor_apurado` = soma, `valor_pago` igual,
  `valor_a_receber` zera; extrato mostra os lançamentos como prestados.
- Saldo líquido negativo (−40,00) → apurado −40,00, pago 0,00; próximo período começa em 0.
- Sem lançamentos → prestação zerada no histórico.
- Senha errada → 422; Gerente de outro afiliado → 404.
- Duas chamadas ao mesmo tempo (duas abas do Postman / runner) → uma com os lançamentos e outra
  zerada; o total pago não duplica.

## 7. Painel do afiliado (US7)

- `/afiliados`: "Olá, nome!", Meu Saldo, última prestação, texto da comissão e extrato no visual do
  antigo.
- Filtro de período e Em aberto/Prestados; abas Clientes (nomes como "João S. P.") e Prestações.
- Copiar meu link → link com o código.
- Meus dados: alterar telefone e Pix; nome/CPF só leitura. Alterar senha com a atual errada → erro
  no campo; certa → continua logado com token novo.
- Abrir `/afiliados` sem sessão → vai para `/?entrar=1`.
- `php artisan db:seed --class=AfiliadosVolumeSeeder`, entrar com o afiliado do seeder e medir a
  abertura do painel na aba Rede do navegador → até 2 s (SC-007).

## 8. Gestão pela API (US8)

- `GET /afiliados?situacao=Em análise`, `busca`, `de`/`ate`, `por_pagina=150` (→ 422, máximo 100).
- Gerente lista só os seus; `GET /afiliados/{id}` de outro gerente → 404.
- Sem `clientes.ver_dados_completos`: CPF, telefone, e-mail e Pix mascarados.
- Excluir (Admin) → lixeira, sufixo nos dados únicos, tokens invalidados; Gerente → 403.
- Restaurar com o login já usado por outro → 422 no campo `login`.
- `GET/PUT /configuracoes/afiliados` muda a regra do próximo cadastro público.

## 9. E-mail do cadastro de clientes (FR-013a)

- Cadastrar um cliente com e-mail pelo `AuthModal` → no log, o e-mail "[ CADASTRO ] Cadastro
  realizado com sucesso!" com o nome dele; o cliente entra normalmente.
- Cadastrar um cliente sem e-mail → nada enviado.
- Parar a fila (`queue:work`) e cadastrar → o cadastro conclui; o e-mail sai quando a fila voltar.

## 10. Postman

A coleção tem todas as rotas de `contracts/api.md` §1 a §3 e as alteradas do §4.
