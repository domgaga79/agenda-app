# Blindagem multitenant — Agenda Bacuri

Este patch reforça o isolamento por `company_id` sem alterar a landing-page.

## Alterações

- adiciona `BelongsToCompany::forCompany()` aos models tenant-aware;
- adiciona `TenantIntegrity`, que bloqueia gravações Eloquent com referências cruzadas entre empresas;
- reforça confirmação pública para revalidar `company_id` do agendamento;
- reforça financeiro e despesas para não seguir relações de outro tenant em pagamentos/estornos;
- reforça carregamentos de profissionais, confirmações e relações sensíveis;
- adiciona `php artisan tenant:audit` para localizar inconsistências existentes;
- cria `company_slug_reservations`, impedindo que um slug antigo de uma empresa seja apropriado por outra;
- mantém slugs históricos apontando para a mesma empresa no agendamento público;
- adiciona testes específicos de hardening multitenant.

## Aplicação

Extraia este pacote **dentro de `~/agenda_app`** e execute:

```bash
cd ~/agenda_app
bash blindagem_multitenant/aplicar.sh
```

O instalador cria um backup antes de substituir qualquer arquivo.

## Verificação manual

Depois da instalação:

```bash
cd ~/agenda_app
php artisan tenant:audit --fail
php artisan migrate:status
```

`tenant:audit --fail` deve terminar com:

```text
OK: nenhuma referência cruzada entre tenants foi encontrada.
```

## Testes automatizados

No servidor, se o PHP CLI tiver `dom`, `mbstring`, `xmlwriter` e o driver SQLite:

```bash
php artisan test --filter TenantHardeningTest
php artisan test
```

O ambiente usado para preparar este patch não possui `dom`, `mbstring` e `xmlwriter`, portanto a suíte PHPUnit não pôde ser executada aqui. Todos os arquivos PHP alterados passaram no `php -l`, e `php artisan route:list` inicializou o Laravel com 101 rotas após o patch.
