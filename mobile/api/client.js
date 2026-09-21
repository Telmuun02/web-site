
import { create as createAxios, isAxiosError } from 'axios';
import * as SecureStore from 'expo-secure-store';

const TOKEN_KEY = 'folio_token';
const USER_KEY = 'folio_user';

let memoryToken = null;

/**
 * @typedef {object} User
 * @property {number} id
 * @property {string} name
 * @property {string} email
 * @property {'admin' | 'member' | string} role
 * @property {{ id: number, name: string } | null} [company]
 */

export async function loadToken() {
  memoryToken = await SecureStore.getItemAsync(TOKEN_KEY);
  return memoryToken;
}

export async function saveToken(token) {
  memoryToken = token;
  await SecureStore.setItemAsync(TOKEN_KEY, token);
}

export async function clearToken() {
  memoryToken = null;
  await SecureStore.deleteItemAsync(TOKEN_KEY);
}

export async function loadStoredUser() {
  const raw = await SecureStore.getItemAsync(USER_KEY);
  if (!raw) return null;

  try {
    return JSON.parse(raw);
  } catch {
    await SecureStore.deleteItemAsync(USER_KEY);
    return null;
  }
}

export async function saveStoredUser(user) {
  await SecureStore.setItemAsync(USER_KEY, JSON.stringify(user));
}

export async function clearStoredUser() {
  await SecureStore.deleteItemAsync(USER_KEY);
}

// --------------------------------------------------------------- client -----

const baseURL = process.env.EXPO_PUBLIC_API_URL;

// Хаяг тохируулаагүй бол хүсэлт бүр ойлгомжгүй алдаагаар унана. Эрт хэлье.
if (!baseURL) {
  console.warn(
    'EXPO_PUBLIC_API_URL тохируулаагүй байна. mobile/.env файлыг шалгаад ' +
      'Expo-г дахин асаана уу (npx expo start -c).'
  );
}

const client = createAxios({
  baseURL,
  headers: {
    Accept: 'application/json',
    'Content-Type': 'application/json',
  },
  timeout: 15000,
});

// Веб хувилбартай адил: токен байвал Authorization толгой залгана.
client.interceptors.request.use((config) => {
  if (memoryToken) {
    config.headers.Authorization = `Bearer ${memoryToken}`;
  }
  return config;
});

export default client;

export function apiError(err, fallback = 'Алдаа гарлаа.') {
  if (isAxiosError(err)) {
    // Сервер хариулсан — Laravel-ийн мессежийг харуулна.
    if (err.response) {
      if (err.response.status === 401) {
        return 'Нэвтрэх хугацаа дууссан байна. Дахин нэвтэрнэ үү.';
      }
      return err.response.data?.message ?? fallback;
    }

    // Сервер хариулаагүй — хаяг/сүлжээний асуудал.
    return (
      'Сервертэй холбогдож чадсангүй.\n\n' +
      `Хаяг: ${baseURL ?? '(тохируулаагүй)'}\n` +
      'Утас, компьютер хоёр ижил Wi-Fi-д байгаа эсэх, docker compose асаалттай ' +
      'эсэхийг шалгаад mobile/.env доторх IP-г шинэчилнэ үү.'
    );
  }

  return fallback;
}
