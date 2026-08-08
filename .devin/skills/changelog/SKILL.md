---
name: changelog
description: Vytvoření nebo aktualizace changelogu v české a anglické verzi podle Keep a Changelog konvence.
triggers:
  - user
---

# Changelog

## Kontext
Tento workflow vytvoří nebo aktualizuje changelog ve dvou jazykových verzích:
- **Anglická verze**: `CHANGELOG.md` v root repozitáře (commitnuto do gitu)
- **Česká verze**: `CHANGELOG_CS.md` v root repozitáře (gitignored, lokální pouze)

Datum je v evropském formátu **DD.MM.YYYY**.

## Kroky

1. **Načti existující changelogy** — přečti `CHANGELOG.md` a `CHANGELOG_CS.md` (pokud existují).

2. **Zjisti změny od posledního záznamu** — použij `git log` od poslední verze/tagu:
   - `git log --oneline --since="<datum posledního záznamu>"` nebo `git log --oneline <last-tag>..HEAD`
   - Filtuj podle conventional commit typů (`feat:`, `fix:`, `refactor:`, `docs:`, `chore:`, `security:`)

3. **Urči typ verze** podle změn:
   - **MAJOR** — breaking changes (označeno `!` nebo `BREAKING CHANGE:` v commit message)
   - **MINOR** — nové funkce (`feat:`)
   - **PATCH** — bug fixy, refactoring, docs, chore (`fix:`, `refactor:`, `docs:`, `chore:`)

4. **Vytvoř záznam v anglické verzi** (`CHANGELOG.md`):
   - Formát data: DD.MM.YYYY
   - Struktura:
     ```markdown
     ## [X.Y.Z] — DD.MM.YYYY

     ### Added
     - New feature description

     ### Changed
     - What was modified

     ### Fixed
     - Bug fixes

     ### Security
     - Security-related changes

     ### Breaking
     - Breaking changes (if any)
     ```

5. **Vytvoř záznam v české verzi** (`CHANGELOG_CS.md`):
   - Stejný formát data: DD.MM.YYYY
   - Struktura:
     ```markdown
     ## [X.Y.Z] — DD.MM.YYYY

     ### Nové
     - Popis nové funkce

     ### Změněno
     - Co bylo upraveno

     ### Opraveno
     - Opravy chyb

     ### Bezpečnost
     - Změny související s bezpečností

     ### Breaking
     - Breaking changes (pokud nějaké)
     ```

6. **Zapiš soubory:**
   - `CHANGELOG.md` — anglická verze (bude commitnuta) — uprav soubor
   - `CHANGELOG_CS.md` — česká verze (gitignored) — uprav soubor přímo (soubor je v `.gitignore`, nebude commitnut)

7. **Commit pouze anglickou verzi**:
   - `git add CHANGELOG.md`
   - `git commit -m "docs: update changelog for version X.Y.Z"`
   - Česká verze zůstává lokální (je v `.gitignore`)

## Poznámky
- Datum vždy v evropském formátu DD.MM.YYYY
- Česká verze je v `.gitignore` — nikdy ji necommituj
- Anglická verze je verzovaná v gitu
- Používej [Keep a Changelog](https://keepachangelog.com/) konvenci
- Verzování podle [Semantic Versioning](https://semver.org/)
