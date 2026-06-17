const USER_STORAGE_KEY = 'user';

type StoredUser = {
  id?: number;
};

const readStoredUserId = (): number | null => {
  if (typeof window === 'undefined') return null;
  try {
    const raw = localStorage.getItem(USER_STORAGE_KEY);
    if (!raw) return null;
    const parsed = JSON.parse(raw) as StoredUser;
    return typeof parsed?.id === 'number' ? parsed.id : null;
  } catch {
    return null;
  }
};

export const getStoredUserId = (): number | null => readStoredUserId();

const buildUserScopedKey = (baseKey: string, userId?: number | null) => {
  const resolvedId = userId ?? readStoredUserId();
  return resolvedId ? `${baseKey}::user:${resolvedId}` : baseKey;
};

export const LAST_SUBJECT_KEY = 'slt::lastSubjectId';
export const LAST_QUIZ_RESULT_KEY = 'slt::lastQuizResult';

export const getLastSubjectKey = (userId?: number | null) =>
  buildUserScopedKey(LAST_SUBJECT_KEY, userId);

export const getLastQuizResultKey = (userId?: number | null) =>
  buildUserScopedKey(LAST_QUIZ_RESULT_KEY, userId);
