# Tecnofit Challenge — Plataforma de Conta Digital com Saque PIX

## Como rodar

```bash
# 1. Copie as variáveis de ambiente
cp .env.example .env

# 2. Suba todos os serviços (build + start)
docker compose up --build -d

# 3. Execute as migrations
docker compose exec app php bin/hyperf.php migrate

# 4. (Opcional) Popule com contas de teste
docker compose exec app php bin/hyperf.php db:seed

# 5. Acesse a API
curl http://localhost:9501/

# 6. Acesse o Mailhog (e-mails capturados)
open http://localhost:8025
```

## Endpoints

### `POST /account/{accountId}/balance/withdraw`

Realiza ou agenda um saque via PIX.

**Request body:**
```json
{
  "method": "PIX",
  "pix": {
    "type": "email",
    "key": "fulano@email.com"
  },
  "amount": 150.75,
  "schedule": null
}
```

| Campo | Tipo | Obrigatório | Descrição |
|-------|------|-------------|-----------|
| `method` | string | sim | Apenas `PIX` suportado atualmente |
| `pix.type` | string | sim (se method=PIX) | Tipo da chave: `email`, `cpf`, `cnpj`, `phone`, `random` |
| `pix.key` | string | sim (se method=PIX) | Valor da chave PIX |
| `amount` | decimal | sim | Valor a sacar (> 0) |
| `schedule` | string\|null | não | Data/hora futura no formato `Y-m-d H:i`. `null` = imediato |

**Respostas:**

| HTTP | Situação |
|------|----------|
| `201 Created` | Saque registrado (imediato ou agendado) |
| `404 Not Found` | Conta não encontrada |
| `422 Unprocessable Entity` | Saldo insuficiente, data no passado, validação falhou |

**Exemplo — saque imediato:**
```bash
curl -X POST http://localhost:9501/account/550e8400-e29b-41d4-a716-446655440000/balance/withdraw \
  -H "Content-Type: application/json" \
  -d '{"method":"PIX","pix":{"type":"email","key":"fulano@email.com"},"amount":100.00,"schedule":null}'
```

**Exemplo — saque agendado:**
```bash
curl -X POST http://localhost:9501/account/550e8400-e29b-41d4-a716-446655440000/balance/withdraw \
  -H "Content-Type: application/json" \
  -d '{"method":"PIX","pix":{"type":"email","key":"fulano@email.com"},"amount":50.00,"schedule":"2026-12-01 10:00"}'
```

## Como rodar os testes

```bash
# Todos os testes
docker compose exec app composer test

# Somente unitários
docker compose exec app composer test-unit

# Somente feature
docker compose exec app composer test-feature
```

---

## Decisões arquiteturais

### 1. Strategy + Factory para métodos e tipos de chave PIX

O campo `method` do request (hoje só `PIX`) é resolvido por `WithdrawMethodFactory` para uma implementação de `WithdrawMethodInterface`. Dentro do PIX, o campo `pix.type` é delegado a um registry de validadores (`PixKeyValidatorRegistry`) que segue o mesmo padrão — cada tipo de chave tem seu próprio `PixKeyValidatorInterface`.

**Por quê:** a especificação diz explicitamente que a arquitetura deve ser extensível para novos métodos e tipos de chave. Com Factory + Strategy, adicionar, por exemplo, TED não exige tocar em `WithdrawService`; basta criar `TedWithdrawStrategy` e registrar na factory.

### 2. Transação com `SELECT ... FOR UPDATE` (lock pessimista)

O débito de saldo acontece dentro de `DB::transaction()`, onde a conta é carregada com `Account::lockForUpdate()->find($id)` antes de qualquer leitura de saldo.

**Por quê:** sem lock, dois processos concorrentes podem ler o mesmo saldo (ex: R$100), ambos verificarem que `100 >= 100`, ambos debitarem, resultando em saldo −R$100. O lock pessimista serializa o acesso à linha; o segundo processo só lê o saldo já debitado pelo primeiro e recebe `InsufficientFundsException`.

**Trade-off:** maior contenção em alta concorrência no mesmo `account_id`. Para escala extrema, lock otimista com `version`/`updated_at` seria mais performático, porém exigiria lógica de retry na aplicação.

### 3. Crontab com `FOR UPDATE SKIP LOCKED` (lock distribuído via MySQL 8)

O job de processamento agendado consulta registros pendentes com:
```sql
SELECT id FROM account_withdraw
WHERE scheduled = 1 AND done = 0 AND scheduled_for <= NOW()
ORDER BY scheduled_for ASC
LIMIT 50
FOR UPDATE SKIP LOCKED
```

`SKIP LOCKED` faz com que cada instância da aplicação (réplica horizontal) obtenha um conjunto disjunto de registros. Nenhum withdrawal é processado duas vezes.

**Por quê:** o Hyperf Crontab com `onOneServer: true` + `singleton: true` já oferece alguma proteção, mas `SKIP LOCKED` garante idempotência no nível do banco, independente do mecanismo de lock de processo.

**Trade-off:** requer MySQL 8+ (disponível na stack definida). Em Postgres seria igualmente suportado.

### 4. `decimal(15,2)` + `bcmath` — sem float

O saldo e os valores de saque são armazenados como `DECIMAL(15,2)` no MySQL e manipulados como strings PHP com `bccomp`/`bcsub`. Nunca passam por `float`.

