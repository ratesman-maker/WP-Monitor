import { useMutation } from '@tanstack/react-query';

import { authApi } from '../api';

import { useAuthStore } from '@/stores/authStore';


export function useLogout() {
  const token = useAuthStore((s) => s.token);
  const csrfToken = useAuthStore((s) => s.csrfToken);
  const logoutStore = useAuthStore((s) => s.logout);

  return useMutation({
    mutationFn: () => {
      if (!token) {
        throw new Error('Not authenticated');
      }
      return authApi.logout(token, csrfToken ?? '');
    },
    onSettled: () => {
      // Always clear store, even if the API call failed
      logoutStore();
    },
  });
}
