import { useCallback, useEffect, useRef, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import { LAST_SUBJECT_KEY } from '../constants/storage';
import { api } from '../services/api';
import type { GoogleCredentialResponse } from '../types/global';

const getAdminBaseUrl = () => {
  if (typeof window !== 'undefined') {
    const isLocalhost =
      window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1';
    if (isLocalhost) {
      const segments = (window.location.pathname || '/').split('/').filter(Boolean);
      const first = segments[0] || '';
      const knownRoots = new Set(['auth', 'login', 'backend', 'public', 'api']);
      const prefix = first && !knownRoots.has(first) ? `/${first}` : '';
      return `${window.location.origin}${prefix}`;
    }
  }
  return import.meta.env.VITE_ADMIN_URL || import.meta.env.VITE_LEGACY_PROXY_TARGET || '';
};

const ADMIN_BASE_URL = getAdminBaseUrl();
const TOKEN_KEY = 'token';

const buildAdminUrl = (path: string) => {
  const base = String(ADMIN_BASE_URL || '').replace(/\/+$/, '');
  const normalizedPath = path.startsWith('/') ? path : `/${path}`;
  if (!base) return normalizedPath;
  if (base.endsWith('.php')) return base;
  return `${base}${normalizedPath}`;
};

const decodeJwtPayload = (token: string): Record<string, unknown> | null => {
  const parts = token.split('.');
  if (parts.length !== 3) return null;
  const payload = parts[1];
  const base64 = payload.replace(/-/g, '+').replace(/_/g, '/');
  const padded = base64.padEnd(base64.length + ((4 - (base64.length % 4)) % 4), '=');
  try {
    const json = atob(padded);
    return JSON.parse(json) as Record<string, unknown>;
  } catch {
    return null;
  }
};

const getEmailFromCredential = (credential: string): string | null => {
  const payload = decodeJwtPayload(credential);
  const email = payload?.email;
  return typeof email === 'string' ? email : null;
};

export const LoginPage = () => {
  const { loginWithGoogle, loginWithPassword, loginWithDev, logout } = useAuth();
  const navigate = useNavigate();
  const buttonContainerRef = useRef<HTMLDivElement | null>(null);
  const [scriptError, setScriptError] = useState<string | null>(null);
  const [authError, setAuthError] = useState<string | null>(null);
  const [authInProgress, setAuthInProgress] = useState(false);
  const [acceptedTerms, setAcceptedTerms] = useState(false);
  const [gsiReady, setGsiReady] = useState(false);
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [entryMode, setEntryMode] = useState<'user' | 'admin'>('user');
  const [googleClientId, setGoogleClientId] = useState(
    () => (isLocalhost ? '' : (import.meta.env.VITE_GOOGLE_CLIENT_ID ?? ''))
  );
  const devLoginEnabled = import.meta.env.VITE_DEV_LOGIN === 'true';
  const googleLoginEnabled = import.meta.env.VITE_GOOGLE_LOGIN_ENABLED !== 'false';

  // ✅ เข้าหน้า Overview เสมอหลังล็อกอิน
  const resolveStudyLogDestination = useCallback(async (): Promise<string> => {
    return '/';
  }, []);

  const bridgeAdminSession = useCallback(async (): Promise<string> => {
    const token = localStorage.getItem(TOKEN_KEY);
    if (!token) {
      throw new Error('ไม่พบข้อมูลการเข้าสู่ระบบ กรุณาลองใหม่อีกครั้ง');
    }

    const response = await fetch(buildAdminUrl('/backend/admin_session_login.php'), {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json'
      },
      credentials: 'include',
      body: JSON.stringify({ token })
    });

    const data = (await response.json().catch(() => null)) as
      | { ok?: boolean; error?: string; redirect?: string }
      | null;

    if (!response.ok || !data?.ok) {
      throw new Error(data?.error || 'ไม่สามารถเข้าสู่หน้าแอดมินได้');
    }

    return data.redirect || buildAdminUrl('/backend/admin.php');
  }, []);

  const handleLoginSuccess = useCallback(
    async (user: { role?: string | null }) => {
      // ✅ กันข้อมูลข้ามบัญชี: เคลียร์วิชาล่าสุดทันทีหลังล็อกอินสำเร็จ
      localStorage.removeItem(LAST_SUBJECT_KEY);

      if (entryMode === 'admin') {
        if (user?.role === 'admin') {
          const adminRedirectUrl = await bridgeAdminSession();
          window.location.assign(adminRedirectUrl);
          return;
        }
        await logout();
        setAuthError('บัญชีนี้ไม่มีสิทธิ์ผู้ดูแลระบบ กรุณาเลือก "ผู้ใช้ทั่วไป"');
        return;
      }

      const destination = await resolveStudyLogDestination();
      navigate(destination, { replace: true });
    },
    [bridgeAdminSession, entryMode, logout, navigate, resolveStudyLogDestination]
  );

  const handleCredential = useCallback(
    async (credential: string) => {
      if (!acceptedTerms) {
        setAuthError('กรุณายอมรับเงื่อนไขการใช้งานก่อนเข้าสู่ระบบ');
        return;
      }

      setAuthInProgress(true);
      setAuthError(null);

      try {
        const user = await loginWithGoogle(credential);
        await handleLoginSuccess(user);
      } catch (error) {
        console.error(error);
        if (devLoginEnabled) {
          const email = getEmailFromCredential(credential);
          if (email) {
            try {
              const user = await loginWithDev(email);
              await handleLoginSuccess(user);
              return;
            } catch (devError) {
              console.error(devError);
            }
          }
        }
        const message =
          error instanceof Error ? error.message : 'ไม่สามารถเข้าสู่ระบบด้วย Google ได้ กรุณาลองใหม่อีกครั้ง';
        setAuthError(message);
      } finally {
        setAuthInProgress(false);
      }
    },
    [loginWithGoogle, loginWithDev, handleLoginSuccess, acceptedTerms, devLoginEnabled]
  );

  const handlePasswordLogin = useCallback(async () => {
    if (!acceptedTerms) {
      setAuthError('กรุณายอมรับเงื่อนไขการใช้งานก่อนเข้าสู่ระบบ');
      return;
    }

    const trimmedEmail = email.trim();
    if (!trimmedEmail || !password) {
      setAuthError('กรุณากรอกอีเมลและรหัสผ่านให้ครบถ้วน');
      return;
    }

    setAuthInProgress(true);
    setAuthError(null);

    try {
      const user = await loginWithPassword(trimmedEmail, password);
      await handleLoginSuccess(user);
    } catch (error) {
      console.error(error);
      const message =
        error instanceof Error ? error.message : 'ไม่สามารถเข้าสู่ระบบด้วยอีเมลได้ กรุณาลองใหม่อีกครั้ง';
      setAuthError(message);
    } finally {
      setAuthInProgress(false);
    }
  }, [acceptedTerms, email, password, loginWithPassword, handleLoginSuccess]);

  useEffect(() => {
    if (!googleLoginEnabled) {
      return;
    }
    if (googleClientId) {
      return;
    }

    let cancelled = false;
    setScriptError(null);

    api
      .get('/auth/google-config')
      .then(response => {
        if (cancelled) return;
        const clientId =
          typeof response.data?.client_id === 'string'
            ? response.data.client_id
            : typeof response.data?.clientId === 'string'
              ? response.data.clientId
              : '';

        if (clientId) {
          setGoogleClientId(clientId);
          setScriptError(null);
        } else {
          setScriptError('ยังไม่ได้ตั้งค่า GOOGLE_CLIENT_ID ในไฟล์ .env');
        }
      })
      .catch(() => {
        if (!cancelled) {
          setScriptError('ไม่สามารถโหลดการตั้งค่า Google ได้');
        }
      });

    return () => {
      cancelled = true;
    };
  }, [googleLoginEnabled, googleClientId]);

  useEffect(() => {
    if (!googleLoginEnabled) {
      setScriptError('ปิดการเข้าสู่ระบบด้วย Google ชั่วคราว ใช้โหมดทดสอบด้านล่างแทน');
      return;
    }

    if (!googleClientId) {
      return;
    }

    const initializeButton = () => {
      if (!window.google?.accounts?.id || !buttonContainerRef.current) {
        setScriptError('ไม่สามารถเตรียม Google Sign-In ได้');
        return;
      }

      buttonContainerRef.current.innerHTML = '';

      window.google.accounts.id.initialize({
        client_id: googleClientId,
        callback: (response: GoogleCredentialResponse) => {
          if (response.credential) {
            void handleCredential(response.credential);
          } else {
            setAuthError(
              `ไม่พบ token จาก Google (ตรวจสอบ Authorized JavaScript origins ให้มี: ${window.location.origin})`
            );
          }
        }
      });

      window.google.accounts.id.renderButton(buttonContainerRef.current, {
        theme: 'outline',
        size: 'large',
        shape: 'pill',
        text: 'signin_with',
        width: 320
      });

      setGsiReady(true);
    };

    const scriptId = 'google-identity-services';
    let script = document.getElementById(scriptId) as HTMLScriptElement | null;
    const handleScriptLoad = () => {
      setScriptError(null);
      initializeButton();
    };

    if (window.google?.accounts?.id) {
      initializeButton();
      return;
    }

    if (!script) {
      script = document.createElement('script');
      script.id = scriptId;
      script.src = 'https://accounts.google.com/gsi/client';
      script.async = true;
      script.defer = true;
      script.onload = handleScriptLoad;
      script.onerror = () => setScriptError('ไม่สามารถโหลดสคริปต์ Google Sign-In ได้');
      document.head.appendChild(script);
    } else {
      script.addEventListener('load', handleScriptLoad);
    }

    return () => {
      script?.removeEventListener?.('load', handleScriptLoad);
    };
  }, [handleCredential, googleLoginEnabled, googleClientId]);

  useEffect(() => {
    if (!googleLoginEnabled) return;
    if (gsiReady && acceptedTerms && window.google?.accounts?.id) {
      window.google.accounts.id.prompt();
    }
  }, [gsiReady, acceptedTerms, googleLoginEnabled]);

  return (
    <div className="card space-y-6">
      <div>
        <h2 className="text-2xl font-semibold text-slate-900">เข้าสู่ระบบ</h2>
        <p className="text-sm text-slate-500">
          ใช้บัญชี Google หรืออีเมล/รหัสผ่าน เพื่อเข้าสู่ระบบ Smart Learning Tracker
        </p>
        <p className="mt-2 text-xs text-slate-500">
          ยังไม่มีบัญชี?{' '}
          <Link to="/auth/register" className="text-blue-600 hover:underline">
            สมัครสมาชิก
          </Link>
        </p>
      </div>

      {scriptError && <div className="rounded-2xl bg-red-50 px-4 py-3 text-sm text-red-600">{scriptError}</div>}
      {authError && <div className="rounded-2xl bg-red-50 px-4 py-3 text-sm text-red-600">{authError}</div>}

      <div className="space-y-4">
        <div className="rounded-2xl border border-slate-200 bg-white p-3">
          <p className="mb-2 text-xs font-semibold text-slate-600">เลือกการเข้าใช้งาน</p>
          <div className="grid grid-cols-2 gap-2">
            <button
              type="button"
              onClick={() => setEntryMode('user')}
              className={`rounded-xl px-3 py-2 text-xs font-semibold transition ${
                entryMode === 'user' ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
              }`}
            >
              ผู้ใช้ทั่วไป
            </button>
            <button
              type="button"
              onClick={() => setEntryMode('admin')}
              className={`rounded-xl px-3 py-2 text-xs font-semibold transition ${
                entryMode === 'admin' ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
              }`}
            >
              ผู้ดูแลระบบ
            </button>
          </div>
        </div>

        <div className="flex items-start gap-3 rounded-2xl bg-slate-50 p-4">
          <input
            type="checkbox"
            id="terms"
            checked={acceptedTerms}
            onChange={event => setAcceptedTerms(event.target.checked)}
            className="mt-0.5 h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
          />
          <label htmlFor="terms" className="cursor-pointer text-sm text-slate-600">
            ฉันยอมรับ{' '}
            <a href="/terms" className="text-blue-600 hover:underline" target="_blank" rel="noopener noreferrer">
              เงื่อนไขการใช้งาน
            </a>{' '}
            และ{' '}
            <a href="/privacy" className="text-blue-600 hover:underline" target="_blank" rel="noopener noreferrer">
              นโยบายความเป็นส่วนตัว
            </a>
          </label>
        </div>

        {googleLoginEnabled ? (
          <>
            <div
              ref={buttonContainerRef}
              className="flex justify-center"
              style={{ opacity: acceptedTerms ? 1 : 0.5, pointerEvents: acceptedTerms ? 'auto' : 'none' }}
            />
            {authInProgress ? <p className="text-center text-sm text-slate-500">กำลังตรวจสอบข้อมูลกับ Google...</p> : null}
          </>
        ) : null}
      </div>

      <div className="rounded-2xl border border-slate-200 bg-white p-4 text-sm text-slate-600">
        <p className="font-medium text-slate-700">เข้าสู่ระบบด้วยอีเมล/รหัสผ่าน</p>
        <p className="mt-1 text-xs text-slate-500">สำหรับบัญชีที่ตั้งรหัสผ่านไว้แล้ว</p>

        <div className="mt-3 space-y-3">
          <input
            type="email"
            value={email}
            onChange={event => setEmail(event.target.value)}
            placeholder="อีเมล"
            className="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700 outline-none focus:border-blue-500"
          />
          <input
            type="password"
            value={password}
            onChange={event => setPassword(event.target.value)}
            placeholder="รหัสผ่าน"
            className="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700 outline-none focus:border-blue-500"
          />
          <button
            type="button"
            onClick={handlePasswordLogin}
            disabled={authInProgress || !acceptedTerms || !email.trim() || !password}
            className="w-full rounded-full bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60"
          >
            {authInProgress ? 'กำลังเข้าสู่ระบบ...' : 'เข้าสู่ระบบ'}
          </button>
        </div>

        {!acceptedTerms ? (
          <p className="mt-2 text-xs text-slate-500">* ต้องยอมรับเงื่อนไขก่อนจึงจะเข้าสู่ระบบได้</p>
        ) : null}
      </div>

    </div>
  );
};
  const isLocalhost =
    typeof window !== 'undefined' &&
    (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1');