**Por quê:** operações de ponto flutuante têm imprecisão inerente (`0.1 + 0.2 !== 0.3`). Em contexto financeiro isso é inaceitável. O `bcmath` faz aritmética arbitrária em precisão com strings, eliminando o problema.

### 5. Mailer: `symfony/mailer` com transport SMTP (Mailhog)

O Hyperf não tem componente oficial de e-mail maduro. A solução foi adaptar `symfony/mailer` (biblioteca standalone, sem dependência do Symfony Framework) por trás de uma interface própria `MailerInterface`.

**Por quê:** `symfony/mailer` é maduro, amplamente testado e suporta qualquer transport SMTP. A interface própria desacopla o código de negócio da biblioteca concreta — trocar por SendGrid ou SES no futuro é questão de mudar a implementação da interface.

**Trade-off:** a instância de `Mailer` é criada no construtor de `SymfonyMailer`. Em Swoole/Hyperf, conexões de recursos externos devem ser coroutine-safe; SMTP síncrono bloqueia a coroutine durante o envio. Para produção de alta carga, o correto seria delegar o envio a uma queue/job assíncrono. No escopo deste case, o envio síncrono é suficiente.

### 6. Logs JSON estruturados com `request_id`

Toda request recebe um `X-Request-Id` (gerado ou propagado pelo header). O `RequestIdProcessor` do Monolog injeta esse ID em cada linha de log via `Hyperf\Context` (coroutine-safe).

**Por quê:** logs estruturados em JSON permitem query e correlação em qualquer sistema de observabilidade (Datadog, Grafana Loki, Elasticsearch). O `request_id` permite reconstruir toda a cadeia de eventos de uma request específica.

### 7. Saque agendado — débito apenas no processamento

Ao registrar um saque com `schedule`, nenhum débito ocorre na hora. O saldo só é comprometido quando o crontab processa o registro.

**Trade-off conhecido:** se o usuário gastar o saldo entre o agendamento e a data de execução, o crontab marcará `done=true, error=true, error_reason='insufficient_funds'` — comportamento especificado. Uma melhoria futura seria reservar/bloquear o saldo no momento do agendamento.

---

## Trade-offs e melhorias futuras

| Item | Decisão atual | Melhoria futura |
|------|---------------|-----------------|
| Envio de e-mail | Síncrono na request/cron | Queue assíncrona (Redis/RabbitMQ) |
| Reserva de saldo em agendamentos | Não implementada | Bloquear saldo no agendamento |
| Idempotência de requests | Não implementada | Header `Idempotency-Key` + cache |
| Tipos de chave PIX | email implementado, outros aceitam qualquer string | Validações específicas por tipo |
| Autenticação/autorização | Não implementada (fora do escopo) | JWT Bearer token |
| Métricas | Logs estruturados | Prometheus + Grafana |
| Testes de integração | Banco real no container | CI com banco efêmero |

---

## Estrutura do projeto

```
app/
├── Controller/Account/WithdrawController.php   # POST endpoint
├── Request/WithdrawRequest.php                 # Validação Hyperf
├── Service/
│   ├── WithdrawService.php                     # Orquestrador principal
│   └── Withdraw/
│       ├── WithdrawMethodInterface.php
│       ├── WithdrawMethodFactory.php
│       └── Pix/
│           ├── PixWithdrawStrategy.php
│           ├── PixKeyValidatorRegistry.php
│           └── KeyValidator/
│               ├── EmailPixKeyValidator.php
│               ├── CpfPixKeyValidator.php
│               └── RandomPixKeyValidator.php
├── Model/
│   ├── Account.php
│   ├── AccountWithdraw.php
│   └── AccountWithdrawPix.php
├── Crontab/ProcessScheduledWithdrawsCrontab.php
├── Mail/
│   ├── MailerInterface.php
│   ├── SymfonyMailer.php
│   └── Notification/
│       ├── AbstractNotification.php
│       └── WithdrawCompletedNotification.php
├── Exception/
│   ├── AccountNotFoundException.php
│   ├── InsufficientFundsException.php
│   ├── InvalidScheduleException.php
│   └── Handler/AppExceptionHandler.php
├── Middleware/RequestIdMiddleware.php
└── Logger/RequestIdProcessor.php

migrations/                  # 3 migrations (account, withdraw, pix)
seeders/AccountSeeder.php
test/
├── Unit/
│   └── Service/...          # Testes unitários (sem banco)
└── Feature/
    ├── WithdrawEndpointTest.php
    ├── CrontabProcessingTest.php
    └── ConcurrentWithdrawTest.php
```

## Variáveis de ambiente

| Variável | Padrão | Descrição |
|----------|--------|-----------|
| `APP_ENV` | `dev` | Ambiente |
| `DB_HOST` | `mysql` | Host do MySQL |
| `DB_DATABASE` | `tecnofit` | Nome do banco |
| `DB_USERNAME` | `tecnofit` | Usuário |
| `DB_PASSWORD` | `secret` | Senha |
| `MAIL_HOST` | `mailhog` | Host SMTP |
| `MAIL_PORT` | `1025` | Porta SMTP |
| `MAIL_FROM_ADDRESS` | `noreply@tecnofit.com` | Remetente |
| `CRONTAB_ENABLE` | `true` | Liga/desliga crontab |
