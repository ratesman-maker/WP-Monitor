import { Routes, Route, Link } from 'react-router-dom';

export default function App() {
  return (
    <div className="grid-app">
      <aside className="sidebar-container bg-secondary p-4">
        <nav className="flex flex-col gap-2">
          <h1 className="text-lg font-bold text-foreground">WP Monitor</h1>
          <Link to="/" className="text-sm text-muted-foreground hover:text-foreground">
            Dashboard
          </Link>
          <Link to="/sites" className="text-sm text-muted-foreground hover:text-foreground">
            Sites
          </Link>
          <Link to="/settings" className="text-sm text-muted-foreground hover:text-foreground">
            Settings
          </Link>
        </nav>
      </aside>
      <main className="p-6">
        <Routes>
          <Route path="/" element={<div className="text-foreground">Dashboard — coming soon</div>} />
          <Route path="/sites" element={<div className="text-foreground">Sites — coming soon</div>} />
          <Route path="/settings" element={<div className="text-foreground">Settings — coming soon</div>} />
        </Routes>
      </main>
    </div>
  );
}
