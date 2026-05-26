# Orders REST API

REST API, kterym partneri (obchody) posilaji svoje objednavky a aktualizuji u nich datum doruceni. Postaveno na PHP 8.4, Symfony 8 a Doctrine ORM 3, databaze Postgres 16.

## Co je potreba

- PHP 8.4
- Composer
- Docker (na lokalni Postgres a Adminer)
- Symfony CLI (`symfony` binarka) - kvuli `symfony server:start` a aby se podchytil DATABASE_URL z dockeru

## Spusteni

```bash
docker compose up -d
composer install
symfony console doctrine:migrations:migrate --no-interaction
symfony serve -d
```

API pojede na adrese, kterou vypise `symfony serve` (obvykle `http://127.0.0.1:8000`, port se muze lisit).

Adminer (databazovy UI) je na `http://127.0.0.1:8080`.

## Testy a kontrola kvality

Vse jednou ranou:

```bash
composer check
```

Tim se postupne spusti:

- `composer lint` - php-cs-fixer v dry-run rezimu (coding style)
- `composer stan` - PHPStan na `level: max` se strict rules, bez baseline
- `composer openapi:lint` - Spectral na `docs/openapi.yaml`
- `composer test` - PHPUnit (unit + integracni testy)

Hodi se taky:

```bash
composer lint:fix       # automaticka oprava stylu
composer db:test:reset  # drop + create + migrate testovaci DB
```

## API ve zkratce

OpenAPI spec: [`docs/openapi.yaml`](docs/openapi.yaml).
Pripravene priklady: [`docs/examples/`](docs/examples) a [`docs/http/orders.http`](docs/http/orders.http) (pro REST Client v VS Code).

### Vytvoreni objednavky

```http
POST /api/v1/partners/{partnerId}/orders
Content-Type: application/json

{
  "orderId": "ORD-2026-00001",
  "expectedDeliveryDate": "2026-06-15",
  "totalValue": "1299.99",
  "products": [
    {"productId": "SKU-001", "name": "Headphones", "price": "129.99", "quantity": 2}
  ]
}
```

- `201 Created` + ulozena objednavka v tele odpovedi
- `409 Conflict` kdyz `(partnerId, orderId)` uz existuje - druhe poslani nikdy nic neprepise
- `422 Unprocessable Entity` pri chybe validace, s poli `errors[]` (JSON Pointer + popis)
- `415 Unsupported Media Type` pri jinem Content-Type nez `application/json`
- `400 Bad Request` pri rozbitem JSONu

### Aktualizace data doruceni

```http
PUT /api/v1/partners/{partnerId}/orders/{orderId}/delivery-date
Content-Type: application/json

{"expectedDeliveryDate": "2026-07-20"}
```

- `200 OK` + cele telo aktualizovane objednavky
- `404 Not Found` kdyz objednavka neexistuje, vcetne pripadu, kdy ji ma jiny partner (cross-partner pristup neni mozny)
- PUT je idempotentni - stejny body produkuje stejny vysledny stav

### Chyby

Vsechny chybove odpovedi jsou ve formatu RFC 7807 (`application/problem+json`):

```json
{
  "title": "Validation Failed",
  "status": 422,
  "detail": "One or more fields failed validation.",
  "instance": "/api/v1/partners/PARTNER_A/orders",
  "errors": [
    {"pointer": "/totalValue", "message": "This value should be greater than or equal to 0."}
  ]
}
```

## Architektura

Klasicke vrstveni:

```
Controller (HTTP)
  -> Handler (use-case logika)
     -> Repository interface (Doctrine adapter)
     -> Domain event (OrderDeliveryDateChangedEvent)
        -> EventListener (audit log)
```

- Entity (`Order`, `OrderProduct`, `OrderAuditLog`) jsou tenke nositele dat, zadna business logika v nich
- Validace na trech urovnich: DTO (struktura), service (invariants), DB (unique constraint)
- Vsechny chyby tece pres `ApiProblemExceptionListener` - jedno misto, ktere mapuje vyjimky na RFC 7807
- Pro penize `BigDecimal` z `brick/math` + vlastni Doctrine typ `bigdecimal` a normalizer. Zadne `float` v cele penezni ceste, jinak by se driv nebo pozdeji ztratil halir.
- Interni primarni klic je UUID v4 (`symfony/uid`), externe se pouziva partnersky `(partnerId, orderId)`
- Update data doruceni a zapis do audit logu jsou ve stejne transakci - bud obe veci, nebo zadna

