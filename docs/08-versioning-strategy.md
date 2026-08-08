# WP Monitor — Verzovací strategie

## 1. Sémantické verzování (SemVer)

```
MAJOR.MINOR.PATCH
  1     .  0  .  0
```

| Číslo | Kdy se zvyšuje | Příklad |
|-------|----------------|---------|
| **MAJOR** | Breaking changes, nekompatibilní API změny | `1.0.0 → 2.0.0` |
| **MINOR** | Nová funkce, zpětně kompatibilní | `1.0.0 → 1.1.0` |
| **PATCH** | Oprava chyby, zpětně kompatibilní | `1.0.0 → 1.0.1` |

**Pre-release verze:**
- `1.0.0-alpha.1` — raná fáze, nestabilní
- `1.0.0-beta.1` — feature complete, testování
- `1.0.0-rc.1` — release candidate, finální testování

**Tag formát:** `v{MAJOR}.{MINOR}.{PATCH}` (např. `v1.0.0`, `v1.2.3-rc.1`)

## 2. Branch model (Git Flow zjednodušený)

```
main (produkční)
├── v1.0.0 (tag)
├── v1.1.0 (tag)
│
develop (vývojová integrace)
├── feat/updates-batch-ui
├── feat/backup-s3-storage
├── fix/credential-decrypt-error
├── security/xss-in-site-detail
├── refactor/module-registry
│
release/v1.1.0 (příprava release — z develop)
│
hotfix/v1.0.1 (emergency fix — z main)
```

### 2.1 Branch typy a naming konvence

| Prefix | Účel | Vytvořeno z | Mergeno do | Životnost |
|--------|------|-------------|------------|-----------|
| `main` | Produkční kód, vždy deployable | — | — | trvalá |
| `develop` | Vývojová integrace, nejnovější feature kód | `main` (init) | `main` (při release) | trvalá |
| `feat/{ticket}-{description}` | Nová funkce | `develop` | `develop` | dočasná |
| `fix/{ticket}-{description}` | Oprava chyby (neprodukční) | `develop` | `develop` | dočasná |
| `security/{ticket}-{description}` | Bezpečnostní oprava | `develop` | `develop` + `main` (kritické) | dočasná |
| `refactor/{ticket}-{description}` | Refaktoring bez změny chování | `develop` | `develop` | dočasná |
| `perf/{ticket}-{description}` | Performance zlepšení | `develop` | `develop` | dočasná |
| `docs/{ticket}-{description}` | Dokumentace | `develop` | `develop` | dočasná |
| `test/{ticket}-{description}` | Testy | `develop` | `develop` | dočasná |
| `chore/{ticket}-{description}` | Údržba, deps, config | `develop` | `develop` | dočasná |
| `release/v{X.Y.Z}` | Příprava release | `develop` | `main` + `develop` | dočasná |
| `hotfix/v{X.Y.Z}` | Emergency fix na produkci | `main` | `main` + `develop` | dočasná |

### 2.2 Naming pravidla

- **kebab-case** pro názvy větví (`feat/updates-batch-ui`, ne `feat/UpdatesBatchUI`)
- **Ticket ID volitelné** — pokud používáme issues, prefix: `feat/UPD-12-batch-ui`
- **Max 50 znaků** pro název větve (bez prefixu)
- **Bez diakritiky** — pouze ASCII znaky
- **Krátký a popisný** — `fix/ssl-check-timeout` ne `fix/problem-with-ssl-checking-when-timeout-occurs`

### 2.3 Příklady

```
✅ feat/updates-batch-ui
✅ fix/ssl-check-timeout
✅ security/xss-site-detail
✅ refactor/module-registry
✅ release/v1.0.0
✅ hotfix/v1.0.1
✅ docs/api-specification

❌ feat/Updates Batch UI        (mezery, velká písmena)
❌ fix/oprava                      (ne popisné)
❌ feat/very-long-branch-name-that-exceeds-fifty-characters-limit-here (příliš dlouhé)
❌ update-branch                   (bez prefixu)
❌ feat/aktualizace             (diakritika)
```

## 3. Workflow podle typu změny

### 3.1 Nová funkce (feature)

```bash
# 1. Vytvoř větev z develop
git checkout develop
git pull origin develop
git checkout -b feat/updates-batch-ui

# 2. Vývoj — commity podle Conventional Commits
git commit -m "feat(updates): pridat batch update dialog"
git commit -m "feat(updates): pridat progress bar pro batch operace"
git commit -m "test(updates): pridat testy pro batch update"

# 3. Push a vytvoř PR do develop
git push -u origin feat/updates-batch-ui
# GitHub: vytvoř PR feat/updates-batch-ui → develop

# 4. Po review a CI: squash merge do develop
```

