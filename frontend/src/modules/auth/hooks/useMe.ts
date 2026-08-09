import { useQuery } from '@tanstack/react-query';

import { authApi } from '../api';

import { useAuthStore } from '@/stores/authStore';


export function useMe() {
  const token = useAuthStore((s) => s.token);
  const isAuthenticated = useAuthStore((s) => s.isAuthenticated);

  return useQuery({
    queryKey: ['auth', 'me'],
    queryFn: () => {
      if (!token) {
        throw new Error('Not authenticated');
      }
      return authApi.me(token);
    },
    enabled: isAuthenticated && !!token,
  });
}