### Testy

- Unit testy nad service vrstvou (`tests/Service/`) - happy/sad cesty, idempotence, vyjimky
- Integracni `WebTestCase` na oba endpointy (`tests/Functional/`) - cely HTTP cyklus vcetne mapovani DTO a chyb
- Validator a normalizer testy
- In-memory repository implementace pro odstineni od DB v unit testech
- `dama/doctrine-test-bundle` obali kazdy DB test transakci a po dokonceni rollbackne, takze testy mezi sebou neinterferuji

## O cem jsem premyslel a co bych delal jinak

Veci, ktere jsem nedelal nebo udelal jinak, nez bych je delal v ostrem provozu. Vsechny mam vedome a v ucelu casu jsem se rozhodl je vynechat.

### partnerId v URL vs autentizacni token

Ted bere endpoint `partnerId` z URL: `/partners/{partnerId}/orders`. V ostrem provozu by partnera mel identifikovat autentizacni mechanismus (API klic, OAuth scope, JWT subject). Klient by ho neposilal sam, server by si ho vytahl z tokenu. Tim odpada cely radius chyb: klient nemuze omylem ani umyslne odeslat data za jineho partnera. Endpointy se zjednodusi na `/orders` resp. `/orders/{orderId}/delivery-date`. Pro tento ukol je autentizace explicitne mimo zadani, takze partnerId zustava v URL.

### Validace data doruceni v minulosti

Backend prijme i datum v minulosti a ulozi ho. Drzime se zadani ("ulozime, co partner poslal"), takze tvrda validace na strane API by sla proti smyslu pozadavku.

Kontrolu intentu povazuji za ulohu klienta: pri zadani uz uplynuleho data zobrazit potvrzeni typu "Opravdu chcete nastavit datum v minulosti?". Tim se zachyti preklepy, ale legitimni zpetne opravy (oprava chybne zadane hodnoty, doplneni po vraceni zbozi, oprava importu) projdou. Tvrde omezeni na backendu by bez konkretne formulovaneho obchodniho pravidla blokovalo i tyto pripady - to je rozhodnuti, ktere by melo prijit z domeny, ne z defaultni opatrnosti.

### Mena

Zadani menu nezminuje, v modelu tedy chybi. Pricina je vedoma: cena bez meny neni penezni hodnota, je to jen cislo. Jakmile by se ulozila s implicitnim predpokladem (typicky CZK), prvni partner s eshopem v EUR by se o ten predpoklad rozbil - a oprava po faktu znamena migraci dat, nikoli zmenu modelu.

Cisty model meny stoji na tech bodech:

- ISO 4217 kod (`CZK`, `EUR`, `USD`, `JPY`...) jako enum nebo ciselnik.
- Mena nese objednavka, produktove radky ji dedi. Jedna objednavka = jedna mena. Smes EUR/USD produktu v jedne objednavce nedava ucetni smysl.
- Pri konverzi do reportingove meny FAVI musi byt smenny kurz zafixovan v okamziku vzniku objednavky (`order.exchangeRate`, `order.exchangeRateDate`). Pozdejsi prepocet aktualnim kurzem by retroaktivne menil ucetni vystupy.

`brick/money` resi tohle vse out-of-the-box vcetne kontrol typu "nelze scitat ruzne meny bez explicitniho prepocteni". Pridat to po faktu znamena migraci historickych dat (`UPDATE orders SET currency = 'CZK'` na celou tabulku) a refaktor vsech mist, kde se s cenou pracuje. Proto je to prvni vec, kterou bych za touto verzi delal hned.

### DPH

Stejna logika jako u meny. `price` a `totalValue` jsou ted "cislo, ktere prislo". Nevime, jestli s DPH nebo bez, neumime to rozlisit, neumime to spocitat sami. Realne by se to chtelo rozdelit na `priceNet`, `vatRate`, `priceGross` a navazat na ciselnik DPH sazeb (CZ ma zakladni 21 %, snizene 12 % a 0 %, plus posunute hranice pro nektere zbozi). To je netrivialni domena - sazba se meni zakonem a uplatnuje se podle typu zbozi a misto dodani - takze bez konkretniho zadani jsem do toho nesahal.