### 3.2 Oprava chyby (fix)

```bash
git checkout develop
git pull origin develop
git checkout -b fix/ssl-check-timeout

git commit -m "fix(security): opravit timeout v SSL check pro pomale weby"
git commit -m "test(security): pridat testy pro SSL check timeout"

git push -u origin fix/ssl-check-timeout
# PR: fix/ssl-check-timeout → develop
```

### 3.3 Bezpečnostní oprava (security)

```bash
# Kritické security fixy mohou jít přímo do main (přes hotfix)
# Nekritické jdou přes develop

# Nekritický:
git checkout develop
git checkout -b security/xss-site-detail
git commit -m "security(sites): opravit XSS v site detail page"
# PR: security/xss-site-detail → develop

# Kritický (hotfix):
git checkout main
git pull origin main
git checkout -b hotfix/v1.0.1
git commit -m "security(auth): kriticka oprava - bypass rate limiteru"
# PR: hotfix/v1.0.1 → main
# Po merge: backport do develop
git checkout develop
git merge hotfix/v1.0.1
git push origin develop
```

### 3.4 Release proces

```bash
# 1. Vytvoř release větev z develop
git checkout develop
git pull origin develop
git checkout -b release/v1.0.0

# 2. Finální úpravy (verze, changelog, docs)
git commit -m "chore(core): nastavit verzi na 1.0.0"
git commit -m "docs: pridat changelog pro v1.0.0"

# 3. PR do main
git push -u origin release/v1.0.1
# PR: release/v1.0.0 → main

# 4. Po merge do main: vytvoř tag
git checkout main
git pull origin main
git tag -a v1.0.0 -m "WP Monitor v1.0.0 — initial release"
git push origin v1.0.0

# 5. Backport do develop
git checkout develop
git merge release/v1.0.0
git push origin develop

# 6. Tag spustí Release workflow na GitHub Actions
```

### 3.5 Hotfix proces

```bash
# 1. Vytvoř hotfix větev z main
git checkout main
git pull origin main
git checkout -b hotfix/v1.0.1

# 2. Oprava
git commit -m "fix(api): opravit 500 error na /sites endpoint"

# 3. PR do main
git push -u origin hotfix/v1.0.1
# PR: hotfix/v1.0.1 → main

# 4. Po merge: tag
git checkout main
git pull origin main
git tag -a v1.0.1 -m "WP Monitor v1.0.1 — hotfix for sites API"
git push origin v1.0.1

# 5. Backport do develop
git checkout develop
git merge hotfix/v1.0.1
git push origin develop
```

## 4. Commit → Branch → Release mapování

| Commit type | Branch prefix | Mergeno do | Zvyšuje verzi |
|-------------|---------------|------------|---------------|
| `feat` | `feat/` | develop | MINOR (při release) |
| `fix` | `fix/` | develop | PATCH (při release) |
| `security` | `security/` nebo `hotfix/` | develop nebo main | PATCH nebo MINOR |
| `refactor` | `refactor/` | develop | — (zpětně kompatibilní) |
| `perf` | `perf/` | develop | PATCH |
| `docs` | `docs/` | develop | — |
| `test` | `test/` | develop | — |
| `chore` | `chore/` | develop | — |
| `BREAKING CHANGE` | `feat/` nebo `hotfix/` | develop nebo main | MAJOR |

## 5. Pravidla pro merge

| Z → Do | Metoda | Důvod |
|--------|--------|-------|
| `feat/*` → `develop` | **Squash merge** | Čistá historie, jeden commit per feature |
| `fix/*` → `develop` | **Squash merge** | Čistá historie |
| `security/*` → `develop` | **Squash merge** | Čistá historie |
| `release/*` → `main` | **Merge commit** | Zachovat release historii |
| `release/*` → `develop` | **Merge commit** | Backport changes |
| `hotfix/*` → `main` | **Merge commit** | Zachovat hotfix historii |
| `hotfix/*` → `develop` | **Merge commit** | Backport changes |

## 6. Changelog

WP Monitor udržuje changelog ve **dvou jazykových verzích**:

| Soubor | Jazyk | Git | Účel |
|--------|-------|-----|------|
| `CHANGELOG.md` | Angličtina | ✅ Commitnuto | Veřejná historie změn (GitHub, dokumentace) |
| `CHANGELOG_CS.md` | Čeština | ❌ Gitignored | Lokální reference pro vývojáře |

### 6.1 Formát

