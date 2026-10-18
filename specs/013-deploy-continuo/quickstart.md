# Quickstart: validação do deploy contínuo

Sem testes automatizados (constituição). Validar pelos passos abaixo. Contratos em
[contracts/pipeline.md](contracts/pipeline.md) e [contracts/aviso-whatsapp.md](contracts/aviso-whatsapp.md).

## Preparação (uma vez)

1. No servidor, como root: rodar `scripts/deploy/server_setup.sh` com a chave pública do pipeline.
2. Ativar o CallMeBot no WhatsApp do responsável e anotar a chave.
3. Cadastrar os 6 segredos do GitHub (research R-11).
4. Conferir: `ssh -i <chave do pipeline> deploy@174.138.49.43 'sudo -n supervisorctl restart wssports-fila:*'`
   funciona e `sudo -n ls /root` é recusado.

## 1. Deploy normal (US1)

- Push na `main` com uma mudança de texto visível → em até 10 min a mudança está em
  `https://ai.wssports.bet`, a execução aparece verde no GitHub e chega o "✅ Deploy concluído".
- Durante o deploy, abrir o site: no máximo alguns segundos de página de manutenção.
- No servidor: `git -C /var/www/wssports rev-parse --short HEAD` igual ao commit do push.

## 2. Push em outra branch (US1)

- Push numa branch qualquer → nenhuma execução do `Deploy`.

## 3. Verificação barra erro (US2)

- Push com erro de sintaxe num `.php` → `verificar` falha, `deploy` nem começa, chega "❌ ... O
  servidor não foi alterado.", commit do servidor não muda. Corrigir com outro push.

## 4. Falha no servidor com volta (US3)

- Push com uma migration que dá erro (ex.: tabela inexistente) → cópia do banco criada em
  `/var/backups/wssports`, passo `migrations` falha, site volta a responder com o commit anterior e
  fora da manutenção, aviso cita a cópia. Corrigir revertendo o commit.

## 5. Disparo manual (US5)

- Em Actions → Deploy → "Run workflow" na `main` → deploy e aviso normais.

## 6. Segurança (US6)

- Procurar no repositório e nos logs da execução por qualquer valor do `.env` (ex.: a senha do
  banco) → nada encontrado.

## 7. Banco novo (US7)

- No servidor, num banco temporário vazio (ou localmente): `php artisan migrate:fresh --seed` termina
  sem erro e o Admin tem todas as permissões padrão.

## 8. Cópias do banco

- Após mais de 5 deploys com migration, `/var/backups/wssports` tem só 5 arquivos.

## 9. Workflows herdados

- Em Actions, só existe o workflow `Deploy`; não há mais execuções diárias de "Tests".
