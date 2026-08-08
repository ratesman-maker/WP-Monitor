---
name: modularity-coding
description: Pravidla pro modulární kód a architekturu — vrstvy, EventDispatcher, DTO, limity kódu. Aplikuje se při každém psaní nebo úpravě kódu.
triggers:
  - model
---

# Skill: Modularity & Architecture

> Pravidla pro modulární kód. Modularita je jedna ze 3 prioritních zásad.
> Viz docs/02-architecture.md a docs/07-development-guide.md sekce 2.3.

## Základní principy

1. **Jedna třída = jedna zodpovědnost** — pokud popíšeš co třída dělá a potřebuješ "a", rozděl
2. **Service orchestruje, neimplementuje** — DB v Repository, HTTP v Client, šifrování v CryptoService
3. **Moduly komunikují přes EventDispatcher** — ne přímými voláními
4. **Dependency depth ≤ 3** — A→B→C→D je limit
5. **Max 4 parametry v metodě** — více = DTO/options objekt

## Backend vrstvy

```
Controller (tenký, ~150 řádků)
    ↓ volá
Service (business logika, ~400 řádků)
    ↓ volá
Repository (DB přístup) / Client (HTTP) / CryptoService (šifrování)
```

### Controller

```php
// ✅ VŽDY: Controller je tenký — validace, volání service, response
class SitesController
{
    public function __construct(
        private readonly SitesService $service,
        private readonly RequestValidator $validator,
    ) {}

    public function create(Request $req, Response $res): Response
    {
        $data = $this->validator->validate($req, SiteSchema::create());
        $site = $this->service->create($data);
        return $res->withStatus(201)->withJson($site->toArray());
    }
}

// ❌ NIKDY: Business logika v controlleru
public function create(Request $req, Response $res): Response
{
    $data = $req->getParsedBody();
    // 50 řádků validace, šifrování, DB insert, audit log...  // ZÁKAZ!
}
```

### Service

```php
// ✅ VŽDY: Service orchestruje — volá Repository, Client, CryptoService
class UpdatesService
{
    public function __construct(
        private readonly WordPressClientFactory $clientFactory,
        private readonly BackupService $backupService,
        private readonly UpdateHistoryRepository $historyRepo,
        private readonly AuditLogger $auditLogger,
        private readonly EventDispatcher $eventDispatcher,
    ) {}

    public function executeUpdate(Site $site, UpdateOptions $options): UpdateResult
    {
        // 1. Pre-backup (deleguje na BackupService)
        if ($options->preBackup) {
            $this->backupService->createQuickBackup($site);
        }

        // 2. Execute update (deleguje na WordPressClient)
        $client = $this->clientFactory->create($site);
        $result = $client->updatePlugin($options->pluginSlug);

        // 3. Log (deleguje na Repository)
        $this->historyRepo->log($site->id, $result);

        // 4. Event (notifikuje ostatní moduly)
        $this->eventDispatcher->dispatch(new UpdateCompletedEvent($site, $result));

        return $result;
    }
}

// ❌ NIKDY: Service dělá všechno sama
public function executeUpdate(Site $site): void
{
    // Šifrování credentials přímo v service  // ZÁKAZ!
    $decrypted = sodium_crypto_aead_aes256gcm_decrypt(...);
    // HTTP call přímo v service  // ZÁKAZ!
    $client = new GuzzleHttp\Client();
    // DB insert přímo v service  // ZÁKAZ!
    $this->conn->insert('update_history', [...]);
}
```

### Repository

```php
// ✅ VŽDY: Repository pattern pro DB přístup
class SitesRepository
{
    public function __construct(
        private readonly Connection $conn,
    ) {}

    public function find(int $id): ?Site
    {
        $row = $this->conn->fetchAssoc("SELECT * FROM sites WHERE id = ?", [$id]);
        return $row ? Site::fromArray($row) : null;
    }

    public function findByUserId(int $userId): array
    {
        $rows = $this->conn->fetchAll(
            "SELECT * FROM sites WHERE user_id = ? ORDER BY name",
            [$userId]
        );
        return array_map(fn($row) => Site::fromArray($row), $rows);
    }
}

// ❌ NIKDY: DB dotazy v Service nebo Controlleru
class SitesService
{
    public function getSites(int $userId): array
    {
        return $this->conn->fetchAll("SELECT * FROM sites WHERE user_id = ?", [$userId]);
        // ZÁKAZ! Použít SitesRepository
    }
}
```

