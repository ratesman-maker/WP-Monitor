import { useState, type FormEvent } from 'react';
import { useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';

import { useLogin } from '../hooks/useLogin';

import { Button } from '@/components/ui/button';
import { SettingsToggles } from '@/components/common/SettingsToggles';
import {
  Card,
  CardContent,
  CardDescription,
  CardFooter,
  CardHeader,
  CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export default function LoginPage() {
  const { t } = useTranslation(['common', 'auth']);
  const navigate = useNavigate();
  const loginMutation = useLogin();
  const [username, setUsername] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState('');

  const handleSubmit = async (e: FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    setError('');

    if (username.trim() === '' || password === '') {
      setError(t('login.errorEmptyFields', { ns: 'auth' }));
      return;
    }

    try {
      await loginMutation.mutateAsync({ username: username.trim(), password });
      navigate('/');
    } catch (err) {
      const message = err instanceof Error ? err.message : t('error.loginFailed');
      setError(message);
    }
  };

  return (
    <div className="relative flex min-h-screen items-center justify-center bg-background p-4">
      <div className="absolute right-4 top-4">
        <SettingsToggles />
      </div>
      <Card className="w-full max-w-md">
        <CardHeader>
          <CardTitle className="text-2xl">{t('login.title', { ns: 'auth' })}</CardTitle>
          <CardDescription>{t('login.subtitle', { ns: 'auth' })}</CardDescription>
        </CardHeader>
        <form onSubmit={handleSubmit}>
          <CardContent className="space-y-4">
            <div className="space-y-2">
              <Label htmlFor="username">{t('login.username', { ns: 'auth' })}</Label>
              <Input
                id="username"
                type="text"
                value={username}
                onChange={(e) => setUsername(e.target.value)}
                placeholder={t('login.usernamePlaceholder', { ns: 'auth' })}
                autoComplete="username"
                disabled={loginMutation.isPending}
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="password">{t('login.password', { ns: 'auth' })}</Label>
              <Input
                id="password"
                type="password"
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                placeholder={t('login.passwordPlaceholder', { ns: 'auth' })}
                autoComplete="current-password"
                disabled={loginMutation.isPending}
              />
            </div>
            {error && (
              <p className="text-sm text-destructive" role="alert">
                {error}
              </p>
            )}
          </CardContent>
          <CardFooter>
            <Button
              type="submit"
              className="w-full"
              disabled={loginMutation.isPending}
            >
              {loginMutation.isPending ? t('login.submitting', { ns: 'auth' }) : t('login.submit', { ns: 'auth' })}
            </Button>
          </CardFooter>
        </form>
      </Card>
    </div>
  );
}
