# WP Monitor — Architecture Decision Records (ADR)

> Záznamy architektonických rozhodnutí. Každé ADR popisuje kontext, rozhodnutí, důsledky a alternativy.

## Formát ADR

```
## ADR-XXX: Název rozhodnutí

**Datum:** YYYY-MM-DD
**Status:** Proposed | Accepted | Deprecated | Superseded by ADR-YYY
**Týká se:** Backend | Frontend | DB | Security | Infrastructure

### Kontext
Proč je potřeba rozhodnout?

### Rozhodnutí
Co jsme rozhodli?

### Důvody
Proč jsme zvolili tuto variantu?

### Důsledky
Co to přináší (pozitivní i negativní)?

### Alternativy
Co dalšího jsme zvažovali a proč zamítli?
```

---

## ADR-001: PHP Slim 4 místo Laravel/Symfony

**Datum:** 2026-08-01
**Status:** Accepted
**Týká se:** Backend

### Kontext
Potřebujeme backend, který je rychlý, modulární a má minimální overhead. Projekt je lokální/admin nástroj, ne veřejná aplikace s tisíci uživateli.

### Rozhodnutí
Použít **Slim 4** jako HTTP framework.

### Důvody
- Minimální overhead — Slim je micro-framework, načítá jen co potřebuje
- Plná kontrola nad architekturou — žádné "magické" skryté chování
- PSR-7/PSR-15 kompatibilní — standardní middleware pipeline
- Snadná modularita — každý modul registruje vlastní routes
- Nízká learning curve — kód je explicitní a čitelný

### Důsledky
- **+** Rychlost, plná kontrola, prediktabilní chování
- **−** Více boilerplate kódu než Laravel (migrace, ORM, queue)
- **−** Manuální integrace některých komponent (Doctrine DBAL místo Eloquent)

### Alternativy
- **Laravel** — příliš "magický", skryté chování, heavy pro admin nástroj
- **Symfony** — robustní, ale vyšší overhead a složitost než Slim
- **Lumen** — deprecated, Slim je lepší micro-framework

---

## ADR-002: Doctrine DBAL místo ORM

**Datum:** 2026-08-01
**Status:** Accepted
**Týká se:** Backend, DB

### Kontext
Potřebujeme databázovou vrstvu. Projekt má 25+ tabulek s komplexními vztahy, ale nechceme ORM overhead ani "magické" lazy-loading.

### Rozhodnutí
Použít **Doctrine DBAL** (Database Abstraction Layer) bez ORM.

### Důvody
- Plná kontrola nad SQL dotazy — žádné neočekávané N+1 problémy
- Type-safe Query Builder pro dynamické dotazy
- Schema Manager pro migrace
- Nižší overhead než plné ORM
- Explicitní — víš přesně co se děje v DB

### Důsledky
- **+** Prediktabilní performance, plná kontrola, žádné N+1
- **−** Více manuálního mapování (row → entity)
- **−** Bez automatických vztahů (ManyToOne, OneToMany)

### Alternativy
- **Doctrine ORM** — heavy, lazy-loading problémy, magické chování
- **Eloquent** — active record pattern, méně vhodný pro komplexní schéma
- **PDO raw** — příliš nízkoúrovňové, bez query builderu

---

## ADR-003: AES-256-GCM přes libsodium

**Datum:** 2026-08-01
**Status:** Accepted
**Týká se:** Security

### Kontext
Potřebujeme šifrovat citlivá data (WP credentials, API klíče, zálohy). Šifrování musí být vojensky bezpečné a kompatibilní s PHP 8.3+.

### Rozhodnutí
Použít **AES-256-GCM** přes **libsodium** (`sodium_crypto_aead_aes256gcm_*`).

### Důvody
- Authenticated encryption — šifrování + integrita v jednom kroku
- AES-256-GCM je standard (NIST, FIPS 140-2)
- libsodium je konstantní čas — odolné proti timing attackům
- AAD (Additional Authenticated Data) — vazba šifrovaných dat na kontext
- PHP 8.3+ má libsodium zabudované (žádné extra rozšíření)

### Důsledky
- **+** Vojensky bezpečné, authenticated, odolné proti timing attackům
- **−** Vyžaduje `sodium` rozšíření (standardní v PHP 7.2+)
- **−** Nelze použít na starém PHP (< 7.2)

### Alternativy
- **OpenSSL `openssl_encrypt`** — není konstantní čas, zranitelné vůči timing
- **AES-CBC** — není authenticated, zranitelné vůči padding oracle
- **ChaCha20-Poly1305** — alternativní, ale AES-256-GCM je více standardní

---

## ADR-004: Argon2id pro key derivation

**Datum:** 2026-08-01
**Status:** Accepted
**Týká se:** Security

### Kontext
Potřebujeme odvozovat šifrovací klíče z uživatelských hesel. Algoritmus musí být odolný vůči brute-force a GPU attackům.

### Rozhodnutí
Použít **Argon2id** (`SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13`).

### Důvody
- OWASP doporučuje Argon2id jako primární volbu
- Odolné vůči GPU a ASIC attackům (memory-hard)
- Kombinuje Argon2i (side-channel resistant) a Argon2d (GPU resistant)
- Nativní podpora v libsodium
- Konfigurovatelné parametry (memory, time, threads)