### EventDispatcher — komunikace mezi moduly

```php
// ✅ VŽDY: Moduly komunikují přes eventy
// Updates modul vyšle event:
$this->eventDispatcher->dispatch(new UpdateCompletedEvent($site, $result));

// Backups modul naslouchá:
class BackupEventListener
{
    public function onUpdateCompleted(UpdateCompletedEvent $event): void
    {
        // Volitelně vytvořit post-update backup snapshot
    }
}

// ❌ NIKDY: Přímé volání mezi moduly
class UpdatesService
{
    public function __construct(
        private BackupService $backupService,  // ZÁKAZ! Přímá závislost na jiném modulu
    ) {}
}
```

### DTO (Data Transfer Object)

```php
// ✅ VŽDY: DTO pro více než 4 parametry
class UpdateOptions
{
    public function __construct(
        public readonly string $pluginSlug,
        public readonly bool $preBackup = true,
        public readonly bool $dryRun = false,
        public readonly ?string $targetVersion = null,
    ) {}
}

// ❌ NIKDY: 5+ parametrů v metodě
public function executeUpdate(
    Site $site,
    string $pluginSlug,
    bool $preBackup,
    bool $dryRun,
    ?string $targetVersion,
    ?int $batchId,      // 6. parametr! ZÁKAZ!
): UpdateResult
```

## Frontend modulární struktura

```tsx
// ✅ VŽDY: Feature-based struktura (ne type-based)
frontend/src/modules/updates/
├── pages/
│   ├── UpdatesOverviewPage.tsx
│   └── UpdateHistoryPage.tsx
├── components/
│   ├── BatchUpdateDialog.tsx
│   └── BatchProgressBar.tsx
├── hooks/
│   ├── useUpdates.ts
│   └── useBatchUpdate.ts
├── api.ts
├── types.ts
└── store.ts

// ❌ NIKDY: Type-based struktura
frontend/src/components/    // všechny komponenty v jednom adresáři
frontend/src/hooks/         // všechny hooky v jednom adresáři
frontend/src/pages/         // všechny stránky v jednom adresáři
```

### Custom hooks pro logiku

```tsx
// ✅ VŽDY: Business logika do custom hooku, ne do komponenty
function useBatchUpdate() {
    const queryClient = useQueryClient();
    const mutation = useMutation({
        mutationFn: batchUpdate,
        onSuccess: () => queryClient.invalidateQueries({ queryKey: ['updates'] }),
    });
    return { mutate: mutation.mutate, isLoading: mutation.isPending };
}

// Komponenta je presentational:
function BatchUpdateDialog({ sites, onClose }: Props) {
    const { mutate, isLoading } = useBatchUpdate();
    return <Dialog>...</Dialog>;
}

// ❌ NIKDY: Business logika v komponentě
function BatchUpdateDialog({ sites }: Props) {
    const [loading, setLoading] = useState(false);
    const handleUpdate = async () => {
        setLoading(true);
        const response = await fetch('/api/updates/batch', ...);  // ZÁKAZ!
        setLoading(false);
    };
}
```

## Limity kódu

| Typ | Doporučený strop | Tvrdý limit |
|-----|------------------|-------------|
| PHP Service | ~400 řádků | 500 |
| PHP Controller | ~150 řádků | 200 |
| PHP Security třída | ~200 řádků | 300 |
| React Page komponenta | ~250 řádků | 350 |
| React komponenta | ~100 řádků | 150 |
| PHP metoda | ~40 řádků | 60 |
| TS funkce | ~30 řádků | 50 |
| React render | ~50 řádků JSX | 80 |

## Varovné signály — refaktorovat když

