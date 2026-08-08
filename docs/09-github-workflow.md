# WP Monitor — GitHub Workflow (průvodce)

> Tento dokument popisuje kompletní GitHub workflow srozumitelně — od nápadu po produkci.
> Technické detaily verzování viz `docs/08-versioning-strategy.md`.

## 1. Větve (branches) = oddělení v továrně

Máme dvě trvalé větve:

- **`main`** — **výloha obchodu**. Tady je jen hotový, otestovaný produkt, který běží u zákazníků. Nic se tu nevyvíjí přímo.
- **`develop`** — **vývojová dílna**. Tady se spojuje práce všech programátorů do jednoho celku. Je to "předváděčka" — všechno funguje, ale ještě to není pro zákazníky.

A pak máme **pracovní stoly** (dočasné větve), kde se reálně pracuje:

| Pracovní stůl | Co se na něm dělá | Kam jde výsledek |
|---|---|---|
| `feature/neco` | Nová funkce (např. tlačítko zálohování) | → do `develop` |
| `fix/neco` | Oprava chyby (např. nefunguje filtr) | → do `develop` |
| `security/neco` | Bezpečnostní záplata | → do `develop` |
| `refactor/neco` | Úklid kódu (funguje stejně, ale lépe se čte) | → do `develop` |
| `perf/neco` | Zrychlení kódu | → do `develop` |
| `docs/neco` | Dokumentace | → do `develop` |
| `test/neco` | Testy | → do `develop` |
| `chore/neco` | Údržba, závislosti, konfigurace | → do `develop` |
| `release/v1.0.0` | Příprava vydání — finální kontroly | → do `main` |
| `hotfix/v1.0.1` | Nouzová oprava na produkci (hoří!) | → do `main` |

## 2. Průběh práce — od nápadu po produkci

```
1. NÁPAD
   "Chci přidat hromadné zálohování"
        ↓
2. VYTVOŘÍŠ VĚTEV
   git checkout develop
   git pull origin develop
   git checkout -b feature/backup-batch
   (otevřeš si vlastní pracovní stůl, nikomu nerušíš)
        ↓
3. PÍŠEŠ KÓD
   Děláš commity: "feat(backups): pridat batch dialog"
   (každý commit má popisný štítek — Conventional Commits)
        ↓
4. ODEŠLEŠ NA GITHUB (Push)
   git push -u origin feature/backup-batch
   (tvůj kód se objeví na GitHubu)
        ↓
5. VYTVORÍŠ PULL REQUEST (PR)
   "Chci to z feature/backup-batch dostat do develop"
   (oficiální žádost o zařazení tvé práce)
   (PR template se automaticky vloží s kontrolním seznamem)
        ↓
6. AUTOMATICKÉ KONTROLY (GitHub Actions)
   ⚙️ Spustí se 14 kontrol automaticky:
   ┌─────────────────────────────────────────────┐
   │  📝 Zkontroluje název větve                  │
   │     (musí být feature/backup-batch)          │
   │  📝 Zkontroluje že jsi mířil na develop      │
   │  📝 Zkontroluje titulek PR                   │
   │     (musí být "feat(backups): ...")          │
   │  🏷️  Automaticky přidá štítek "feature"      │
   │                                              │
   │  🔍 PHP kód je čistý (PSR-12)               │
   │  🔍 PHPStan: žádné chyby v typech           │
   │  🔍 PHPUnit: testy prošly                   │
   │  🔍 ESLint: TypeScript bez chyb             │
   │  🔍 TypeScript: typy jsou správně           │
   │  🔍 Vitest: frontend testy prošly           │
   │  🔍 Build: frontend se zkompiloval          │
   │  🔍 Composer audit: žádné zranitelnosti     │
   │  🔍 CodeQL: bezpečnostní scan kódu          │
   │  🔍 Commit zprávy: správný formát            │
   └─────────────────────────────────────────────┘
        ↓
7. CODE REVIEW
   Někdo (nebo ty) projde kód a schválí ho
   (pro bezpečnostní soubory je review povinný — CODEOWNERS)
        ↓
8. MERGE
   Kód se spojí s develop (squash — všechny tvé
   commity se spojí do jednoho čistého)
        ↓
9. KDYŽ JE ČAS NA VYDÁNÍ...
   Vytvoříš release/v1.0.0 z develop
   → PR do main
   → Po merge: vytvoříš tag v1.0.0
   → GitHub Actions automaticky:
     a) Postaví backend (bez testovacích nástrojů)
     b) Postaví frontend (optimalizovaný)
     c) Vytvoří .zip archivy
     d) Vytvoří GitHub Release s changelogem
   → Hotovo — produkt je ve výloze
```

## 3. Nouzová oprava (hotfix) — když hoří

