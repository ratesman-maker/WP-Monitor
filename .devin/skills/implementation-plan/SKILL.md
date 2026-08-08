---
name: implementation-plan
description: Vytvoří implementační plán pro novou funkci, modul, refaktor nebo bugfix podle šablony v docs/implementation-plan-template.md
argument-hint: "[název funkce/modulu]"
triggers:
  - user
  - model
---

# Skill: Vytvoření implementačního plánu

## Kdy použít

Tento skill se používá PŘED začátkem implementace jakékoliv nové funkce, modulu, refaktoru nebo bugfixu.
Cílem je zdokumentovat plán vývoje, ověřit reálný stav a zajistit dodržení priorit (bezpečnost, rychlost, modularita).

## Postup

### 1. Načti šablonu

Přečti `docs/implementation-plan-template.md` — z této šablony vycházej všechny implementační plány.

### 2. Zjisti reálný stav

Před vyplněním plánu ZJISTI REÁLNÝ STAV — nespoléhej na dokumentaci:
- **Backend**: projdi `backend/src/` — jaké třídy, služby, moduly skutečně existují
- **Frontend**: projdi `frontend/src/` — jaké komponenty, stránky, store existují
- **Databáze**: použij `mcp_server_mysql` MCP server (nástroj `mysql_query` pro `SHOW TABLES`, `DESCRIBE`, `SHOW CREATE TABLE`) nebo ověř přes `mariadb` CLI
- **Dokumentace**: přečti relevantní docs/ soubory jako referenci, ale dokumentace nemusí být aktuální
- **Reálný stav kódu a databáze má prioritu nad dokumentací**

### 3. Vyplň plán podle šablony

Vytvoř soubor v `docs/implementation-plans/{YYYY-MM-DD}-{název}.md` s vyplněnými sekcemi:

1. **Metadata** — název, typ, priorita, status, datum, autor, související workflow
2. **Cíl** — co má být výsledkem
3. **Reálný stav** — co už existuje (backend, frontend, DB), rozdíl oproti dokumentaci
4. **Priority (bez výjimek)** — konkrétní bezpečnost, rychlost, modularita opatření
5. **Architektura** — backend struktura, frontend struktura, DB změny
6. **API endpointy** — tabulka endpointů (pokud relevantní)
7. **Kroky implementace** — fáze a checklist kroků
8. **Testy** — jaké testy se napíší
9. **Validace** — checklist (composer test, analyse, npm run test/lint/typecheck, mysql MCP ověření)
10. **Rizika a mitigace** — co se může pokazit a jak se tomu vyhnout
11. **Rollback strategie** — jak vrátit změny pokud implementace selže (kód, DB migrace, záloha)
12. **Cross-module impact** — jak změna ovlivní ostatní moduly (EventDispatcher eventy, sdílené služby, DB tabulky)
13. **Poznámky** — dodatečný kontext

### 4. Získej schválení

Po vytvoření plánu prezentuj ho uživateli a počkej na schválení před začátkem implementace.
Status v metadatech by měl být `draft` → po schválení `approved` → při implementaci `in-progress` → po dokončení `completed`.

### 5. Aktualizuj plán během implementace

- Při začátku implementace změň status na `in-progress`
- Při dokončení změň status na `completed`
- Pokud se plán mění během implementace, přidej záznam do sekce "Changelog plánu"
- Odkrojuj checkboxy v sekci "Kroky implementace" a "Validace"

### 6. Post-implementační kontrola

Po dokončení implementace a úspěšné validaci proveď post-implementační kontrolu:

- [ ] **Code review** — projdi implementaci podle skillu `code-review` (bezpečnost, rychlost, modularita)
- [ ] **DB verifikace** — pokud se měnila DB, ověř přes `mcp_server_mysql` (`mysql_query` s `SHOW TABLES`, `DESCRIBE`, `SHOW INDEX`, `SHOW CREATE TABLE`)
- [ ] **Cross-module impact** — ověř že změna neovlivní ostatní moduly (EventDispatcher eventy, sdílené služby v `Core/` nebo `Shared/`, DB tabulky)
- [ ] **Environment variables** — pokud se přidaly nové `.env` proměnné, aktualizuj `backend/.env.example` a `docs/12-environment-variables.md`
- [ ] **Dependency audit** — pokud se přidaly nové balíčky:
  - Backend: `composer audit` — bez známých vulnerabilit
  - Frontend: `npm audit` — bez známých vulnerabilit
  - Verze musí být publikována alespoň 7 dní (nové verze nejsou otestované, riziko supply chain attack)
- [ ] **Dokumentace** — aktualizuj `docs/` pokud je potřeba (DB schéma, API specifikace, modul specifikace)
- [ ] **Plán dokončen** — všechny checkboxy v sekci "Kroky implementace" a "Validace" odkrokovány, status `completed`

### 7. GitHub a changelog

Po úspěšné post-implementační kontrole:

- [ ] **Secrets scanning** — před commit zkontroluj že se v git staged souborech nenacházejí secrets:
  - Žádné API klíče, hesla, tokens, `.env` soubory
  - Žádné `APP_KEY`, `DB_PASSWORD`, application passwords
  - Pokud si nejsi jistý, projdi `git diff --cached` a hledej patterny jako `password=`, `key=`, `secret=`, `token=`
- [ ] **Commit** — vytvoř commit s konvenčním formátem (Conventional Commits):
  - `feat(auth): add login and JWT token issuance`
  - `fix(sites): resolve duplicate URL validation`
  - `refactor(core): extract CryptoService to Core/`
- [ ] **Push** — pushni na `origin/main` (nebo feature branch pokud je definována)
- [ ] **Changelog** — podle skillu `changelog`:
  - Aktualizuj `CHANGELOG.md` (anglicky, commitnuto do gitu)
  - Aktualizuj `CHANGELOG_CS.md` (česky, gitignored, lokální)
  - Verze podle Semantic Versioning (MAJOR/MINOR/PATCH)
  - Datum v evropském formátu DD.MM.YYYY
- [ ] **Commit changelog** — commitni pouze `CHANGELOG.md` (`docs: update changelog for version X.Y.Z`)
- [ ] **Push** — pushni changelog na `origin/main`

## Pravidla

- **Vždy** vytvoř plán před implementací — bez výjimky
- **Vždy** ověř reálný stav — nespoléhej na dokumentaci
- **Vždy** dodrž priority: bezpečnost > rychlost > modularita — bez výjimek
- **Nikdy** nezačínej implementaci bez schváleného plánu (status `approved`)
- Plán je živý dokument — aktualizuj ho během implementace
- Jeden plán = jeden soubor v `docs/implementation-plans/`
- Název souboru: `{YYYY-MM-DD}-{název}.md` (např. `2026-08-07-auth-module.md`)

## Související

- Šablona: `docs/implementation-plan-template.md`
- Vygenerované plány: `docs/implementation-plans/`
- Skills: `.devin/skills/` — každý skill (code-review, db-migration, build-deploy, changelog) má krok pro vytvoření implementačního plánu