### Důsledky
- **+** Nejlepší dostupná ochrana proti brute-force
- **−** Vyšší memory/time cost = pomalejší login (~100-300ms)
- **−** Vyžaduje dostatek RAM na serveru (64MB+ per request)

### Alternativy
- **PBKDF2** — starší, méně odolný vůči GPU
- **bcrypt** — good, ale ne memory-hard
- **scrypt** — good, ale méně standardizovaný

---

## ADR-005: React + TypeScript + Vite

**Datum:** 2026-08-01
**Status:** Accepted
**Týká se:** Frontend

### Kontext
Potřebujeme frontend framework pro admin dashboard s tabulkami, grafy, formuláři a real-time aktualizacemi.

### Rozhodnutí
Použít **React 18 + TypeScript 5 + Vite 5**.

### Důvody
- React — největší ekosystém, nejvíce knihoven, shadcn/ui
- TypeScript — type safety, IntelliSense, odhalení chyb při vývoji
- Vite — extrémně rychlý dev server (ESM-based), optimální build
- shadcn/ui + shadcnblocks.com — plně kompatibilní s React + TypeScript

### Důsledky
- **+** Rychlý dev cycle, type safety, obrovský ekosystém
- **−** React vyžaduje více boilerplate než Svelte
- **−** TypeScript má learning curve

### Alternativy
- **Vue 3** — dobrý, ale menší ekosystém pro admin UI
- **Svelte/SvelteKit** — nejrychlejší runtime, ale menší ekosystém
- **Angular** — příliš heavy pro admin nástroj, strmná learning curve

---

## ADR-006: shadcnblocks.com jako UI blok knihovna

**Datum:** 2026-08-07
**Status:** Accepted
**Týká se:** Frontend

### Kontext
Potřebujeme rychle postavit profesionální admin UI bez nutnosti designovat každý komponent od nuly. Máme premium licenci na shadcnblocks.com.

### Rozhodnutí
Použít **shadcnblocks.com** (premium) pro UI bloky + **shadcn/ui** pro base komponenty.

### Důvody
- 1816+ bloků pokrývajících všechny potřebné UI patterny (Dashboard, Data Table, Charts, Bento)
- Bloky se kopírují do projektu — plná kontrola, žádná runtime závislost
- Postaveno na shadcn/ui + TailwindCSS — konzistentní s naším stackem
- Premium licence — legální použití v komerčním projektu
- IDE Extension + CLI — rychlá instalace přímo z editoru

### Důsledky
- **+** Rychlý vývoj UI, profesionální vzhled, plná kontrola nad kódem
- **+** Konzistence — všechny moduly používají stejné bloky
- **−** Bloky je třeba udržovat při upstream změnách (diff a merge)
- **−** Některé bloky vyžadují customizaci pro naše datové modely

### Alternativy
- **Tailwind UI** — dobrý, ale méně bloků pro admin/dashboard
- **Cruip** — free, ale menší výběr a méně udržovaný
- **Pure custom** — maximální flexibilita, ale pomalé a náchylné na nekonzistenci

---

## ADR-007: JWT + Refresh token místo session

**Datum:** 2026-08-01
**Status:** Accepted
**Týká se:** Security, Backend

### Kontext
Potřebujeme autentizaci, která funguje pro SPA frontend a je bezpečná. Backend a frontend jsou oddělené.

### Rozhodnutí
Použít **JWT access token (15 min) + Refresh token (7 dní)** s rotating refresh tokens.

### Důvody
- SPA-friendly — frontend ukládá token v memory (ne localStorage)
- Stateless — backend nevyžaduje session storage pro auth
- Refresh token rotation — detekce token theft
- Krátký TTL access tokenu — minimalizace rizika při úniku

### Důsledky
- **+** Stateless, škálovatelné, SPA-friendly
- **−** Token revokace vyžaduje blacklist (při logout)
- **−** Refresh token rotation vyžaduje DB storage

### Alternativy
- **Server-side sessions** — bezpečnější, ale vyžaduje session storage
- **OAuth 2.0 / OIDC** — overkill pro single-tenant admin nástroj
- **API keys** — nevhodné pro uživatelskou autentizaci

---

## ADR-008: Symfony EventDispatcher pro modulovou komunikaci

**Datum:** 2026-08-01
**Status:** Accepted
**Týká se:** Backend, Architecture

### Kontext
Moduly (Updates, Backups, Security, SEO) potřebují komunikovat — např. Updates modul vyžaduje pre-update backup od Backups modulu. Přímé volání by vytvořilo těsné vazby.

### Rozhodnutí
Použít **Symfony EventDispatcher** pro komunikaci mezi moduly.

### Důvody
- Loose coupling — moduly neznají sebe navzájem, jen eventy
- Pub/sub pattern — jeden modul vyšle event, ostatní reagují
- Testovatelné — snadné mockování event listenerů
- Symfony standard — PSR-14 compatible

### Důsledky
- **+** Moduly jsou nezávislé, snadno přidat/odebrat
- **+** Event flow je explicitní a auditovatelný
- **−** Debugging event flow je složitější než přímé volání
- **−** Eventy je třeba dobře dokumentovat

### Alternativy
- **Přímá volání** — těsné vazby, špagetový kód
- **Message queue (RabbitMQ)** — overkill pro lokální aplikaci
- **PSR-14 Event Dispatcher** — standard, ale Symfony impl. je nejzralejší
