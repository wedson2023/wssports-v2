# Quickstart: Painel — Bônus (validação manual)

Sem testes automatizados (constituição): esta é a validação da feature. Contratos em
[contracts/api.md](contracts/api.md) e [contracts/paginas.md](contracts/paginas.md); regras em
[data-model.md](data-model.md).

## 0. Preparação

```bash
php artisan migrate
php artisan queue:work      # o estorno geral roda na fila
php artisan schedule:work   # bonus:expirar
npm run dev
```

Usuários: um Admin; um Supervisor S com o Gerente G1 abaixo; um Gerente G2 de outra rede. Afiliados:
A1 (gerente G1), A2 (gerente G2) e A0 (sem gerente). Clientes: C1 e C2 do A1, C3 do A2, C4 sem afiliado.

## 1. Alcance (FR-002, FR-003, SC-005)

1. Como Admin, cadastre: P-geral (geral), P-A1 (afiliado A1), P-A2 (afiliado A2), P-A0 (afiliado A0).
2. Como G1, abra **Bônus**: aparecem P-A1 (com ações) e P-geral (só Ver detalhe e Ver bônus recebidos);
   P-A2 e P-A0 não aparecem. Como S, o mesmo. Como Admin, as quatro.
3. Como G1, `PATCH /clientes-promocoes/{P-geral}` → 403 "Só o Admin pode alterar promoções gerais.";
   `GET /clientes-promocoes/{P-A2}` → 404 "Promoção não encontrada.".
4. Como G1, cadastre sem afiliado → "Afiliado não encontrado." / obrigatório; com A2 → recusado.

## 2. Promoções e cadastro (US1, US2)

1. Cadastre uma promoção de Primeiro depósito, Percentual 100% até R$ 200,00, rollover 5x, 7 dias,
   afiliado A1: o modal esconde Quantidade de indicações e mostra Depósito máximo e Rollover no
   primeiro depósito.
2. Repita com o mesmo nome → "Já existe uma promoção com este nome.".
3. Cadastre outra Primeiro depósito/Esportes para A1 no mesmo período → mensagem com o nome e o ID da
   primeira. Para A2 ou geral no mesmo período → aceito (FR-015).
4. Informe fim ontem → "A data de fim já passou.". Edite só a descrição de uma promoção já encerrada →
   aceito.
5. Busque por parte do nome; filtre situação Vigente, modalidade, categoria e afiliado "Geral"; confira
   as etiquetas e que recarregar a página mantém o filtro.
6. Faça um primeiro depósito de R$ 100,00 com C1 (aceitando bônus): a promoção mostra Clientes 1,
   Creditado R$ 100,00; editar Valor/Categoria/Modalidade/Tipo aparece travado.
7. Desative: novo depósito de C2 não recebe bônus; C1 continua com o bônus. Ative de novo.

## 3. Bônus recebidos (US3)

1. Aba **Bônus recebidos**: "Em andamento" selecionado, C1 com valor R$ 100,00, rollover exigido
   R$ 500,00, falta R$ 500,00, expira em +7 dias. Cartão Em andamento = 1.
2. C1 aposta R$ 50,00 com o bônus: falta R$ 450,00.
3. Cadastre uma promoção Primeiro cadastro com rollover 0 e cadastre C5: o bônus aparece direto em
   "Cumpridos".
4. Situação "Todos", filtro por promoção e período "Hoje": lista e cartões batem.
5. Detalhe do bônus: regras gravadas e histórico; "Ver cliente" abre o detalhe do cliente sem ações.
6. Como G1, os bônus de C3 e C4 não aparecem; os de C1/C2 (inclusive de P-geral) aparecem.

## 4. Estorno individual (US5, SC-004)

1. Bônus de R$ 50,00 em andamento com saldo de bônus R$ 30,00: "Estornar bônus" mostra "Será retirado:
   R$ 30,00".
2. Senha errada → "Senha incorreta." e nada muda.
3. Motivo e senha corretos → saldo de bônus 0, situação Estornado com autor e motivo, extrato com
   "Estorno"; o rollover não aparece mais na área do cliente; as apostas continuam.
4. Tente de novo (ou via API) → "Este bônus não pode mais ser estornado.". Em um bônus Convertido, a ação
   não aparece.
5. Como G1 → a ação não aparece; `POST /clientes-bonus/{id}/estornar` → 403.

## 5. Estorno geral (US4, SC-004, SC-007)

1. Promoção recebida por C1 (em andamento), C2 (convertido) e um terceiro (já estornado no passo 4).
2. "Estornar promoção": mostra clientes e creditado; senha errada → nada muda.
3. Confirme: a linha mostra "Estornando N de M" e vira "Estornada"; C1 perde o saldo do bônus; C2 não
   perde nada do saldo real; o terceiro não é retirado de novo; os números de uso mostram Retirado.
4. Pare o worker no meio de um estorno com muitos clientes e suba de novo: termina sem retirar duas
   vezes.

## 6. Excluir (US6)

1. Promoção nova sem uso → "Excluir" → some da lista.
2. Promoção aplicada → "Excluir" não aparece; `DELETE` → "Promoção já aplicada não pode ser excluída;
   desative ou estorne.".

## 7. Cancelamentos automáticos e dados antigos (R-05, R-12)

1. Estorne totalmente o depósito que deu bônus a um cliente: o bônus aparece como **Estornado** (tipo
   Automático, motivo "Cancelado pelo estorno do depósito"), não como "Excluído pelo cliente".
2. Depois da migration de dados, bônus de promoções estornadas antes desta spec aparecem como
   Estornados.

## 8. Visual e desempenho (FR-031 a FR-034, SC-008)

- Celular 360 px: cartões, filtros e modais em tela cheia, sem rolagem horizontal.
- Esqueleto ao carregar, vazio com orientação, erro com "Tentar novamente" por área.
- Botões com carregando e sem clique duplo; após cada ação, lista, cartões e detalhe atualizados sem
  perder filtros e página.
- Primeira página de cada aba em até 2 s.
- Coleção do Postman com as rotas `clientes-bonus*` e as alteradas.
