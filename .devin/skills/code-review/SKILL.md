---
name: code-review
description: Code review pro WP Monitor — kontrola bezpečnosti, kvality a konzistence s důrazem na bezpečnost (P0 priorita).
triggers:
  - user
---

# Code review

## Kontext
Tento workflow provádí systematický code review s důrazem na bezpečnost (P0 priorita) a kvalitu kódu.

## Priority (bez výjimky)
Při review se vždy řídit těmito prioritami v uvedeném pořadí — mají přednost před vším ostatním:
1. **Bezpečnost** — žádné kompromisy. Žádné plaintext credentials, šifrování CryptoService, CSRF, input validace, žádné `eval()`/`exec()`, žádné `dangerouslySetInnerHTML`, žádné interní ID veřejně
2. **Rychlost** — indexy na kritických sloupcích, minimální payload, HTTP cache headers, lazy loading, žádné N+1 dotazy, WebP
3. **Modularita** — EventDispatcher komunikace, tenké controllery, generické služby v `Core/` nebo `Shared/`, reusable komponenty v `components/common/`

## Kroky

0. **Vytvoř implementační plán** — pokud review vede k úpravám kódu, vytvoř plán podle šablony `docs/implementation-plan-template.md` do `docs/implementation-plans/{YYYY-MM-DD}-{název}.md`. Použij skill `implementation-plan`. Počkej na schválení uživatelem před pokračováním. Pokud review jen identifikuje problémy bez úprav, plán není potřeba.

1. **Zjisti reálný stav** — ověř skutečnou situaci v kódu a databázi, nespoléhej jen na dokumentaci:
   - Projdi reálnou strukturu projektu v `backend/src/` a `frontend/src/` — jaké moduly, služby a patterny skutečně existují
   - Ověř reálné DB schéma přes `mcp_server_mysql` MCP (`mysql_query` s `SHOW TABLES`, `DESCRIBE`) pokud review zahrnuje DB změny
   - Přečti `docs/03-security-model.md`, `docs/02-architecture.md` a `docs/07-development-guide.md` jako referenci, ale dokumentace nemusí být aktuální — je určena spíše pro přehled
   - Reálný stav kódu a databáze má prioritu nad dokumentací

2. **Bezpečnostní kontrola (kritická):**
   - Žádné plaintext credentials v kódu, logu nebo API response
   - Všechny credentials šifrovány přes CryptoService s AAD
   - Žádné hardcode API klíče nebo hesla
   - HTTPS vynuceno pro všechny WP client požadavky
   - CSRF token na všech POST/PUT/DELETE endpointech
   - Input validace na všech API endpointech
   - Rate limiting na login a kritických endpointech
   - `sodium_memzero()` pro wipe dešifrovaných dat
   - Žádné `eval()`, `exec()` nebo `shell_exec()` v PHP
   - Žádné `dangerouslySetInnerHTML` v React
   - Žádné interní ID (user, module, DB) v názvech souborů, cestách nebo veřejných identifikátorech — použít UUID v4 (ramsey/uuid) nebo SHA-256 hash. Zabrání enumeration a information disclosure

3. **Architektonická kontrola:**
   - Moduly komunikují přes EventDispatcher, ne přímými voláními
   - Kontrolery jsou tenké — logika v Service vrstvě
   - Repository pattern pro DB přístup
   - DI container pro všechny závislosti
   - Moduly mají manifest.json a implementují ModuleInterface
   - Generické služby extrahovány do `Core/` nebo `Shared/` — žádná duplikace logiky napříč moduly
   - Reusable frontend komponenty v `components/common/`, hooky v `hooks/`

3b. **Kontrola rychlosti (neslevitelná priorita):**
   - Statické soubory servírovány přes web server, ne přes PHP
   - HTTP cache headers nastaveny pro cacheovatelné response (`ETag`, `Cache-Control`)
   - DB dotazy: jen potřebné sloupce, indexy na filtrovaných/řazených sloupcích
   - API response: minimální payload, žádné zbytečné vztahy nebo pole
   - Frontend: lazy loading, `width`/`height` proti layout shift, TanStack Query caching
   - Obrázky: WebP formát, client-side resize před uploadem
   - Žádné N+1 dotazy