- Verzování podle [Semantic Versioning](https://semver.org/) (`MAJOR.MINOR.PATCH`)
- Datum v evropském formátu **DD.MM.YYYY**
- Struktura podle [Keep a Changelog](https://keepachangelog.com/)
- Záznamy seřazeny od nejnovější verze nahoru

### 6.2 Struktura — anglická verze (`CHANGELOG.md`)

```markdown
# Changelog

All notable changes to WP Monitor are documented in this file.
The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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

## [1.0.0] — 08.08.2026

### Added
- Initial release with auth, sites, dashboard, updates, backups, security, SEO modules
```

### 6.3 Struktura — česká verze (`CHANGELOG_CS.md`)

```markdown
# Changelog

Všechny významné změny ve WP Monitor jsou dokumentovány v tomto souboru.
Formát je založen na [Keep a Changelog](https://keepachangelog.com/cs/1.1.0/),
a tento projekt dodržuje [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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

## [1.0.0] — 08.08.2026

### Nové
- První verze s moduly auth, sites, dashboard, updates, backups, security, SEO
```

### 6.4 Sekce a jejich význam

| Sekce (EN) | Sekce (CS) | Kdy použít |
|------------|------------|------------|
| `Added` | `Nové` | Nové funkce (`feat:`) |
| `Changed` | `Změněno` | Změny existující funkcionality (`refactor:`, `perf:`) |
| `Fixed` | `Opraveno` | Opravy chyb (`fix:`) |
| `Security` | `Bezpečnost` | Bezpečnostní změny (`security:`) |
| `Breaking` | `Breaking` | Breaking changes (`!` nebo `BREAKING CHANGE:`) |
| `Deprecated` | `Zastaralé` | Funkce, které budou odstraněny |
| `Removed` | `Odstraněno` | Odstraněné funkce |

### 6.5 Určení typu verze

| Typ změny | Zvýšení verze | Commit type |
|-----------|---------------|-------------|
| Breaking change | MAJOR | `feat!:` nebo `BREAKING CHANGE:` v commit body |
| Nová funkce | MINOR | `feat:` |
| Oprava chyby | PATCH | `fix:` |
| Bezpečnostní oprava | PATCH nebo MINOR | `security:` |
| Refaktoring | — (zpětně kompatibilní) | `refactor:` |
| Dokumentace | — | `docs:` |
| Údržba | — | `chore:` |

### 6.6 Postup tvorby changelogu

1. **Načti existující changelogy** — přečti `CHANGELOG.md` a `CHANGELOG_CS.md`
2. **Zjisti změny** — `git log --oneline <last-tag>..HEAD` nebo `git log --oneline --since="<datum>"`
3. **Filtruj podle commit type** — `feat:`, `fix:`, `security:`, `refactor:`, `docs:`, `chore:`
4. **Urči typ verze** — MAJOR / MINOR / PATCH podle tabulky výše
5. **Vytvoř záznam v anglické verzi** — `CHANGELOG.md` s anglickým popisem
6. **Vytvoř záznam v české verzi** — `CHANGELOG_CS.md` s českým popisem
7. **Commit pouze anglickou verzi** — `git add CHANGELOG.md && git commit -m "docs: update changelog for version X.Y.Z"`
8. **Česká verze zůstává lokální** — je v `.gitignore`, nikdy ji necommituj

### 6.7 Pravidla

- **Datum vždy v evropském formátu** DD.MM.YYYY (např. `08.08.2026`, ne `2026-08-08`)
- **Česká verze je v `.gitignore`** — nikdy ji necommituj
- **Anglická verze je verzovaná v gitu** — commituj ji s každou verzí
- **Používej [Unreleased] sekci** — pro dosud nevydané změny
- **Odkazuj na issue/PR čísla** — `(#12)` na konci položky
- **Popisuj co, ne jak** — `hromadné aktualizace s progress barem` ne `přidán BatchUpdateService.php s ProgressBar komponentou`
- **Jedna položka = jeden commit** — nespojuj více commitů do jedné položky (kromě souvisejících)

### 6.8 GitHub Releases

Kromě `CHANGELOG.md` se changelog generuje i automaticky do GitHub Releases (pomocí `generate_release_notes: true` v release workflow). GitHub Release obsahuje:

```markdown
## v1.1.0 — 15.08.2026

### Features
- feat(updates): hromadné aktualizace s progress barem (#12)
- feat(backups): S3 storage backend (#15)

### Fixes
- fix(security): SSL check timeout pro pomalé weby (#18)
- fix(api): chybějící CSRF validace na /sites import (#20)

### Security
- security(auth): zpevnění rate limiteru (#22)

### Breaking Changes
- feat(api): změna response formátu pro /dashboard/overview (#10)
```

`CHANGELOG.md` je primární zdroj — GitHub Release je automatický doplněk.
