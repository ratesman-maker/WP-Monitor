import { api } from '@/lib/api';

export interface AuthUser {
  id: number;
  username: string;
  role: 'admin' | 'manager' | 'viewer';
  email?: string | null;
}

export interface LoginResponse {
  token: string;
  refreshToken: string;
  csrfToken: string;
  sessionId: string;
  user: AuthUser;
}

export interface RefreshResponse {
  token: string;
  refreshToken: string;
}

export const authApi = {
  login: (username: string, password: string) =>
    api.post<LoginResponse>('/auth/login', { username, password }),

  logout: (token: string, csrfToken: string) =>
    api.post<null>('/auth/logout', undefined, { token, csrfToken }),

  // Refresh the access token. The refresh token is sent automatically via
  // the `wpm_refresh` httpOnly cookie (credentials: 'include'); no body needed.
  refresh: () => api.post<RefreshResponse>('/auth/refresh'),

  me: (token: string) => api.get<AuthUser>('/auth/me', { token }),

  setup: (username: string, password: string, email?: string) =>
    api.post<{ message: string; user: { id: number; username: string } }>('/auth/setup', {
      username,
      password,
      email,
    }),
};
