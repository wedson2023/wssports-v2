# Data Model: Painel — Usuários

**Feature**: [spec.md](spec.md) | **Research**: [research.md](research.md)

Nenhuma tabela ou coluna nova. Mudam as **permissões** (R-02) e a regra de cadastro (R-03); o resto
usa `usuarios`, `usuarios_configuracoes` e as tabelas do `spatie/laravel-permission`.

## 1. Permissões de usuários (`Funcao::PERMISSOES_GESTAO`)

| Antes | Depois |
|---|---|
| `usuarios.listar` | `usuarios.listar` |
| `usuarios.consultar` | `usuarios.consultar` |
| `usuarios.cadastrar` | `usuarios.cadastrar_supervisor`, `usuarios.cadastrar_gerente`, `usuarios.cadastrar_vendedor` |
| `usuarios.editar` | `usuarios.editar_supervisor`, `usuarios.editar_gerente`, `usuarios.editar_vendedor` |
| `usuarios.alterar_situacao` | `usuarios.alterar_situacao` |
| `usuarios.excluir` | `usuarios.excluir_supervisor`, `usuarios.excluir_gerente`, `usuarios.excluir_vendedor` |
| `usuarios.gerenciar_permissoes` | `usuarios.gerenciar_permissoes` |

**Uso por função** (`pode_usar`; ✔ = pode usar e recebe no cadastro):

| Permissão | Admin | Supervisor | Gerente | Vendedor |
|---|:-:|:-:|:-:|:-:|
| `*_supervisor` | ✔ | ✘ | ✘ | ✘ |
| `*_gerente` | ✔ | ✔ | ✘ | ✘ |
| `*_vendedor` | ✔ | ✔ | ✔ | ✘ |
| listar, consultar, alterar_situacao, gerenciar_permissoes | ✔ | ✔ | ✔ | ✘ |

A regra é por nível: a função alvo precisa ter `nivel()` maior que a de quem usa.

**Migration de dados** `2026_10_21_000001_dividir_permissoes_usuarios_por_funcao` (R-02): cria as nove,
dá a cada usuário com a antiga as novas equivalentes que a função dele pode usar e apaga as três
antigas; `down` recria as antigas e as devolve a quem tinha alguma das novas.

## 2. Regras de cadastro (R-03)

| Quem cadastra | Funções permitidas | Superior |
|---|---|---|
| Admin | Supervisor | o próprio Admin |
| Admin | Gerente | obrigatório: um Supervisor da rede |
| Admin | Vendedor | obrigatório: um Gerente da rede |
| Supervisor | Gerente | o próprio Supervisor |
| Supervisor | Vendedor | obrigatório: um Gerente dele |
| Gerente | Vendedor | o próprio Gerente |

Comissões opcionais no cadastro do vendedor (R-04): `comissao_1`, `comissao_2`, `comissao_3`
(numeric, 0 a 100, até 2 casas). Gravação: pré-jogo e ao vivo 1/2/3 = valores; faixas 4 a 12 = valor
de 3.

## 3. Restauração (R-05)

```text
Ativo ──excluir (cascata, mesmo deleted_at)──▶ Lixeira ──restaurar (alvo + os de mesmo deleted_at)──▶ Ativo
                                                │
                                                └─ superior ainda na Lixeira → 422 "Restaure antes o superior."
```

## 4. Campos novos na resposta da listagem (`UsuariosResource`)

| Campo | Quando | Valor |
|---|---|---|
| `superior` | sempre que carregado | `{id, nome}` ou null |
| `comissoes` | vendedor | `{"1": "5.00", "2": "7.00", "3": "10.00"}` (pré-jogo) |
| `quantidade_vendedores` | gerente e supervisor | vendedores ativos e não excluídos abaixo |
| `quantidade_gerentes` | supervisor | gerentes ativos e não excluídos abaixo |
| `deleted_at` | Lixeira | quando foi excluído |

## 5. Lugar único dos nomes das permissões (frontend)

`resources/js/utils/permissions.js`: `{ chave: { grupo, nome, descricao } }` para todas as permissões
de `Funcao.php`, e a ordem dos grupos (Usuários, Clientes, Confrontos e cotações, Apostas, Especiais,
Site e configurações, Pagamentos, Cassino, Afiliados, Dashboard). Só textos: **quais** permissões
aparecem vem do servidor (`disponiveis`, contracts/api §9), sem regra de função no frontend.
