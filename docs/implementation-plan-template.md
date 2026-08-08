# Implementační plán — {NÁZEV}

## Metadata

| Pole | Hodnota |
|------|---------|
| Název | {NÁZEV} |
| Typ | {modul / funkce / refaktor / bugfix} |
| Priorita | {P0 / P1 / P2} |
| Status | {draft / approved / in-progress / completed / cancelled} |
| Vytvořeno | {DD.MM.YYYY} |
| Autor | {jméno / AI agent} |
| Související workflow | {název workflow souboru} |
| Související dokumentace | {docs/XX-...md} |

## 1. Cíl

{Jednoznačný popis toho, co má být výsledkem. Co se bude vytvářet, upravovat nebo opravovat.}

## 2. Reálný stav (před implementací)

### 2.1 Backend
{Co už existuje v backend/src/ — soubory, třídy, služby. Co chybí.}

### 2.2 Frontend
{Co už existuje v frontend/src/ — komponenty, stránky, store. Co chybí.}

### 2.3 Databáze
{Reálný stav DB — existující tabulky, sloupce, indexy. Overeno pres mariadb MCP nebo primo.}

### 2.4 Rozdíl oproti dokumentaci
{Co dokumentace popisuje ale v kódu chybí. Co v kódu je ale dokumentace nezachycuje.}

## 3. Priority (bez výjimek)

### 3.1 Bezpečnost
{Konkrétní bezpečnostní opatření pro tento plán — šifrování, auth, CSRF, input validace, atd.}

### 3.2 Rychlost
{Konkrétní performance opatření — indexy, lazy loading, cache, minimální payload, atd.}

### 3.3 Modularita
{Konkrétní modularita — generické služby v Core/, EventDispatcher, reusable komponenty, atd.}

### 3.4 UI komponenty (striktní pravidla)
- **STRIKTNÍ ZÁKAZ vytváření vlastních komponent** — nepoužívat custom CSS ani custom React komponenty pro UI
- **Používat pouze shadcn/ui komponenty** — instalované přes `shadcn` MCP server nebo CLI
- **Používat pouze shadcnblocks.com bloky** pro kompozice (Dashboard, Data Table, Chart, Layout)
- **CSS** — pouze Tailwind utility classes a shadcn CSS proměnné v `globals.css`, žádné vlastní CSS soubory
- **Logické komponenty** (bez UI, např. `ProtectedRoute`) mohou být v `frontend/src/components/`, ale nesmí obsahovat custom styling — pouze skládají shadcn komponenty

## 4. Architektura

### 4.1 Backend
{Třídy, služby, repository, controller, middleware. Vztahy mezi nimi.}

```
src/
├── Core/
│   └── {generické služby}
└── Modules/
    └── {ModuleName}/
        ├── {Module}Module.php
        ├── manifest.json
        ├── Controllers/
        ├── Services/
        └── Repositories/
```

### 4.2 Frontend
{Komponenty, stránky, store, hooks. Vztahy mezi nimi.}

```
src/
├── modules/
│   └── {moduleName}/
│       ├── pages/
│       └── components/
├── components/
│   └── common/
├── hooks/
└── stores/
```

### 4.3 Databáze
{Nové tabulky, sloupce, indexy, migrace. Pokud není DB změna, napiš "Beze změny".}

## 5. API endpointy

| Metoda | Cesta | Popis | Role | Request | Response |
|--------|-------|-------|------|---------|----------|
| {METODA} | {/api/...} | {popis} | {veřejný/auth/manager/admin} | {body} | {body} |

## 6. Kroky implementace

### Fáze 0: Načtení stylu kódování (před jakýmkoliv kódem)
- [ ] **Backend konvence** — přečti a načti do kontextu:
  - `backend/.php-cs-fixer.php` — PSR-12 pravidla (importy, spacing, ordered imports)
  - `backend/phpstan.neon` — PHPStan level 8 + strict rules
  - Projdi 2-3 existující soubory v `backend/src/Modules/` a `backend/src/Core/` jako referenci skutečného stylu