- Třída má 5+ různých "témat" v metodách → rozděl na 2+ tříd
- Metoda má 4+ úrovně if/else nesting → early return nebo extrakce
- Komponenta má 8+ `useState` → přesuň do custom hooku nebo Zustand
- Service injectuje 6+ závislostí → rozděl na subservisy
- Stejná logika ve 3+ souborech → extrahuj do sdílené utility
- Funkce volá 5+ dalších funkcí v řadě → zjednoduš pipeline

## Testovací checklist — Modularity

### Architektura — statická analýza

- [ ] **PHPStan level 8** — projde bez chyb (`composer analyse`)
- [ ] **ESLint** — projde bez warningů (`npm run lint -- --max-warnings=0`)
- [ ] **Controller řádky** — žádný controller > 200 řádků
- [ ] **Service řádky** — žádná service > 500 řádků
- [ ] **Komponenta řádky** — žádná React komponenta > 150 řádků
- [ ] **Metoda řádky** — žádná PHP metoda > 60 řádků
- [ ] **Parametry** — žádná metoda/funkce > 4 parametry (jinak DTO)
- [ ] **Nesting** — žádná metoda > 3 úrovně if/else nesting
- [ ] **Dependencies** — žádná service injectuje > 5 závislostí
- [ ] **Dependency depth** — žádná řada A→B→C→D (max 3 úrovně)

### Architektura — vynuceno nástroji

- [ ] **Rector** — dry-run neproponuje žádné změny (`rector process src --dry-run`)
- [ ] **dpdm** — žádné cyklické závislosti v frontendu (`dpdm ./src/main.tsx --exit-code circular:1`)
- [ ] **PHP CS Fixer** — projde bez návrhů (`php-cs-fixer fix --dry-run`)
- [ ] **Prettier** — projde bez návrhů (`prettier --check "src/**/*.{ts,tsx}"`)

### Unit testy — modulární izolace

- [ ] **Service test** — test Service s mockovaným Repository (ne reálná DB)
- [ ] **Service test** — test Service s mockovaným Client (ne reálné HTTP)
- [ ] **Controller test** — test Controller s mockovanou Service
- [ ] **Repository test** — test Repository s testovací DB (ne mock)
- [ ] **Event test** — test EventDispatcher dispatchuje správný event
- [ ] **Event test** — test Listener reaguje na správný event
- [ ] **DTO test** — test DTO validace (required fields, defaults, typy)
- [ ] **Hook test** — test custom hook s mockovaným useQuery (msw nebo mock)

### Integration testy — modulová hranice

- [ ] **Modul izolace** — test Updates modul neimportuje Backups modul přímo
- [ ] **Modul izolace** — test každý modul lze zakázat v `modules.php` bez chyby
- [ ] **Event komunikace** — test Updates → UpdateCompletedEvent → Backups listener reaguje
- [ ] **Event komunikace** — test Backups listener NEreaguje na cizí eventy
- [ ] **DI container** — test každá service je registrována v containeru
- [ ] **DI container** — test circular dependency detekce (A→B→A = chyba)

### Frontend testy (Vitest + React Testing Library)

- [ ] **Feature izolace** — test `modules/updates/` neimportuje z `modules/backups/`
- [ ] **Hook izolace** — test `useUpdates` nevolá API z `modules/backups/`
- [ ] **Komponenta izolace** — test komponenta neprovádí API volání (pouze hook)
- [ ] **Store izolace** — test Zustand store neimportuje z jiného modulu
- [ ] **Props drilling** — test žádná komponenta nepředává props > 3 úrovně
- [ ] **Re-render** — test komponenta se nepřerenderuje při nezměněných props (React.memo test)

### Code review checklist

- [ ] **Single responsibility** — lze popsat co třída dělá jednou větou bez "a"
- [ ] **God object** — service nedělá DB + HTTP + šifrování (orchestruje)
- [ ] **Přímé volání** — moduly nevolají se navzájem přímo (přes eventy)
- [ ] **Duplikace** — stejná logika není ve 2+ souborech
- [ ] **Dead code** — žádné nepoužívané třídy, funkce, importy
- [ ] **TODO/FIXME** — žádné bez issue linku
