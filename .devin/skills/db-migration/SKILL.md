---
name: db-migration
description: Vytvoření nebo úprava databázové migrace pro WP Monitor — Doctrine DBAL, reverzibilní migrace, verifikace schématu.
triggers:
  - user
---

# DB migrace

## Kontext
Tento workflow vytvoří novou databázovou migraci nebo upraví existující podle specifikace.

## Priority (bez výjimek)
Při plánování a realizaci se vždy řídit těmito prioritami v uvedeném pořadí — mají přednost před vším ostatním:
1. **Bezpečnost** — žádné kompromisy. Žádné plaintext credentials, šifrování přes CryptoService, žádné interní ID veřejně, prepared statements
2. **Rychlost** — indexy na kritických sloupcích, minimální payload, žádné N+1 dotazy, lazy loading
3. **Modularita** — reverzibilní migrace, žádná duplikace logiky, generické služby v `Core/` nebo `Shared/`

## Kroky

0. **Vytvoř implementační plán** — před začátkem implementace vytvoř plán podle šablony `docs/implementation-plan-template.md` do `docs/implementation-plans/{YYYY-MM-DD}-{název}.md`. Použij skill `implementation-plan`. Počkej na schválení uživatelem před pokračováním.

0a. **Načti styl kódování** — před jakýmkoliv psaním kódu načti do kontextu konvence projektu, ať píšeš kód rovnou ve správném stylu a snížíš počet chyb při kontrole:
   - **Backend**: přečti `backend/.php-cs-fixer.php` (PSR-12 pravidla), `backend/phpstan.neon` (PHPStan level 8), projdi 2-3 existující migrace v `backend/migrations/` jako referenci skutečného stylu
   - **Konvence**: backend `final` třídy + `readonly` props + typed returns + PHPDoc; importy globálních tříd (`use RuntimeException` ne `\RuntimeException`), `use function` pro built-in funkce
   - Spusť `composer cs-check --dry-run` na aktuálním kódu, ať víš, jaký čistý výstup má vypadat

0b. **Zálohuj databázi** — před jakoukoliv operací s databází (migrace, úprava schématu, spuštění `up()` i `down()`) vytvoř zálohu. Vždy, bez výjimky, na všech prostředích (dev i produkce).

1. **Zjisti reálný stav** — ověř skutečnou situaci v databázi a kódu, nespoléhej jen na dokumentaci:
   - `mcp_server_mysql` MCP: `mysql_query` s `SHOW TABLES` a `DESCRIBE` pro existující tabulky a sloupce
   - Přečti existující migrace v `backend/migrations/` — co už je vytvořeno
   - Přečti `docs/05-database-schema.md` jako referenci, ale dokumentace nemusí být aktuální — je určena spíše pro přehled
   - Reálný stav databáze a kódu má prioritu nad dokumentací

2. **Vytvoř migraci:**
   - Název: `Version{YYYYMMDD}_{NN}_{description}.php` v `backend/migrations/`
   - Použij Doctrine DBAL `Connection` a `Schema` (vlastní migrace, ne Doctrine Migrations balíček)
   - Konstruktor přijímá `Connection $connection` (DI)
   - Implementuj `up(): void` a `down(): void`
   - Pro příklad viz `backend/migrations/Version20260807_01_create_core_tables.php`

3. **Kontrola:**
   - Všechny tabulky: `ENGINE=InnoDB`, `CHARSET=utf8mb4`, `COLLATE=utf8mb4_unicode_ci`
   - Indexy na kritických sloupcích (cizí klíče, filtrované sloupce, řazené sloupce)
   - Foreign keys s odpovídající ON DELETE strategií (CASCADE / SET NULL)
   - Audit log tabulka: append-only trigger
   - JSON sloupce pro flexibilní metadata
   - TIMESTAMP s DEFAULT CURRENT_TIMESTAMP

4. **Vytvoř test:**
   - Test že migrace proběhne na čisté DB
   - Test že down() správně odstraní tabulky

5. **Aktualizuj dokumentaci:**
   - Pokud přidáváš novou tabulku, aktualizuj `docs/05-database-schema.md`
   - Pokud přidáváš index, aktualizuj sekci Indexy a výkon

6. **Verifikace schématu přes mcp_server_mysql MCP:**
   - Po spuštění migrace ověř schéma přes `mcp_server_mysql` MCP server:
     - `mysql_query` s `SHOW TABLES` — ověř že nové tabulky existují
     - `mysql_query` s `DESCRIBE` — ověř že sloupce, typy a indexy odpovídají migraci
     - `mysql_query` s `SHOW INDEX FROM {table}` — ověř že indexy byly vytvořeny
     - `mysql_query` s `SHOW CREATE TABLE {table}` — ověř ENGINE, CHARSET a COLLATE
     - `mysql_query` s `SELECT COUNT(*) FROM {table}` na referenčních tabulkách — ověř že nejsou prázdné pokud nemají být
   - Po `down()` ověř přes `mysql_query` s `SHOW TABLES` že tabulky byly odstraněny

## Poznámky
- Záloha DB je krok 0 — vždy, před jakoukoliv operací, na všech prostředích
- Migrace musí být reverzibilní (down() musí fungovat)
- Pro spuštění migrace spusť v adresáři backend/ a příkazem `php bin/migrate`
- Pro verifikaci schématu použij `mcp_server_mysql` MCP server (nástroje `mysql_query` s `SHOW TABLES`, `mysql_query` s `DESCRIBE`, `mysql_query`)

## Ladění chyb (striktní pravidla)
- **Žádné odhadování** — nezkoušet naslepo dvacet možností
- **Nejprve dokumentace** — před opravou najít oficiální dokumentaci (`webfetch`, `web_search`, web search, GitHub Issues)
- **Logování do souboru** — pokud chyba není zřejmá, vytvoř `.debug.log` v `storage/logs/`, zapisuj výstupy, soubor musí být čitelný přes `read` nástroj, po vyřešení smazat
- **Systematicky**: reprodukovat → přečíst celou chybu → najít docs → identifikovat root cause → jedno řešení → ověřit
- **Zakázáno**: `--force`, `--no-verify`, obcházení kontrol, naslepo měnit konfiguraci
