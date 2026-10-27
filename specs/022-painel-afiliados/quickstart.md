# Quickstart: Painel — Afiliados

Validação manual (sem testes automatizados, constituição). Contratos em
[contracts/api.md](contracts/api.md) e [contracts/paginas.md](contracts/paginas.md).

## Preparação

1. `php artisan migrate` (perfil do afiliado e remoção da configuração de afiliados).
2. `php artisan db:seed --class=AfiliadosSeeder` se a base estiver vazia de afiliados.
3. `npm run dev` e `php artisan serve`.
4. Usuários: um Admin, um Supervisor com dois Gerentes (G1 e G2) e um Vendedor.
5. Afiliados: A1 (Ativo, de G1, com clientes que depositaram neste mês), A2 (Ativo, de G2), A3
   (antigo, sem gerente).

## Cenários

### 1. Consulta e rede (US1)

- Admin abre `/painel/afiliados`: cartões, aba Ativos com período "Este mês", A3 com "Sem gerente
  responsável" em destaque e o filtro "Sem gerente".
- Conferir as colunas de A1 com a gaveta: "A receber" = soma do extrato Em aberto; "Entradas" =
  soma das bases em aberto.
- Entrar como G1: só A1, sem filtro de gerente, sem aba Em análise nem Lixeira, cartões com os
  números só de A1. Supervisor: A1 e A2, sem A3. Vendedor: sem o item no menu.
- `GET /api/afiliados/{A2}` com o token de G1 → 404 "Afiliado não encontrado.".

### 2. Cadastro e edição (US2)

- Como G1, cadastrar com Qualquer depósito a 10%, Instagram, link e 5.000 seguidores: nasce Ativo,
  com G1 responsável e a regra escolhida (sem precisar de `afiliados.editar_comissao`).
- Tentar cadastrar sem tipo de ganho, sem link ou com link `instagram.com/x` → mensagens no campo.
- Editar: trocar telefone e a senha; entrar como o afiliado com a senha nova; a sessão antiga cai.
- Trocar a situação para Inativo no modal: o afiliado não entra; os clientes continuam ligados.
- Sem `clientes.ver_dados_completos`: CPF, e-mail, telefone e PIX mascarados no modal.
- Conferir que nenhum modal tem interruptor e que os campos estão em grade, por seção.

### 3. Aprovação (US3)

- Fazer um cadastro público em `/cadastro-afiliado` com TikTok, link, 12.000 seguidores e o texto:
  aparece em Em análise para o Admin, com "Ganho: A definir".
- Supervisor: não vê a aba nem o cartão; `POST /api/afiliados/{id}/aprovar` → 404.
- Admin aprova sem gerente ou sem regra → recusado no campo; com G1 e Saldo líquido 20% → Ativo, e-mail
  de aprovação, aparece em Ativos.
- Outro cadastro público: recusar com motivo; aparece em Recusados; aprovar depois.

### 4. Gaveta e regra (US4)

- Abrir A1: abas Dados (rede, link, seguidores, sobre, cadastrado por), Extrato (filtrar Em
  aberto; soma), Clientes ("Ver cliente"), Prestações, Regras.
- Trocar a regra para Saldo líquido 15%: a antiga aparece encerrada agora; um depósito novo usa 15%.
- Usuário sem `afiliados.editar_comissao`: sem o botão "Trocar regra".

### 5. Prestação de contas (US5)

- Com A1 a receber R$ 85,00: modal mostra lançamentos, apurado, a pagar e PIX; senha errada →
  "Senha incorreta." e nada muda; senha certa → a receber R$ 0,00, última prestação agora,
  lançamentos como prestados.
- Afiliado com saldo negativo: a pagar R$ 0,00 com o aviso; depois da prestação, a receber zero.

### 6. Link, exclusão e restauração (US6)

- Copiar link de A1 e abrir numa janela anônima: o cadastro de cliente traz o código.
- Copiar link de um Inativo: aviso de que o link só liga clientes quando Ativo.
- Excluir A2 (com valor a receber) como Supervisor: aviso "Este afiliado tem R$ X a receber.";
  senha errada → nada muda; senha certa → vai para a Lixeira com o valor a receber visível.
- `DELETE /api/afiliados/{id}` sem `password` → 422.
- Restaurar A2: volta Ativo. Excluir outro, cadastrar um novo com o mesmo e-mail, restaurar o
  primeiro → pede o e-mail novo; com um e-mail livre, restaura.
- Gerente: sem Excluir e sem Lixeira; não existe "Excluir para sempre".

### 7. Cadastro público e "Meus dados" (US7)

- `/cadastro-afiliado`: sem rede, link ou seguidores → recusado; "Sobre você" para no limite de
  1.000.
- Entrar como afiliado aprovado: em "Meus dados", alterar seguidores para 15.000 e o link; conferir
  na gaveta.
- Afiliado antigo (sem os campos): "Não informado" na gaveta; editar sem preencher salva; depois de
  preenchido, apagar o link é recusado.

### 8. Configurações

- `/painel/configuracoes`, aba Operação: não existe mais a seção "Afiliados".
- `GET /api/configuracoes/afiliados` → 404.

### 9. Celular e desempenho

- 360 px: lista em cartões, gaveta e modais em tela cheia, uma coluna de campos, sem rolagem
  horizontal, alvos com 40 px.
- Com `AfiliadosVolumeSeeder`, abrir a tela e trocar de aba/filtro: primeira página em até 2 s.

### 10. Postman

- A coleção tem `GET /afiliados/resumo`, o `DELETE /afiliados/{afiliado}` com `password` e não tem
  mais `/configuracoes/afiliados`.
