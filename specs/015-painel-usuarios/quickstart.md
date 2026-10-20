# Quickstart: validação manual — Usuários do painel

Sem testes automatizados. Contratos: [contracts/api.md](contracts/api.md) e
[contracts/paginas.md](contracts/paginas.md).

## Preparação

```bash
php artisan migrate      # inclui a migration que divide as permissões de usuários
npm run build
```

Usuários: `admin`, um supervisor, dois gerentes com vendedores (no servidor de teste: `ovelasques`,
`arruda.thales`, `gerente.teste`, `vendedor.teste01..08`), senha `password`.

## 1. Migração das permissões

1. Antes do `migrate`, anotar quem tinha `usuarios.cadastrar`. Depois: Admin com as 9 novas,
   Supervisor com `*_gerente` e `*_vendedor`, Gerente com `*_vendedor`; nenhuma das 3 antigas existe.
2. `php artisan migrate:rollback --step=1` volta as antigas; `migrate` de novo.

## 2. Menu e alcance (US1)

1. Admin: Usuários > Supervisores, Gerentes, Vendedores; vê todos.
2. Supervisor: Gerentes e Vendedores, só da rede dele.
3. Gerente: só Vendedores, os dele. Vendedor: sem "Usuários"; `/painel/vendedores` volta à Dashboard.
4. Colunas: Comissão 5/7/10 nos vendedores, quantidade de vendedores nos gerentes, de gerentes e
   vendedores nos supervisores; busca por nome e login; paginação; celular em cartões.

## 3. Cadastro e edição (US2)

1. Admin cadastra vendedor escolhendo um gerente; supervisor com um só gerente → vem escolhido.
2. Gerente cadastra vendedor sem campo Gerente; tentar `POST /usuarios` com `funcao=Gerente` → 422.
3. Supervisor sem `usuarios.cadastrar_gerente` → sem "Novo Gerente"; API → 403.
4. Dar `usuarios.cadastrar_gerente` a um gerente pela API → recusado (422); pela migration/seeder ele
   nunca recebe.
5. Login repetido (inclusive de afiliado) → erro no campo; senhas diferentes → "As senhas não conferem."
6. Cadastro com comissões 5/7/10 → aba Comissões mostra 5/7/10 e 10 de 4 a 12, pré-jogo e ao vivo.
7. Editar com senha em branco mantém a senha; mover vendedor para outro gerente da rede.

## 4. Comissões e regras (US3)

1. Alterar a comissão de 2 jogos do vendedor → próxima aposta de 2 palpites usa o novo %.
2. Gerente: aba Regras da equipe, prêmio máximo → aviso "N vendedores alterados"; conferir em todos.
3. Supervisor: aba Regras da rede → confirmação "Isso vai alterar N vendedores".
4. Sem `usuarios_configuracoes.editar`: abas só leitura.
5. Comissão 150 → erro no campo, nada gravado.

## 5. Permissões (US4)

1. Modal de um gerente aberto pelo supervisor: só permissões que o supervisor tem e o gerente pode usar.
2. Desligar `usuarios.excluir_vendedor` → botão excluir some para o gerente; API → 403.
3. "Marcar todas"/"Desmarcar todas" num grupo.

## 6. Situação, exclusão e Lixeira (US5)

1. Desativar gerente com vendedores → todos inativos e sem login; reativar → voltam.
2. Excluir gerente → ele e os vendedores na Lixeira; apostas continuam na Dashboard.
3. Restaurar o gerente → voltam ele e os vendedores excluídos junto.
4. Restaurar só um vendedor cujo gerente está excluído → "Restaure antes o superior."
5. Não existe "apagar para sempre".

## 7. Postman

Coleção com `GET /usuarios/excluidos`, `POST /usuarios/{usuario}/restaurar`,
`GET /usuarios-configuracoes/{usuario}` e as alteradas.