- [ ] **Frontend konvence** — přečti a načti do kontextu:
  - `frontend/.eslintrc.cjs` — ESLint pravidla (import/order, no-default-export, no-floating-promises)
  - `frontend/.prettierrc` (nebo prettier sekce v `package.json`) — formátování
  - `frontend/.stylelintrc.json` — CSS pravidla a výjimky pro shadcn
  - `frontend/tsconfig.json` — strict mode, path aliases
  - Projdi 2-3 existující soubory v `frontend/src/modules/` a `frontend/src/components/` jako referenci skutečného stylu
- [ ] **Konvence názvů a struktury** — ověř:
  - Backend: `final` třídy, `readonly` properties, typed return types, PHPDoc pro `@throws`/`@return`
  - Frontend: named exports (ne default), `async/await` (ne `.then()`), TypeScript strict typy
- [ ] **Ověř linting nástroje** — spusť `composer cs-check --dry-run` a `npm run lint` na aktuálním kódu, ať víš, jaký výstup má vypadat

### Fáze 1: {název fáze}
- [ ] {krok 1}
- [ ] {krok 2}

### Fáze 2: {název fáze}
- [ ] {krok 1}
- [ ] {krok 2}

### Fáze 3: {název fáze}
- [ ] {krok 1}
- [ ] {krok 2}

## 7. Testy

### 7.1 Backend
{Jaké testy se napíší — unit, integration, co pokrývají.}

### 7.2 Frontend
{Jaké testy se napíší — komponent, MSW mock, co pokrývají.}

## 8. Validace

- [ ] `composer test` — bez chyb
- [ ] `composer analyse` — PHPStan level 8 bez chyb
- [ ] `composer cs-check` — PSR-12 bez chyb
- [ ] `npm run test` — bez chyb
- [ ] `npm run lint` — bez chyb
- [ ] `npm run typecheck` — bez chyb
- [ ] DB verifikace přes `mariadb` MCP (`list_tables`, `describe_table`, `SHOW INDEX`)
- [ ] Vizuální kontrola přes `mcp-playwright` (pokud se mění frontend)

## 8b. Ladění chyb (striktní pravidla)

Při ladění jakékoliv chyby (lint, typecheck, build, test, runtime) platí:

1. **Žádné odhadování** — nezkoušet naslepo dvacet možností
2. **Nejprve dokumentace** — před pokusem o opravu najít oficiální dokumentaci:
   - `webfetch` / `web_search` pro oficiální docs knihovny/nástroje
   - `context7` MCP pro aktuální dokumentaci knihoven
   - GitHub Issues pro známé bugy a řešení
   - Teprve s pochopením root cause navrhnout řešení
3. **Logování do souboru** — pokud chyba není zřejmá z výstupu:
   - Vytvoř `.debug.log` soubor v `storage/logs/` (backend) nebo `frontend/.debug.log`
   - Zapisuj logy příkazů, výstupy, chybové hlášky
   - Soubor musí být přístupný pro čtení přes `read` nástroj
   - Po vyřešení smazat `.debug.log` soubor
4. **Systematický přístup**:
   - Reprodukovat chybu
   - Přečíst chybovou zprávu pozorně (celou, ne jen první řádek)
   - Najít dokumentaci pro daný nástroj/knihovnu
   - Identifikovat root cause
   - Aplikovat jedno řešení (ne zkoušet více najednou)
   - Ověřit, že řešení funguje
5. **Zakázáno**:
   - `--force`, `--no-verify`, obcházení kontrol
   - Naslepo měnit konfiguraci
   - Zkoušet řešení bez pochopení příčiny

## 9. Rizika a mitigace

| Riziko | Pravděpodobnost | Dopad | Mitigace |
|--------|----------------|-------|----------|
| {riziko} | {nízká/střední/vysoká} | {nízký/střední/vysoký} | {jak se mu vyhnout} |

## 10. Rollback strategie

{Co se stane, když implementace selhne v půlce. Jak vrátit změny.}

