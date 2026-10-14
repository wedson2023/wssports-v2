# Quickstart: validação manual do Criar Aposta

Sem testes automatizados (constituição). Os cenários seguem a [spec](spec.md); rotas e formatos em
[contracts/api.md](contracts/api.md).

## Preparação

```bash
php artisan migrate
npm run build   # ou npm run dev
```

- Configurar pela API (`PUT /api/configuracoes/criar-aposta`, token de Admin): desconto `15`,
  mínimo `2`, máximo `10`, liquidação `Recalcular`.
- Ter um jogo do pré-jogo com cotações exibidas conhecidas (ex.: odd1 = 1,80, odd116 = 2,10,
  odd4 = 1,50); usar `porcentagens_confrontos` para fixar as cotações, se precisar.
- No banco, conferir que as colunas novas têm comentário:
  `SHOW FULL COLUMNS FROM apostas_blocos;` e o mesmo para `configuracoes` e
  `usuarios_configuracoes` (coluna `Comment` preenchida).

## Cenários

| # | Passos | Esperado |
|---|---|---|
| 1 | Cliente: no detalhe do jogo, escolher Casa, Mais de 2,5 e Ambas marcam; R$ 10,00; finalizar | Cupom mostra o bloco com 4,81; comprovante com `blocos[0].cotacao` 4,81, prêmio R$ 48,10, "Qtde. jogos" 1 |
| 2 | Desconto geral 0; repetir o 1 | Bloco 5,67 |
| 3 | Escolher Casa e depois Empate do mesmo jogo | Troca (mesmo mercado), não forma bloco |
| 4 | Escolher Casa e depois Dupla chance Casa/Empate | Aviso "Não é possível combinar…"; envio direto pela API → 422 |
| 5 | Máximo 2; tentar um 3º palpite | Aviso do máximo; API → 422 |
| 6 | Bloco + palpite simples de outro jogo (3,00) | Cotação total 14,43 (4,81 × 3,00); conta como 2 jogos |
| 7 | Jogo ao vivo: escolher dois mercados | Troca (não forma bloco); API com dois palpites do mesmo jogo ao vivo → 422 "O Criar Aposta não está disponível para jogos ao vivo." |
| 8 | `criar_aposta_habilitado` = false para um vendedor; vendedor tenta o bloco | Troca no cupom; API → 422 "O Criar Aposta não está habilitado."; outro vendedor consegue |
| 9 | Mudar o desconto entre abrir o cupom e enviar | 409 com o item `bloco: true`; confirmar grava com a cotação nova |
| 10 | Vendedor com comissão 1 jogo = 5% e 3 jogos = 10%: bloco de 3 palpites, R$ 10,00 | Comissão R$ 0,50 (1 jogo), abate `limite_simples`, sem acréscimo |
| 11 | Limite por confronto R$ 15,00: bloco de 3 palpites de R$ 10,00 e depois outra aposta de R$ 10,00 no jogo | A segunda é recusada com "Restam R$ 5,00…" (o bloco contou R$ 10,00, não R$ 30,00) |
| 12 | Liquidação Recalcular: no painel, cancelar o palpite de 1,50 do cenário 1 | Bloco 3,21, prêmio R$ 32,10; histórico registrado |
| 13 | Liquidação Anular bloco (nova aposta): cancelar um palpite do bloco | Todos os palpites do bloco cancelados, bloco Anulado, cotação total 1,00, prêmio R$ 10,00; restaurar volta tudo |
| 14 | Mudar a liquidação depois da aposta e cancelar um palpite | Vale a gravada na aposta |
| 15 | Visitante gera código com bloco; vendedor com desconto 10% simula e valida | Simulação e comprovante com o bloco recalculado com 10% |
| 16 | Aposta mista: bloco do pré-jogo + jogo ao vivo | Em análise; após o delay, Ativa com o bloco e a cotação total corretos |
| 17 | Imprimir o comprovante do cenário 6 (navegador e térmica) | Palpites do bloco + linha "Criar Aposta 4,81"; "Qtde. jogos" 2 |
| 18 | Aposta antiga (sem bloco): consultar pelo código | Comprovante igual ao de antes, `blocos: []`, assinatura válida |
