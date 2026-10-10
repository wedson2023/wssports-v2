# Implementation Plan: Ajustes da tela principal

**Branch**: `006-ajustes-tela-principal` | **Date**: 2026-10-10 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `specs/006-ajustes-tela-principal/spec.md`

## Summary

Amplia a tela principal da spec 005 com seis blocos:

- **Link do bilhete** (`/?code=`): só frontend; reaproveita a busca de código que já leva o vendedor
  à validação.
- **Impressão do vendedor**: itens Impressão/Largura/Tabela no menu, impressão Bluetooth por Web
  Bluetooth com ESC/POS no formato do antigo (sem dependência nova), modo APP pelo `app://` do
  antigo e a rota `GET /api/tabela-jogos` sobre a `ListagemConfrontos` existente.
- **Especiais**: tabelas `especiais` e `especiais_opcoes`, CRUD e encerramento pela API do painel,
  listagem pública e na `Home`, palpite especial nas rotas de aposta existentes (colunas novas em
  `apostas_palpites`) e resultado dos palpites/apostas quando decidível sem os jogos (pagamento e
  apuração dos jogos ficam para a spec de apuração).
- **Regras**: página `/regras` em blocos no visual de "Regras de apostas" do antigo: texto da banca
  (coluna `configuracoes.regras`, editado pela API do painel), regras de bônus das promoções
  ativas, regras de cada mercado (textos fixos) e limites de quem vê; quatro promoções padrão
  inativas por seeder.
- **Avisos** (antigo popup): tabelas `avisos` e `avisos_leituras`, CRUD com upload, aviso padrão,
  entrega e leitura pela API pública por aparelho/cliente e modal redesenhado (`NoticeModal`).
- **Banners e logo**: tabela `banners` e coluna `configuracoes.logo`, gerenciadas pela API do
  painel; carrossel e logo deixam de ser fake; imagens salvas com o hash do conteúdo no nome para
  não ficarem presas no cache.

Decisões em [research.md](research.md) (R-01 a R-22).

## Technical Context

**Language/Version**: PHP 8.2 (^8.2) no backend; JavaScript (ES2022) com JSX no frontend

**Primary Dependencies**: as já instaladas (Laravel 12, Inertia 2, React 19, styled-components 6,
sweetalert2, `jwt-auth`, `spatie/laravel-permission`). **Nenhum pacote novo** (R-06, R-20): Web
Bluetooth e ESC/POS com APIs do navegador; redimensionamento de banner com a extensão GD do PHP, já
instalada, que passa a ser declarada no `composer.json` (`ext-gd`).

**Storage**: MySQL: 5 tabelas novas (`especiais`, `especiais_opcoes`, `avisos`,
`avisos_leituras`, `banners`) e alteração em `apostas_palpites` e `configuracoes`; disco `public`
para as imagens de avisos, banners e logo (`storage:link`), com nome pelo hash do conteúdo. No
aparelho: `localStorage` `wssports.impressao` e `wssports.aparelho`.

**Testing**: nenhum teste automatizado (Constituição); validação manual pelo
[quickstart.md](quickstart.md)

**Target Platform**: navegadores atuais no desktop e no celular; impressão Bluetooth no Chrome
(Android e desktop); servidor PHP 8.2 com GD

**Project Type**: aplicação web Laravel (monólito com Inertia) + API JWT existente

**Performance Goals**:

| Meta | Alvo |
|---|---|
| Bilhete impresso por Bluetooth após tocar em "Imprimir" (impressora já escolhida, SC-002) | ≤ 10 s |
| Aviso não atrasa a lista (FR-032) | pedido só depois da lista; modal só com a imagem carregada |
| Tabela do dia (até ~500 jogos) | ≤ 5 páginas de 100 |
| Logo ou banner novo visível sem limpar o cache (SC-008) | 100% das aberturas seguintes (URL nova a cada imagem nova) |

**Constraints**:

- dinheiro e cotações em `decimal` + `bcmath` no backend e centavos inteiros no frontend;
- paginação ≤ 100 em todas as listagens novas (inclusive a tabela, buscada página a página);
- cotação do especial fixa (sem porcentagens nem teto);
- nenhum crédito de saldo ao encerrar especiais;
- texto das regras da banca só como texto (sem HTML interpretado);
- visual: lista de especiais igual ao `components/specials` do antigo; blocos de regras no visual de
  "Regras de apostas" do antigo, com as cores pelos tokens do tema; aviso redesenhado (aprovado);
- nomes conforme a Constituição 3.2.0 (tabelas e colunas em português; componentes em inglês
  PascalCase; funções e variáveis em snake_case português; hooks `useX`), com nomes intuitivos
  (aviso no lugar de popup).

**Scale/Scope**:

| Item | Quantidade |
|---|---|
| Tabelas novas / alteradas | 5 / 2 |
| Migrations | 7 |
| Rotas da API novas | 27 (3 públicas, 24 do painel) |
| Controllers novos | 10 |
| Componentes novos | 9 (`SpecialList`, `SpecialCard`, `NoticeModal`, `TableModal`, `RulesBlock`, `RuleItem`, `BonusRules`, `MarketRules`, `BetLimits`) |
| Hooks novos | 1 (`useImpressao`) |
| Utilitários novos | 3 (`utils/bluetooth.js`, `utils/thermal.js`, `utils/market_rules.js`) |
| Serviços novos | 5 (`ListagemEspeciais`, `EncerramentoEspeciais`, `TabelaJogos`, `EscolhaAvisos`, `ArmazenamentoImagens`) |
| Seeders novos | 4 (`EspeciaisSeeder`, `AvisosSeeder`, `BannersSeeder`, `ClientesPromocoesSeeder`) |

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Princípio / Regra | Verificação | Status |
|---|---|---|
| I. Nomes no backend | Tabelas e colunas em português (`especiais`, `especiais_opcoes`, `avisos`, `avisos_leituras`, `banners`, `data_limite`, `aparelho`, `ordem`, `logo`, `regras`); prefixo da tabela principal; classes em português PascalCase (`EncerramentoEspeciais`, `ArmazenamentoImagens`, `ConfiguracoesRegrasController`); enum `SituacaoEspecial` com casos em PascalCase e valores em português; permissões `<recurso>.<acao>` (`especiais.encerrar`, `avisos.gerenciar`, `banners.gerenciar`, `configuracoes.editar`); rotas em kebab-case (`tabela-jogos`) | ✅ Pass |
| I. Nomes no frontend | Componentes em inglês PascalCase (`SpecialList`, `NoticeModal`, `RulesBlock`, `RuleItem`); funções e variáveis em snake_case português (`imprimir_bilhete`, `aviso_banca`); hook `useImpressao`; utilitários `utils/bluetooth.js`, `utils/thermal.js`, `utils/market_rules.js`; props de estilo com `$` | ✅ Pass |
| I. Siglas | `ip`, `link` como estão | ✅ Pass |
| II. Idioma | Artefatos e comentários em português | ✅ Pass |
| III. Componentes | Cada componente novo em pasta própria com `index.jsx` e `styles.jsx` | ✅ Pass |
| IV. Escopo estrito | Arquivos existentes alterados listados abaixo, confirmados pelo responsável em 2026-10-10 | ✅ Pass |
| V. Legibilidade | Revisão ao final de cada tarefa | ✅ Pass |
| VI. Consistência | Especiais, avisos e banners no padrão de `clientes_promocoes`/`campeonatos` (apiResource, Request, Resource, permissões no `Funcao.php`, seeder por recurso idempotente); upload num único serviço (`ArmazenamentoImagens`) para os três; aposta especial pelos serviços da spec 004, sem caminho paralelo; tabela sobre a `ListagemConfrontos`; logo e regras na `configuracoes` existente | ✅ Pass |
| VII. Sistema antigo | Arquivos e funcionamento no R-01/R-02; diferenças no R-03, aprovadas em Clarifications (aviso por aparelho, nome aviso, promoções inativas, regras em blocos com texto único, banners/logo com hash, apuração parcial) ou melhorias registradas | ✅ Pass |
| VIII. Fidelidade visual | Itens do menu do vendedor, lista de especiais e cabeçalhos das regras iguais ao antigo; aviso redesenhado e regras com cores do tema, com aprovação (Clarifications); impressões no formato do antigo | ✅ Pass |
| IX. Dados fake | Saem os fakes de banners, logo e texto das regras (R-16); continuam os de cores, contatos, indicadores e ícones | ✅ Pass |
| Stack: dependências | Nenhum pacote novo; `ext-gd` declarada (já instalada) (R-20) | ✅ Pass |
| Timestamps e soft delete | Todas as tabelas novas com `timestamps()` + `softDeletes()`; exclusões lógicas | ✅ Pass |
| Referências a tabelas inexistentes | Nenhuma: todas as FKs apontam para tabelas existentes ou criadas nesta spec | ✅ Pass |
| Paginação ≤ 100 | Especiais (50/100), avisos (≤ 100), banners (≤ 100), tabela (100, buscada por páginas) | ✅ Pass |
| Sem testes | Nenhum arquivo, tarefa ou dependência de teste | ✅ Pass |
| Postman | Coleção regenerada na mesma entrega (R-17) | ✅ Pass |