4. **Kontrola kvality kódu:**
   - PHP: `declare(strict_types=1)`, type hints, readonly properties
   - TypeScript: správné typy, `import type` pro type-only importy
   - Žádné TODO/FIXME bez issue linku
   - Konzistentní pojmenování podle konvencí
   - Žádné mrtvé kódy (nepoužívané funkce, importy)

4b. **Kontrola špaget kódu (viz docs/07-development-guide.md sekce 2.3):**
   - Třída má 5+ různých témat v metodách → rozdělit
   - Metoda má 4+ úrovně if/else nesting → early return nebo extrakce
   - Komponenta má 8+ useState → custom hook nebo Zustand
   - Service injectuje 6+ závislostí → rozdělit na subservisy
   - Stejná logika ve 3+ souborech → extrahovat
   - Funkce volá 5+ dalších funkcí v řadě → zjednodušit pipeline
   - Security třídy (CryptoService, AuthMiddleware) > 200 řádků → refaktor
   - Service > 400 řádků → zvážit rozdělení
   - Controller > 150 řádků → logiku přesunout do Service
   - Page komponenta > 250 řádků → rozdělit na sub-komponenty
   - Max 4 parametry v metodě/funkci (více = DTO/options objekt)
   - Max 3 úrovně nesting v metodách
   - Dependency depth ≤ 3 (A→B→C→D je limit)

4c. **Kontrola standardů (viz docs/07-development-guide.md sekce 2.0):**
   - PHP: `declare(strict_types=1)` na všech souborech
   - PHP: type hints na všech parametrech a return typech
   - PHP: readonly properties kde vhodné
   - PHP: PSR-12 formátování (spusť `composer cs-check`)
   - PHP: PHPStan level 8 bez chyb (spusť `composer analyse`)
   - TS: žádné `any` typy (vynuceno ESLint)
   - TS: `import type` pro type-only importy (vynuceno ESLint)
   - TS: named exports (vynuceno ESLint, kromě page komponent)
   - TS: strict mode — noUncheckedIndexedAccess, exactOptionalPropertyTypes
   - TS: žádné floating promises (vynuceno ESLint)
   - TS: exhaustiveness check na switchech (vynuceno ESLint)
   - TS: žádné cyklické importy > depth 3 (vynuceno ESLint)
   - React: hooks rules + exhaustive deps (vynuceno ESLint)
   - Commits: Conventional Commits formát

5. **Kontrola testů:**
   - Nový kód má testy (unit + integration)
   - Testy pokrývají happy path i error scénáře
   - Security-related kód má testy na šifrování/dešifrování
   - Batch operace mají testy na paralelismus a error handling

6. **Kontrola DB:**
   - Migrace mají up() i down()
   - Indexy na kritických sloupcích
   - Foreign keys s ON DELETE strategií
   - Audit log tabulka je append-only

7. **Vizuální kontrola frontendu (prohlížeč pro vizuální kontrolu):**
   - Pokud review zahrnuje frontend změny, použij prohlížeč pro vizuální kontrolu:
     - `browser_navigate` na lokální dev server (např. `http://localhost:5173`)
     - `browser_take_screenshot` pro vizuální ověření layoutu a komponent
     - `browser_console_messages` s `level: "error"` pro kontrolu JS chyb
     - `browser_find` s `text` nebo `regex` pro ověření že texty a prvky jsou vykreslené
     - `browser_snapshot` pro kontrolu accessibility stromu stránky
   - Ověř že nové komponenty nezhoršují layout shift a responsive chování

8. **Report:**
   - Vypiš seznam nalezených problémů seřazený podle závažnosti (critical → high → medium → low)
   - Pro každý problém uveď soubor, řádek a navrhovanou opravu

## Poznámky
- Bezpečnostní problémy jsou vždy critical — žádné kompromisy
- Dodržuj pravidlo: "100% zabezpečení, rychlost a modularita — ze kterých nejde slevit"
- Pro vizuální kontrolu frontendu použij prohlížeč pro vizuální kontrolu (nástroje `browser_navigate`, `browser_take_screenshot`, `browser_console_messages`, `browser_snapshot`)
