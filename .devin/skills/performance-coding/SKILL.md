---
name: performance-coding
description: Performance pravidla pro rychlý kód — DB optimalizace, HTTP concurrency, React rendering, bundle size. Aplikuje se při každém psaní nebo úpravě kódu.
triggers:
  - model
---

# Skill: Performance Coding

> Pravidla pro rychlý kód. Rychlost je jedna ze 3 prioritních zásad.
> Viz docs/02-architecture.md a docs/07-development-guide.md.

## PHP Backend

### Databáze

```php
// ✅ VŽDY: Použít indexované sloupce pro WHERE/ORDER BY
// DB schéma má indexy definované v docs/05-database-schema.md

// ✅ VŽDY: Batch operace — ne N dotazů, ale 1 dotaz
$sites = $this->conn->fetchAll(
    "SELECT * FROM sites WHERE id IN (:ids)",
    ['ids' => $siteIds]
);  // 1 dotaz

// ❌ NIKDY: N+1 query pattern
foreach ($siteIds as $id) {
    $site = $this->conn->fetchAssoc("SELECT * FROM sites WHERE id = ?", [$id]);
}  // N dotazů!

// ✅ VŽDY: LIMIT pro velké výsledky
$qb->select('*')->from('activity_log')->setMaxResults(100)->setFirstResult($offset);

// ✅ VŽDY: Použít transakci pro multi-operace
$this->conn->transactional(function () {
    $this->conn->insert('sites', $data);
    $this->conn->insert('audit_log', $logEntry);
});

// ✅ Cache často používaných dat
$modules = $this->cache->get('modules.active', fn() => $this->moduleRepo->findActive());
```

### HTTP client (Guzzle)

```php
// ✅ VŽDY: Concurrency pro hromadné operace (Guzzle pool)
$pool = new Pool($client, $requests, [
    'concurrency' => 10,  // max 10 paralelních
    'fulfilled' => function ($response, $index) { /* ... */ },
    'rejected' => function ($reason, $index) { /* ... */ },
]);
$pool->promise()->wait();

// ✅ VŽDY: Timeout nastaven (30s default, 10s connect)
$client = new Client(['timeout' => 30, 'connect_timeout' => 10]);

// ✅ VŽDY: Retry s exponential backoff (max 2 retry)
// Guzzle middleware pro retry

// ❌ NIKDY: Synchronní volání na 50+ webů bez pool
foreach ($sites as $site) {
    $client->get($site->url . '/wp-json/...');  // sekvenční = pomalé!
}
```

### Memory management

```php
// ✅ VŽDY: memory_limit 512M v produkci
// ✅ VŽDY: Wipe velké stringy z paměti
sodium_memzero($decryptedPassword);
sodium_memzero($backupKey);

// ✅ Pro velké datové sady — generator/yield
function getSitesIterator(): Generator {
    $stmt = $this->conn->executeQuery("SELECT * FROM sites");
    while ($row = $stmt->fetch()) {
        yield Site::fromArray($row);
    }
}

// ❌ NIKDY: Načítat všechny záznamy do paměti
$allLogs = $this->conn->fetchAll("SELECT * FROM activity_log");  // OOM risk!
```

### OpCache

```ini
# ✅ VŽDY: OpCache zapnutý v produkci
opcache.enable=1
opcache.validate_timestamps=0  # v prod (v dev = 1)
opcache.memory_consumption=256
```

## TypeScript Frontend

### React rendering

```tsx
// ✅ VŽDY: React.memo pro drahé komponenty
const SiteCard = React.memo(function SiteCard({ site }: Props) {
    return <Card>...</Card>;
});

// ✅ VŽDY: useMemo pro drahé výpočty
const sortedSites = useMemo(
    () => sites.sort((a, b) => a.name.localeCompare(b.name)),
    [sites]
);

// ✅ VŽDY: useCallback pro handlery v dependencies
const handleDelete = useCallback((id: number) => {
    deleteSite(id);
}, [deleteSite]);

// ❌ NIKDY: Zbytečné re-rendery
function SiteList({ sites }: Props) {
    // ❌ vytváří novou funkci při každém renderu
    const handleDelete = (id: number) => deleteSite(id);
    return sites.map(s => <SiteCard key={s.id} onDelete={handleDelete} />);
}
```

### Data fetching (TanStack Query)

```tsx
// ✅ VŽDY: Použít TanStack Query pro server state
const { data, isLoading, error } = useQuery({
    queryKey: ['sites', siteId],
    queryFn: () => fetchSite(siteId),
    staleTime: 60_000,      // 1 min cache
    gcTime: 5 * 60_000,     // 5 min garbage collection
});

// ✅ VŽDY: Query key jako array (pro invalidaci)
// ✅ VŽDY: staleTime nastaven (ne default 0)
// ✅ Pro mutace: invalidate relevant queries
const mutation = useMutation({
    mutationFn: updateSite,
    onSuccess: () => {
        queryClient.invalidateQueries({ queryKey: ['sites'] });
    },
});

// ❌ NIKDY: useEffect + fetch + useState místo useQuery
useEffect(() => {
    fetch('/api/sites').then(setSites);
}, []);  // ZÁKAZ! Použít useQuery
```