```
Na produkci (main) je chyba — nefunguje přihlášení!

1. Vytvoříš hotfix/v1.0.1 z main (ne z develop!)
   git checkout main
   git pull origin main
   git checkout -b hotfix/v1.0.1

2. Opravíš kód
   git commit -m "fix(auth): opravit nefunkcni prihlaseni"

3. PR do main (ne do develop!)
   GitHub Actions zkontroluje všechno

4. Merge do main

5. Vytvoříš tag v1.0.1
   git tag -a v1.0.1 -m "WP Monitor v1.0.1 — hotfix"
   git push origin v1.0.1
   → Automaticky se buildí a publikuje Release

6. Zpětně zkopíruješ opravu i do develop
   git checkout develop
   git merge hotfix/v1.0.1
   git push origin develop
   (ať se chyba nevrátí v příštím release)
```

## 4. Verzování — štítky na krabici

```
v1.0.0  →  v1.0.1  →  v1.0.2  →  v1.1.0  →  v1.1.1  →  v2.0.0
│         │         │         │         │         │
│         │         │         │         │         └─ Breaking change (něco přestalo fungovat)
│         │         │         │         └─ Oprava chyby
│         │         │         └─ Nová funkce (zpětně kompatibilní)
│         │         └─ Oprava chyby
│         └─ Oprava chyby
└─ První stabilní verze
```

**Pre-release verze:**
- `v1.0.0-alpha.1` — raná fáze, nestabilní
- `v1.0.0-beta.1` — feature complete, testování
- `v1.0.0-rc.1` — release candidate, finální testování

## 5. Týdenní úklid (code-quality.yml)

Každé pondělí ráno (2:00 UTC) se automaticky spustí:

| Kontrola | Co dělá |
|----------|---------|
| **Rector** (dry-run) | "Může se ten kód napsat moderněji?" (nic nemění, jen hlásí) |
| **Bundle analysis** | "Není frontend moc velký?" (kontrola velikosti buildu) |
| **Circular deps** | "Neodkazují soubory na sebe navzájem?" (cyklické závislosti) |
| **Dependency review** | "Nejsou v knihovnách nové zranitelnosti?" |

Lze spustit i manuálně (workflow_dispatch).

## 6. Štítky (labels) na GitHubu

| Štítek | Barva | Co znamená |
|--------|-------|------------|
| `feature` | zelená | Nová funkce |
| `bug` | červená | Oprava chyby |
| `security` | oranžová | Bezpečnostní oprava — P0 |
| `refactor` | modrá | Refaktoring bez změny chování |
| `performance` | fialová | Performance zlepšení |
| `documentation` | modrá | Dokumentace |
| `testing` | žlutá | Testy |
| `maintenance` | světle modrá | Údržba, deps, config |
| `release` | tmavě zelená | Release příprava |
| `hotfix` | oranžová | Emergency fix na produkci |
| `P0-critical` | červená | Blokuje release |
| `P1-high` | oranžová | Důležité, ale neblokuje |
| `P2-medium` | žlutá | Střední priorita |
| `P3-low` | zelená | Nízká priorita |
| `breaking-change` | červená | Změna, co rozbije kompatibilitu |
| `needs-review` | žlutá | Čeká na code review |
| `approved` | zelená | Schváleno, připraveno k merge |
| `wip` | světle modrá | Work in progress |
| `blocked` | červená | Blokováno — čeká na závislost |
| `wontfix` | bílá | Nebude opraveno |

Štítky se přidělují **automaticky** podle názvu větve — `feature/*` dostane štítek `feature`, `fix/*` dostane `bug`, `security/*` dostane `security`, atd.

## 7. GitHub Actions workflows — přehled

| Workflow | Soubor | Kdy se spustí | Co dělá |
|----------|--------|---------------|---------|
| **CI** | `ci.yml` | push (main/develop), PR (main/develop) | 14 kontrol: backend lint/static/tests, frontend lint/typecheck/tests/build, security audit + CodeQL, commitlint |
| **Branch Rules** | `branch-rules.yml` | PR (otevření, úprava, reopen) | 4 kontroly: branch naming, PR target, PR title, auto-label |
| **Release** | `release.yml` | tag `v*.*.*` | Backend + frontend build, .zip archivy, GitHub Release s changelogem |
| **Code Quality** | `code-quality.yml` | cron (pondělí 2:00 UTC), manuálně | Rector dry-run, bundle analysis, circular deps, dependency review |

## 8. Branch protection (co je zakázáno)

Na GitHubu (Settings → Branches) je nastaveno:

### `main` (produkční)
- **Žádný direct push** — vše musí přes PR
- **Min. 1 approval** — kód musí schválit
- **CODEOWNERS review** — pro security soubory povinné
- **14 CI kontrol musí projít** — všechny, žádná nesmí fail
- **Conventional Commits** — PR title musí mít správný formát
- **Linear history** — squash nebo rebase merge (ne merge bubble)
- **Force push zakázán** — historii nelze přepsat
- **Smazání zakázáno**

### `develop` (vývojová)
- **Žádný direct push** — vše musí přes PR
- **Min. 1 approval**
- **CI kontroly musí projít** (backend + frontend)
- **Force push zakázán**
- **Smazání zakázáno**

## 9. Shrnutí jednou větou

**Nic nejde přímo do `main` — všechno musí projít `develop`, přes PR s 14 automatickými kontrolami a code review, a jen release/hotfix větve se dostanou do `main` s verzovým tagem.**
