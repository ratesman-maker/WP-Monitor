---
name: build-deploy
description: Produkční build a deploy WP Monitor aplikace — frontend build, backend příprava, post-deploy kontrola.
triggers:
  - user
---

# Build a deploy

## Kontext
Tento workflow provede produkční build frontendu, připraví backend a nasadí aplikaci.

## Priority (bez výjimky)
Při plánování a realizaci se vždy řídit těmito prioritami v uvedeném pořadí — mají přednost před vším ostatním:
1. **Bezpečnost** — žádné kompromisy. `APP_ENV=production`, `APP_DEBUG=false`, silný `APP_KEY`, HTTPS, `.env` není veřejně přístupný, security headers, debug mode vypnutý
2. **Rychlost** — optimalizovaný build, statické soubory přes web server, cache headers pro assets
3. **Modularita** — moduly nasazeny konzistentně, žádné osiřelé závislosti

## Kroky

0. **Vytvoř implementační plán** — pokud deploy zahrnuje změny (ne jen rutinní nasazení), vytvoř plán podle šablony `docs/implementation-plan-template.md` do `docs/implementation-plans/{YYYY-MM-DD}-{název}.md`. Použij skill `implementation-plan`. Počkej na schválení uživatelem. Pro rutinní deploy bez změn plán není potřeba.

1. **Zjisti reálný stav** — ověř skutečnou situaci v projektu, nespoléhej jen na dokumentaci:
   - Zkontroluj reálnou strukturu `frontend/` a `backend/` — jaké skripty, config a assets skutečně existují
   - Ověř reálný obsah `.env` (přítomnost, produkční hodnoty)
   - Přečti `docs/07-development-guide.md` sekci Build a deploy jako referenci, ale dokumentace nemusí být aktuální — je určena spíše pro přehled
   - Reálný stav projektu má prioritu nad dokumentací

2. **Pre-build kontrola:**
   - Zkontroluj že `.env` je nastaven pro produkci (`APP_ENV=production`, `APP_DEBUG=false`)
   - Zkontroluj že `APP_KEY` je silný (32+ bytes)
   - Zkontroluj že HTTPS je povoleno

3. **Frontend build:**
   - Spusť `npm run build` ve `frontend/` adresáři (spusť v adresáři frontend/)
   - Výstup je ve `frontend/dist/`

4. **Kopírování buildu:**
   - Zkopíruj obsah `frontend/dist/` do `backend/public/assets/`

5. **Backend příprava:**
   - Spusť `composer install --no-dev --optimize-autoloader` (spusť v adresáři backend/)
   - Spusť DB migrace: `php bin/migrate` (spusť v adresáři backend/)
   - Pokud je cache zapnutá, spusť cache clear

6. **Post-deploy kontrola:**
   - Ověř že `public/assets/` obsahuje JS a CSS soubory
   - Ověř že `.env` není veřejně přístupný
   - Ověř security headers (použij curl nebo Node.js fetch)
   - Ověř že debug mode je vypnutý

7. **Vizuální kontrola přes prohlížeč (mcp-playwright):**
   - Otevři deployovanou URL přes `browser_navigate`
   - Pořiď screenshot hlavní stránky přes `browser_take_screenshot` — ověř že layout není rozbitý
   - Zkontroluj konzoli na chyby přes `browser_console_messages` s `level: "error"`
   - Naviguj na hlavní routy (dashboard, login) přes `browser_navigate` a ověř že nepadají 404/500
   - Pokud máš E2E testy, spusť je přes Playwright a ověř výsledky

## Poznámky
- Pro všechny příkazy spusť s příslušným pracovním adresářem
- Vždy zálohuj DB před migrací
- Po deploy zkontroluj audit log že je funkční
- Pro vizuální kontrolu použij prohlížeč pro vizuální kontrolu