**Resultado do gate**: aprovado; lista de arquivos existentes confirmada pelo responsável em 2026-10-10
(Princípio IV) abaixo. Reavaliado depois da Fase 1 (banners, logo, avisos e regras): sem violação
nova.

### Arquivos existentes que serão alterados (Princípio IV)

| Arquivo | Alteração | Motivo |
|---|---|---|
| `app/Enums/Funcao.php` | Constantes `PERMISSOES_ESPECIAIS` e `PERMISSOES_SITE`; `permissoes_padrao` e `pode_usar` (só Admin e Supervisor) | R-09, R-21 |
| `database/seeders/PapeisPermissoesSeeder.php` | Cria as permissões novas (inclui `configuracoes.editar`) | R-09, R-21 |
| `database/seeders/DatabaseSeeder.php` | Chama `EspeciaisSeeder`, `AvisosSeeder`, `BannersSeeder`, `ClientesPromocoesSeeder` | R-13, R-15, R-18 |
| `app/Models/Configuracoes.php` | `fillable` `logo` e `regras`; constante `REGRAS_PADRAO`; `$attributes` com o texto padrão; `url_logo()` | R-19, R-22 |
| `app/Fakes/DadosFake.php` | Remove `banners()`, `regras()` e a chave `logo` de `tema()` | R-16 |
| `app/Http/Middleware/TratarRequisicoesInertia.php` | `tema.logo` pela `Configuracoes::url_logo()` | R-19 |
| `public/fakes/logo.png`, `public/fakes/banners/1.jpg` | Saem de `public/fakes` (viram `public/images/logo_padrao.png` e `database/seeders/files/banner_padrao.jpg`) | R-16 |
| `composer.json` (e `composer.lock`, pelo `composer update --lock`) | `"ext-gd": "*"` em `require` | R-20 |
| `app/Models/ApostasPalpites.php` | `fillable`, `casts` (`resultado`) e relações `especial`, `opcao_especial` | R-10 |
| `app/Support/CodigosCotacao.php` | Constante `ESPECIAL` e método `e_aposta` | R-10 |
| `app/Http/Requests/ApostasRequest.php` | Campo `palpites.*.especiais_opcoes_id` e regras/mensagens | R-10 |
| `app/Services/CriacaoApostas.php` | `montar` com especiais; `garantir_limite_por_confronto` ignora especiais; `gravar_palpites` com as colunas novas | R-10 |
| `app/Services/RegrasAposta.php` | Disponibilidade do especial e uma opção por categoria | R-10 |
| `app/Services/ConferenciaCotacoes.php` | Especial indisponível → motivo | R-10 |
| `app/Services/ValidacaoCodigos.php` | Simulação com especiais | R-10 |
| `app/Services/EdicaoApostas.php` | Método público de cancelamento pelo sistema (permite o último palpite) reaproveitando `marcar`/`recalcular` | R-11 |
| `app/Services/ListagemConfrontos.php` | Filtro `campeonatos` (lista) e códigos de cotação escolhidos no pré-jogo | R-08 |
| `app/Http/Resources/ComprovanteApostaResource.php` | Palpite especial e `resultado` | R-10 |
| `app/Http/Resources/SimulacaoApostaResource.php` | Palpite especial | R-10 |
| `app/Http/Controllers/PaginaInicialController.php` | `esporte=ESPECIAL` → `ListagemEspeciais`; `banners` pela tabela | R-12, R-18 |
| `app/Http/Controllers/RegrasController.php` | `regras` da configuração; props `regras_bonus` e `limites_aposta` | R-14, R-22 |
| `routes/api.php` | 27 rotas novas | contracts/api.md |
| `docs/postman/wssports_api.postman_collection.json` | Regenerada | Constituição, R-17 |
| `resources/js/pages/Home/index.jsx` | `?code=`, especiais, aviso (`aviso_banca`), tabela, impressão | R-04, R-12, R-13 |
| `resources/js/pages/Rules/index.jsx` | Repassa `regras_bonus` e `limites_aposta` | R-14 |
| `resources/js/components/RulesContent/index.jsx` e `styles.jsx` | Troca o título e o cartão numerado pelos blocos (banca, bônus, apostas, limites) | R-14 |
| `resources/js/components/SideMenu/index.jsx` | Itens do vendedor (Impressão, Largura, Tabela) | R-05 |
| `resources/js/components/SuccessModal/index.jsx` | "Imprimir" pelo `useImpressao` (navegador, Bluetooth ou APP) | R-05, R-06, R-07 |
| `resources/js/hooks/useCupom.js` | Palpite `especial` (validação e envio) | R-12 |
| `resources/js/hooks/useSessao.js` | `limites_aposta` em `props_do_publico` | R-14 |
| `resources/js/utils/storage.js` | Chaves `wssports.impressao` e `wssports.aparelho` | R-05, R-13 |
| `resources/js/utils/print.js` | Tabela no navegador (HTML do antigo) e palpite especial no comprovante | R-08, R-10 |
| `specs/004-apostas/contracts/api.md` | Nota do palpite especial nas rotas de aposta | contracts/api.md seção 6 |

