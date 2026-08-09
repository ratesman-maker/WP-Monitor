import { Routes, Route, Link, useNavigate } from 'react-router-dom';

import { Button } from '@/components/ui/button';
import { ProtectedRoute } from '@/components/ProtectedRoute';
import LoginPage from '@/modules/auth/pages/LoginPage';
import { useInitAuth } from '@/modules/auth/hooks/useInitAuth';
import { useLogout } from '@/modules/auth/hooks/useLogout';
import { useAuthStore } from '@/stores/authStore';

function Sidebar() {
  const navigate = useNavigate();
  const logoutMutation = useLogout();
  const isAuthenticated = useAuthStore((s) => s.isAuthenticated);

  const handleLogout = async () => {
    await logoutMutation.mutateAsync();
    navigate('/login');
  };

  return (
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
        {isAuthenticated && (
          <Button
            variant="ghost"
            size="sm"
            onClick={handleLogout}
            disabled={logoutMutation.isPending}
            className="mt-4 justify-start"
          >
            {logoutMutation.isPending ? 'Signing out...' : 'Sign out'}
          </Button>
        )}
      </nav>
    </aside>
  );
}

function AppContent() {
  return (
    <div className="grid-app">
      <Sidebar />
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

export default function App() {
  const { isInitializing } = useInitAuth();

  return (
    <Routes>
      <Route path="/login" element={<LoginPage />} />
      <Route
        path="/*"
        element={
          <ProtectedRoute isInitializing={isInitializing}>
            <AppContent />
          </ProtectedRoute>
        }
      />
    </Routes>
  );
}
