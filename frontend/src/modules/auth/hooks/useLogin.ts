import { useMutation } from '@tanstack/react-query';

import { authApi, type LoginResponse } from '../api';

import { useAuthStore } from '@/stores/authStore';


export function useLogin() {
  const loginStore = useAuthStore((s) => s.login);

  return useMutation({
    mutationFn: ({ username, password }: { username: string; password: string }) =>
      authApi.login(username, password),
    onSuccess: (data: LoginResponse) => {
      loginStore(data.user, {
        token: data.token,
        refreshToken: data.refreshToken,
        csrfToken: data.csrfToken,
        sessionId: data.sessionId,
      });
    },
  });
}