- **Kód**: {jak vrátit kód — git revert, git reset, feature branch smazání}
- **DB migrace**: {jak rollbackovat migraci — `php bin/migrate down`, obnova ze zálohy}
- **DB záloha**: {kde je záloha vytvořená v kroku 0, jak ji obnovit}
- **Dependencies**: {jak odstranit nově přidané balíčky pokud nejsou potřeba}

## 11. Cross-module impact

{Jak změna ovlivní ostatní moduly. Jaké eventy se emitují a kdo je poslouchá. Jaké sdílené služby se mění.}

| Modul | Dopad | Míra | Poznámka |
|-------|-------|------|----------|
| {název modulu} | {jak je ovlivněn} | {žádná/nízká/střední/vysoká} | {co je potřeba zkontrolovat} |

- **EventDispatcher eventy**: {jaké eventy se emitují, kdo je poslouchá}
- **Sdílené služby**: {jaké služby v `Core/` nebo `Shared/` se mění, kdo je používají}
- **DB tabulky**: {jaké tabulky se mění, kdo k nim přistupuje}

## 12. Poznámky

{Další kontext, odkazy na dokumentaci, decision record, atd.}

## 13. Post-implementační kontrola

- [ ] **Code review** — projdi implementaci podle workflow `.windsurf/workflows/code-review.md` (bezpečnost, rychlost, modularita)
- [ ] **DB verifikace** — pokud se měnila DB, ověř přes `mariadb` MCP (`list_tables`, `describe_table`, `SHOW INDEX`, `SHOW CREATE TABLE`)
- [ ] **Vizuální kontrola** — pokud se měnil frontend, ověř přes `mcp-playwright` (`browser_navigate`, `browser_take_screenshot`, `browser_console_messages`)
- [ ] **Cross-module impact** — ověř že změna neovlivní ostatní moduly (EventDispatcher eventy, sdílené služby v `Core/` nebo `Shared/`, DB tabulky)
- [ ] **Environment variables** — pokud se přidaly nové `.env` proměnné, aktualizuj `backend/.env.example` a `docs/12-environment-variables.md`
- [ ] **Dependency audit** — pokud se přidaly nové balíčky:
  - Backend: `composer audit` — bez známých vulnerabilit
  - Frontend: `npm audit` — bez známých vulnerabilit
  - Verze musí být publikována alespoň 7 dní (nové verze nejsou otestované, riziko supply chain attack)
- [ ] **Dokumentace** — aktualizuj `docs/` pokud je potřeba (DB schéma, API specifikace, modul specifikace)
- [ ] **Plán dokončen** — všechny checkboxy v sekci "Kroky implementace" a "Validace" odkrokovány, status `completed`

## 14. GitHub a changelog

- [ ] **Secrets scanning** — před commit zkontroluj že se v git staged souborech nenacházejí secrets:
  - Žádné API klíče, hesla, tokens, `.env` soubory
  - Žádné `APP_KEY`, `DB_PASSWORD`, application passwords
  - Pokud si nejsi jistý, projdi `git diff --cached` a hledej patterny jako `password=`, `key=`, `secret=`, `token=`
- [ ] **Commit** — vytvoř commit s konvenčním formátem (Conventional Commits: `feat:`, `fix:`, `refactor:`, `docs:`, `chore:`, `security:`)
- [ ] **Push** — pushni na `origin/main` (nebo feature branch pokud je definována)
- [ ] **Changelog** — podle workflow `.windsurf/workflows/changelog.md`:
  - Aktualizuj `CHANGELOG.md` (anglicky, commitnuto do gitu)
  - Aktualizuj `CHANGELOG_CS.md` (česky, gitignored, lokální)
  - Verze podle Semantic Versioning (MAJOR/MINOR/PATCH)
  - Datum v evropském formátu DD.MM.YYYY
- [ ] **Commit changelog** — commitni pouze `CHANGELOG.md` (`docs: update changelog for version X.Y.Z`)
- [ ] **Push** — pushni changelog na `origin/main`

---

## Changelog plánu

| Datum | Změna | Autor |
|-------|-------|-------|
| {DD.MM.YYYY} | {popis změny} | {autor} |