### Celkova hodnota objednavky

Ukladam `totalValue` tak, jak ji partner poslal. Nepocitam ji ze souctu produktu a neporovnavam ji s nim. Zadani to chce takhle ("ulozime, co prislo"). V provozu bych dodelal jednu vec: pri detekci `total != sum(price * quantity)` logovat metriku, ale neodmitnout. Rozdil totiz nemusi byt chyba - muze jit o slevu, akcni cenu, manualni upravu nebo zaokrouhleni, ktere my nevidime. Nicmene "kolik objednavek dochazi s nesedicim souctem" je zajimave cislo pro datovy team.

### Audit log

Pri PUT data doruceni vznika radek v tabulce `order_audit_log`: kdo (`actorUserId`), kdy (`occurredAt`), na cem (`partnerId` + `orderIdValue`), co se zmenilo (`changes` JSON: `{"expectedDeliveryDate": {"old": ..., "new": ...}}`). Je to nad ramec zadani, ale:

- v B2B integracich je auditni stopa casto regulatorni pozadavek
- "kdo to zmenil" je prvni otazka pri kazdem sporu se zakaznikem
- listener bezi ve stejne transakci jako sam update - kdyby zapis auditu selhal, update se rollbackne. Stav "data se zmenila, ale audit chybi" se proste nemuze stat.

Identitu volajiciho ted dodava `MockUserContext` (vraci konstantu) - jakmile se zapoji autentizace, bude to brat z `Security` tokenu.

### UUID v4 jako interni klic

Pouzivam `Symfony\Component\Uid\Uuid::v4()`. V4 je ciste nahodne, takze pri vyssim zapisu fragmentuje B-tree index hure nez UUID v7 nebo ULID (ktere maji casovou slozku, takze nove zaznamy jdou na "konec" indexu). Pri stovkach objednavek za den je to jedno, pri stovkach za sekundu uz ne. Pri rustu trafiku by byl prechod na v7 prvni krok.

### Doctrine typ `bigdecimal` misto nativniho `decimal`

Doctrine nativni `decimal` vraci v PHP `string`. Drive nebo pozdeji to skonci tak, ze nekdo udela `(float) $order->totalValue * 1.21` a o halir prijdou. Vlastni typ vraci `Brick\Math\BigDecimal`, na kterem se float operace ani nedaji volat - musi se psat `$total->multipliedBy('1.21')`. Stoji to par radku navic (`BigDecimalType`, `BigDecimalNormalizer`, dva custom validatory `BigDecimalGreaterThanOrEqual` a `BigDecimalMaxScale`), ale typove vynucuje korektni penezni aritmetiku v celem kodu.

### URL design: action endpoint vs PATCH

Update data doruceni je samostatny endpoint `PUT .../delivery-date`, ne obecny `PATCH .../orders/{id}`. Duvody:

- kazda aktualizovatelna vec ma svuj endpoint, svoji validaci, svoje rate-limit/auditni pravidlo
- partneri se snadneji integruji - kazdy endpoint je dokumentovany jako konkretni operace
- pri pridani permission modelu se "kdo smi menit datum doruceni" da gateovat na route, ne v telu requestu

Cena je, ze kazde nove pole vyzaduje novy endpoint. To je vyhodnejsi nez polymorfni PATCH, ve kterem se snadno schova subtle bug ("klient poslal `null`, mysleno bylo `nezahrnovat`").

### Coding standard

PHP 8.4 - readonly classes (DTO), asymmetric visibility (entity properties), constructor property promotion, `declare(strict_types=1)` vsude.
PHPStan na `level: max` + strict rules + Symfony a Doctrine rozsireni, bez baseline. Chyby se chyti pri staticke analyze, ne v provoznich logach.
php-cs-fixer s `@PER-CS2.0`, `@PHP84Migration`, `@Symfony`, `@Symfony:risky`.

CI pipeline (`.github/workflows/ci.yml`) prohrava `composer check` na kazdy push a PR.
