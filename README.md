# Систем за клиентски сметки и хартии од вредност

Laravel бекенд (REST API) кој ги памти сите движења на клиентска сметка
(депозит, подигнување, купување, продавање) и во секој момент може да
пресмета колку готовина клиентот има и што поседува.

## Како да се подигне проектот локално

```bash
composer install
cp .env.example .env
php artisan key:generate

touch database/database.sqlite

php artisan migrate --seed
php artisan test
php artisan serve
```

По ова, API-то е достапно на `http://127.0.0.1:8000/api`.

`--seed` внесува 3 примерни клиенти (Ана, Марко, Јана) со неколку
движења секоја.

## Начини за комуникација со системот

### Креирање клиент

Испраќаш:

POST /api/clients
{ "name": "Ана Петровска" }


Добиваш назад:
```json
{
  "data": {
    "id": 1,
    "name": "Ана Петровска",
    "cash_balance": 0,
    "holdings": []
  }
}
```

### Депозит

Испраќаш:

POST /api/clients/1/deposit
{ "amount": 1000 }


Добиваш назад:
```json
{
  "data": {
    "transaction": { "id": 4, "type": "deposit", "amount": 1000, "instrument": null, "quantity": null, "price": null },
    "cash_balance": 1000,
    "holdings": []
  }
}
```

### Подигнување

Испраќаш:

POST /api/clients/1/withdraw
{ "amount": 300 }


Добиваш назад:
```json
{
  "data": {
    "transaction": { "id": 5, "type": "withdrawal", "amount": 300, "instrument": null, "quantity": null, "price": null },
    "cash_balance": 700,
    "holdings": []
  }
}
```

Ако бараниот износ е поголем од достапната готовина, враќа `422`:
```json
{ "message": "Клиентот „Ана Петровска“ има само 700 на располагање, не може да подигне 5000." }
```

### Купување

Испраќаш:

POST /api/clients/1/buy
{ "instrument": "AAPL", "quantity": 5, "price": 100 }


Добиваш назад:
```json
{
  "data": {
    "transaction": { "id": 6, "type": "buy", "amount": 500, "instrument": "AAPL", "quantity": 5, "price": 100 },
    "cash_balance": 200,
    "holdings": { "AAPL": 5 }
  }
}
```

### Продавање

Испраќаш:

POST /api/clients/1/sell
{ "instrument": "AAPL", "quantity": 3, "price": 120 }


Добиваш назад:
```json
{
  "data": {
    "transaction": { "id": 7, "type": "sell", "amount": 360, "instrument": "AAPL", "quantity": 3, "price": 120 },
    "cash_balance": 560,
    "holdings": { "AAPL": 2 }
  }
}
```

Ако количината е поголема од тоа што клиентот поседува, враќа `422`:
```json
{ "message": "Клиентот „Ана Петровска“ поседува само 2 парчиња од AAPL, не може да продаде 10." }
```

### Преглед на состојба

Испраќаш:

GET /api/clients/1


Добиваш назад:
```json
{
  "data": {
    "id": 1,
    "name": "Ана Петровска",
    "cash_balance": 860,
    "holdings": { "AAPL": 2 }
  }
}
```

### Целосна историја (ledger)

Испраќаш:

GET /api/clients/1/transactions


Добиваш назад:
```json
{
  "data": [
    { "id": 1, "type": "deposit", "amount": 1000, "instrument": null, "quantity": null, "price": null, "created_at": "2026-09-04T13:05:10.000000Z" },
    { "id": 2, "type": "buy", "amount": 500, "instrument": "AAPL", "quantity": 5, "price": 100, "created_at": "2026-09-04T13:05:10.000000Z" },
    { "id": 3, "type": "sell", "amount": 360, "instrument": "AAPL", "quantity": 3, "price": 120, "created_at": "2026-09-04T13:05:10.000000Z" }
  ]
}
```

### Список на сите клиенти

Испраќаш:

GET /api/clients


Добиваш назад:
```json
{
  "data": [
    { "id": 1, "name": "Ана Петровска", "cash_balance": 860, "holdings": { "AAPL": 2 } },
    { "id": 2, "name": "Марко Стојаноски", "cash_balance": 1780, "holdings": { "MSFT": 6, "TSLA": 4 } },
    { "id": 3, "name": "Јана Илиевска", "cash_balance": 250, "holdings": [] }
  ]
}
```

## Зошто вака

**Состојбата се пресметува од историјата, не се чува посебно.**
Готовината и поседувањата секогаш се пресметуваат од целата листа
движења (`AccountService::getCashBalance` / `getHoldings`), наместо да
се чуваат во посебна колона што се ажурира при секое движење. Ова
значи дека не постои начин состојбата да се расинхронизира со
историјата.

**Правилата се во сервисна класа, не во контролерот.**
`AccountController` и `ClientController` не одлучуваат ништо - само го
викаат `AccountService`. Ова прави тестирање можно директно, без да се
минува низ HTTP слој.

**Секое движење се пишува во `DB::transaction()` со `lockForUpdate()`.**
Ова спречува race condition - ако два барања пристигнат речиси во исто
време, базата ги сериjализира. `DB::transaction` автоматски прави
rollback штом се фрли исклучок, па нема половично запишани редови.

**Исклучоци наместо if/враќање грешка низ повеќе слоеви.**
`InsufficientFundsException` и `InsufficientHoldingsException` се
фрлаат директно од `AccountService` и се фаќаат централно во
`bootstrap/app.php`, каде се претвораат во `422` JSON одговор.

**Валидацијата на формат е одвоена од валидацијата на бизнис-правила.**
Form Request класите проверуваат дали бројот воопшто има смисла (не е
негативен, е цел број), пред барањето да стигне до `AccountService`,
каде се проверува дали конкретното движење е дозволено за конкретниот
клиент.

**Инструментот секогаш се чува со главни букви.** Со ова "aapl" и
"AAPL" се третираат како ист инструмент.

## Примерни клиенти (по seed)

| Клиент | Готовина | Поседувања |
|---|---|---|
| Ана Петровска | 860.00 | 2 AAPL |
| Марко Стојаноски | 1780.00 | 6 MSFT, 4 TSLA |
| Јана Илиевска | 250.00 | (ништо) |