import { Routes, Route, Link, useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';

import { Button } from '@/components/ui/button';
import { SettingsToggles } from '@/components/common/SettingsToggles';
import { ProtectedRoute } from '@/components/ProtectedRoute';
import LoginPage from '@/modules/auth/pages/LoginPage';
import { useInitAuth } from '@/modules/auth/hooks/useInitAuth';
import { useLogout } from '@/modules/auth/hooks/useLogout';
import { useAuthStore } from '@/stores/authStore';

function Sidebar() {
  const { t } = useTranslation();
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
        <h1 className="text-lg font-bold text-foreground">{t('app.name')}</h1>
        <Link to="/" className="text-sm text-muted-foreground hover:text-foreground">
          {t('sidebar.dashboard')}
        </Link>
        <Link to="/sites" className="text-sm text-muted-foreground hover:text-foreground">
          {t('sidebar.sites')}
        </Link>
        <Link to="/settings" className="text-sm text-muted-foreground hover:text-foreground">
          {t('sidebar.settings')}
        </Link>
        <div className="mt-4">
          <SettingsToggles />
        </div>
        {isAuthenticated && (
          <Button
            variant="ghost"
            size="sm"
            onClick={handleLogout}
            disabled={logoutMutation.isPending}
            className="mt-2 justify-start"
          >
            {logoutMutation.isPending ? t('sidebar.signingOut') : t('sidebar.signOut')}
          </Button>
        )}
      </nav>
    </aside>
  );
}

function AppContent() {
  const { t } = useTranslation();

  return (
    <div className="grid-app">
      <Sidebar />
      <main className="p-6">
        <Routes>
          <Route path="/" element={<div className="text-foreground">{t('dashboard.comingSoon')}</div>} />
          <Route path="/sites" element={<div className="text-foreground">{t('sites.comingSoon')}</div>} />
          <Route path="/settings" element={<div className="text-foreground">{t('settings.comingSoon')}</div>} />
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
