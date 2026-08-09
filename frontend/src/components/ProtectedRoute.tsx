import { type ReactNode } from 'react';
import { Navigate, useLocation } from 'react-router-dom';

import { Skeleton } from '@/components/ui/skeleton';
import { useAuthStore } from '@/stores/authStore';

interface ProtectedRouteProps {
  children: ReactNode;
  /**
   * Whether the silent session refresh is in progress (app init).
   * When true and a persisted user exists, a loading state is shown
   * instead of redirecting to /login — the refresh may still succeed.
   */
  isInitializing?: boolean;
}

/**
 * Route guard — redirects to /login if the user is not authenticated.
 * Preserves the intended destination in location state for post-login redirect.
 *
 * During the initial silent refresh (page reload with a persisted user but
 * no access token), a loading skeleton is shown while the refresh is in
 * flight. This avoids a flash of the login page on reload for logged-in users.
 */
export function ProtectedRoute({ children, isInitializing = false }: ProtectedRouteProps) {
  const isAuthenticated = useAuthStore((s) => s.isAuthenticated);
  const user = useAuthStore((s) => s.user);
  const location = useLocation();

  // Still restoring the session — show loading, don't redirect yet.
  if (isInitializing && user) {
    return (
      <div className="flex min-h-screen items-center justify-center bg-background p-6">
        <div className="flex w-full max-w-md flex-col gap-4">
          <Skeleton className="h-8 w-48" />
          <Skeleton className="h-4 w-full" />
          <Skeleton className="h-4 w-3/4" />
          <Skeleton className="h-10 w-full" />
        </div>
      </div>
    );
  }

  if (!isAuthenticated) {
    return <Navigate to="/login" state={{ from: location }} replace />;
  }

  return <>{children}</>;
}