`Header`, `Footer`, `AuthModal` e `BannerCarousel` não mudam: já leem `tema.logo` e `banners`. Nenhum
outro arquivo das specs 001 a 005 muda.

## Project Structure

### Documentation (this feature)

```text
specs/006-ajustes-tela-principal/
├── spec.md
├── plan.md              # este arquivo
├── research.md          # R-01 a R-22
├── data-model.md        # tabelas, enums, permissões e estado no aparelho
├── quickstart.md        # validação manual
├── contracts/
│   ├── api.md           # rotas novas e palpite especial
│   └── paginas.md       # mudanças nas páginas Home e Rules e nas props compartilhadas
├── checklists/
│   └── requirements.md
└── tasks.md             # /speckit-tasks
```

### Source Code (repository root)

```text
app/
├── Enums/
│   └── SituacaoEspecial.php
├── Models/
│   ├── Especiais.php
│   ├── EspeciaisOpcoes.php
│   ├── Avisos.php
│   ├── AvisosLeituras.php
│   └── Banners.php
├── Services/
│   ├── ListagemEspeciais.php           # lista pública e da Home
│   ├── EncerramentoEspeciais.php       # encerrar/cancelar + resultado das apostas
│   ├── TabelaJogos.php                 # tabela do vendedor sobre a ListagemConfrontos
│   ├── EscolhaAvisos.php               # aviso elegível e leitura
│   └── ArmazenamentoImagens.php        # upload com hash no nome, redimensionamento e remoção
└── Http/
    ├── Controllers/
    │   ├── EspeciaisController.php
    │   ├── EspeciaisOpcoesController.php
    │   ├── EncerramentoEspeciaisController.php
    │   ├── PublicoEspeciaisController.php
    │   ├── AvisosController.php
    │   ├── PublicoAvisosController.php
    │   ├── BannersController.php
    │   ├── ConfiguracoesRegrasController.php
    │   ├── ConfiguracoesLogoController.php
    │   └── TabelaJogosController.php
    ├── Requests/
    │   ├── EspeciaisRequest.php
    │   ├── EspeciaisOpcoesRequest.php
    │   ├── EncerrarEspecialRequest.php
    │   ├── ListagemEspeciaisRequest.php
    │   ├── AvisosRequest.php
    │   ├── LeituraAvisoRequest.php
    │   ├── BannersRequest.php
    │   ├── ConfiguracoesRegrasRequest.php
    │   ├── ConfiguracoesLogoRequest.php
    │   └── TabelaJogosRequest.php
    └── Resources/
        ├── EspeciaisResource.php
        ├── EspeciaisOpcoesResource.php
        ├── AvisosResource.php
        └── BannersResource.php

database/
├── migrations/
│   ├── xxxx_create_especiais_table.php
│   ├── xxxx_create_especiais_opcoes_table.php
│   ├── xxxx_add_especiais_apostas_palpites_table.php
│   ├── xxxx_create_avisos_table.php
│   ├── xxxx_create_avisos_leituras_table.php
│   ├── xxxx_create_banners_table.php
│   └── xxxx_add_logo_regras_configuracoes_table.php   # preenche o texto padrão das regras
└── seeders/
    ├── EspeciaisSeeder.php             # permissões de especiais
    ├── AvisosSeeder.php                # permissão de avisos + aviso padrão
    ├── BannersSeeder.php               # permissão de banners + banner padrão
    ├── ClientesPromocoesSeeder.php     # 4 promoções padrão inativas
    └── files/
        ├── aviso_padrao.png
        └── banner_padrao.jpg           # antigo public/fakes/banners/1.jpg

public/images/
└── logo_padrao.png                     # antigo public/fakes/logo.png (logo sem envio)

resources/js/
├── components/
│   ├── SpecialList/                    # lista de categorias especiais
│   ├── SpecialCard/                    # categoria com opções
│   ├── NoticeModal/                    # aviso Fechar/Lido
│   ├── TableModal/                     # escolha de campeonatos da tabela
│   ├── RulesBlock/                     # bloco da página de regras (título opcional)
│   ├── RuleItem/                       # cabeçalho com ícone e bordas na cor do tema + conteúdo
│   ├── BonusRules/                     # regras de bônus
│   ├── MarketRules/                    # regras de apostas por mercado
│   └── BetLimits/                      # limites de aposta
├── hooks/
│   └── useImpressao.js                 # preferências e impressão (navegador, Bluetooth, APP)
└── utils/
    ├── bluetooth.js                    # conexão e escrita Web Bluetooth
    ├── thermal.js                      # texto ESC/POS do bilhete e da tabela
    └── market_rules.js                 # textos fixos das regras de cada mercado
```

**Structure Decision**: monólito Laravel com Inertia (spec 005). Backend no padrão de recursos das
specs 002 a 004; frontend nas pastas da spec 005.

## Complexity Tracking

| Violação | Por que é necessária | Alternativa mais simples rejeitada porque |
|---|---|---|
| `apostas_palpites.confrontos_id` e `campeonatos_id` passam a aceitar nulo | O palpite especial não tem confronto nem campeonato | Tabela separada de palpites especiais duplicaria cotação total, prêmio, cancelamento, histórico, comprovante e validação da spec 004 |
| Cancelamento do último palpite ativo pelo sistema | Categoria cancelada com aposta só de especial deve devolver o valor (cotação 1,00), como no antigo | Manter a recusa do `EdicaoApostas` deixaria a aposta presa com um palpite de categoria cancelada |
| Texto padrão das regras no código (`REGRAS_PADRAO`), não no padrão da coluna | Nem toda versão do MySQL aceita padrão em coluna `text` | Seeder que preenche quando vazio sobrescreveria o texto que o administrador apagou de propósito |