### Bundle size

```tsx
// ✅ VŽDY: Code splitting pro routes (React.lazy)
const DashboardPage = lazy(() => import('./pages/DashboardPage'));
const SitesPage = lazy(() => import('./pages/SitesPage'));

// ✅ VŽDY: Dynamic import pro velké knihovny
const chart = await import('recharts');  // jen když potřebuješ

// ❌ NIKDY: Importovat celou knihovnu pokud potřebuješ 1 funkci
import _ from 'lodash';  // ZÁKAZ! ~70KB
// ✅ Místo:
import debounce from 'lodash/debounce';  // jen ~1KB
```

### Vite build

```ts
// ✅ vite.config.ts — production optimalizace
build: {
    rollupOptions: {
        output: {
            manualChunks: {
                'react-vendor': ['react', 'react-dom', 'react-router-dom'],
                'query-vendor': ['@tanstack/react-query'],
                'ui-vendor': ['lucide-react'],
            },
        },
    },
    chunkSizeWarningLimit: 500,  // alert při > 500KB chunk
}
```

## Performance checklist

- [ ] Žádné N+1 dotazy
- [ ] Batch operace používají Guzzle pool (concurrency)
- [ ] React.memo na drahé komponenty
- [ ] useQuery místo useEffect+fetch
- [ ] Code splitting na routes
- [ ] OpCache zapnutý v prod
- [ ] memory_limit 512M v prod
- [ ] DB indexy na často dotazovaných sloupcích
- [ ] Cache na často používaná data
- [ ] Timeout na HTTP volání

## Testovací checklist — Performance

### Unit testy

- [ ] **Repository** — test batch query vrací správná data (IN clause)
- [ ] **Repository** — test LIMIT/OFFSET funguje správně
- [ ] **Guzzle pool** — test concurrency limit je dodržen (max 10 paralelních)
- [ ] **Guzzle pool** — test timeout na jeden request nezablokuje pool
- [ ] **Cache** — test cache hit vrací cached data
- [ ] **Cache** — test cache miss zavolá fallback a uloží výsledek
- [ ] **Generator** — test yield ne načte vše do paměti (memory_usage před/po)

### Integration testy

- [ ] **N+1 detekce** — test načtení 100 webů provede ≤ 2 SQL dotazy (1 pro sites, 1 pro metadata)
- [ ] **Batch update** — test 50 webů aktualizováno s Guzzle pool (concurrency 10) < 30s
- [ ] **DB transakce** — test rollback při chybě v transakci
- [ ] **DB indexy** — test EXPLAIN na kritické dotazy nepoužívá filesort/temporary
- [ ] **Memory limit** — test zpracování 10 000 log záznamů nepřekročí 128M
- [ ] **Response time** — test API endpoint < 200ms pro 100 záznamů (p95)
- [ ] **Response time** — test API endpoint < 500ms pro 1000 záznamů (p95)

### Frontend testy (Vitest + React Testing Library)

- [ ] **React.memo** — test komponenta se nepřerenderuje při nezměněných props
- [ ] **useMemo** — test drahý výpočet neprobíhá při nezměněných dependencies
- [ ] **useCallback** — test handler reference je stabilní mezi rendery
- [ ] **TanStack Query** — test staleTime funguje (nefetchuje znovu v časovém okně)
- [ ] **TanStack Query** — test invalidateQueries spustí refetch
- [ ] **Code splitting** — test lazy route se načte až při navigaci (dynamic import)
- [ ] **Bundle size** — test main chunk < 200KB (gzip)
- [ ] **Bundle size** — test žádný chunk > 500KB

### Load / stress testy

- [ ] **Apache Bench** — `ab -n 1000 -c 10 https://wp-monitor.example.com/api/health` — p95 < 100ms
- [ ] **k6 / Artillery** — test 100 koncurrent uživatelů na dashboard → p95 < 500ms
- [ ] **k6 / Artillery** — test batch update 50 webů → dokončeno < 60s
- [ ] **DB load** — test 100 koncurrent dotazů na `activity_log` → p95 < 200ms
- [ ] **Memory** — test memory_usage po 1000 requestech se ne zvyšuje (memory leak detekce)

### Monitoring / benchmark

- [ ] **Lighthouse** — Performance score ≥ 90 (frontend)
- [ ] **Lighthouse** — LCP < 2.5s, CLS < 0.1, INP < 200ms
- [ ] **PHP profiling** — Blackfire/Tideways na kritické endpointy
- [ ] **DB slow query log** — žádný dotaz > 1s v produkci
- [ ] **OpCache hit rate** — > 99% v produkci
