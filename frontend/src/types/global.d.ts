/// <reference types="vite/client" />

interface WindowEnv {
  VITE_API_URL?: string;
  VITE_APP_NAME?: string;
  VITE_APP_VERSION?: string;
  VITE_ENABLE_CLIENT_CRYPTO?: string;
}

interface Window {
  __ENV__?: WindowEnv;
}
