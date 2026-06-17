import { useCallback, useEffect, useRef, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useAuth } from '../../context/AuthContext';
import { LAST_SUBJECT_KEY } from '../../constants/storage';
import { api } from '../../services/api';
import type { GoogleCredentialResponse } from '../../types/global';

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

const buildAdminUrl = (path: string) => {
  const base = String(ADMIN_BASE_URL || '').replace(/\/+$/, '');
  const normalizedPath = path.startsWith('/') ? path : `/${path}`;
  if (!base) return normalizedPath;
  if (base.endsWith('.php')) return base;
  return `${base}${normalizedPath}`;
};

export const RegisterPage = () => {
  const { loginWithGoogle, registerWithPassword } = useAuth();
  const navigate = useNavigate();
  const buttonContainerRef = useRef<HTMLDivElement | null>(null);
  const initializedClientIdRef = useRef<string | null>(null);
  const [scriptError, setScriptError] = useState<string | null>(null);
  const [authError, setAuthError] = useState<string | null>(null);
  const [authInProgress, setAuthInProgress] = useState(false);
  const [acceptedTerms, setAcceptedTerms] = useState(false);
  const [gsiReady, setGsiReady] = useState(false);
  const [registerName, setRegisterName] = useState('');
  const [registerEmail, setRegisterEmail] = useState('');
  const [registerPassword, setRegisterPassword] = useState('');
  const [registerEducationLevel, setRegisterEducationLevel] = useState('');
  const [googleClientId, setGoogleClientId] = useState(
    () => (isLocalhost ? '' : (import.meta.env.VITE_GOOGLE_CLIENT_ID ?? ''))
  );
  const googleLoginEnabled = import.meta.env.VITE_GOOGLE_LOGIN_ENABLED !== 'false';

  const resolveStudyLogDestination = useCallback(async (): Promise<string> => {
    return '/';
  }, []);

  const handleLoginSuccess = useCallback(
    async (user: { role?: string | null }) => {
      localStorage.removeItem(LAST_SUBJECT_KEY);

      if (user?.role === 'admin') {
        window.location.assign(buildAdminUrl('/backend/admin.php'));
        return;
      }

      const destination = await resolveStudyLogDestination();
      navigate(destination, { replace: true });
    },
    [navigate, resolveStudyLogDestination]
  );

  const handleCredential = useCallback(
    async (credential: string) => {
      if (!acceptedTerms) {
        setAuthError('กรุณายอมรับเงื่อนไขการใช้งานก่อนสมัครสมาชิก');
        return;
      }

      setAuthInProgress(true);
      setAuthError(null);

      try {
        const user = await loginWithGoogle(credential);
        await handleLoginSuccess(user);
      } catch (error) {
        console.error(error);
        const message =
          error instanceof Error ? error.message : 'ไม่สามารถสมัครสมาชิกด้วย Google ได้ กรุณาลองใหม่อีกครั้ง';
        setAuthError(message);
      } finally {
        setAuthInProgress(false);
      }
    },
    [loginWithGoogle, handleLoginSuccess, acceptedTerms]
  );

  const handleRegister = useCallback(async () => {
    if (!acceptedTerms) {
      setAuthError('กรุณายอมรับเงื่อนไขการใช้งานก่อนสมัครสมาชิก');
      return;
    }

    const trimmedName = registerName.trim();
    const trimmedEmail = registerEmail.trim();
    const education = registerEducationLevel.trim();

    if (!trimmedName || !trimmedEmail || !registerPassword) {
      setAuthError('กรุณากรอกข้อมูลให้ครบถ้วน');
      return;
    }

    if (registerPassword.length < 8) {
      setAuthError('รหัสผ่านต้องมีอย่างน้อย 8 ตัวอักษร');
      return;
    }

    setAuthInProgress(true);
    setAuthError(null);

    try {
      const user = await registerWithPassword({
        name: trimmedName,
        email: trimmedEmail,
        password: registerPassword,
        password_confirmation: registerPassword,
        education_level: education || null
      });
      await handleLoginSuccess(user);
    } catch (error) {
      console.error(error);
      const message =
        error instanceof Error ? error.message : 'ไม่สามารถสมัครสมาชิกได้ กรุณาลองใหม่อีกครั้ง';
      setAuthError(message);
    } finally {
      setAuthInProgress(false);
    }
  }, [
    acceptedTerms,
    registerName,
    registerEmail,
    registerPassword,
    registerEducationLevel,
    registerWithPassword,
    handleLoginSuccess
  ]);

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
      setScriptError('ปิดการเข้าสู่ระบบด้วย Google ชั่วคราว ใช้การสมัครด้วยอีเมลแทน');
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

      if (initializedClientIdRef.current !== googleClientId) {
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
        initializedClientIdRef.current = googleClientId;
      }

      window.google.accounts.id.renderButton(buttonContainerRef.current, {
        theme: 'outline',
        size: 'large',
        shape: 'pill',
        text: 'signup_with',
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
    <div className="space-y-6">
      <div>
        <h2 className="text-2xl font-semibold text-[color:var(--text)]">สมัครสมาชิกใหม่</h2>
        <p className="text-sm text-muted">
          สร้างบัญชีด้วยอีเมลของคุณ หรือสมัครผ่าน Google เพื่อเริ่มใช้งาน
        </p>
        <p className="mt-2 text-xs text-muted">
          มีบัญชีอยู่แล้ว?{' '}
          <Link to="/auth/login" className="text-accent hover:underline">
            เข้าสู่ระบบ
          </Link>
        </p>
      </div>

      {scriptError && (
        <div className="rounded-2xl border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-sm text-rose-600">
          {scriptError}
        </div>
      )}
      {authError && (
        <div className="rounded-2xl border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-sm text-rose-600">
          {authError}
        </div>
      )}

      <div className="space-y-4">
        {googleLoginEnabled ? (
          <>
            <div
              ref={buttonContainerRef}
              className="flex justify-center"
              style={{ opacity: acceptedTerms ? 1 : 0.5, pointerEvents: acceptedTerms ? 'auto' : 'none' }}
            />
            {authInProgress ? <p className="text-center text-sm text-muted">กำลังตรวจสอบข้อมูลกับ Google...</p> : null}
          </>
        ) : null}
      </div>

      <div className="rounded-2xl border border-muted surface p-4 text-sm text-muted shadow-soft">
        <p className="font-medium text-[color:var(--text)]">สร้างบัญชีด้วยอีเมล</p>
        <p className="mt-1 text-xs text-muted">กรอกข้อมูลให้ครบถ้วน แล้วเริ่มใช้งานได้ทันที</p>

        <div className="mt-3 space-y-3">
          <input
            type="text"
            value={registerName}
            onChange={event => setRegisterName(event.target.value)}
            placeholder="ชื่อผู้ใช้"
            className="w-full rounded-xl border border-muted surface-2 px-3 py-2 text-sm text-[color:var(--text)] outline-none transition focus:border-[color:var(--accent)] focus:ring-2 focus:ring-[color:var(--accent)]/30"
          />
          <input
            type="email"
            value={registerEmail}
            onChange={event => setRegisterEmail(event.target.value)}
            placeholder="อีเมล"
            className="w-full rounded-xl border border-muted surface-2 px-3 py-2 text-sm text-[color:var(--text)] outline-none transition focus:border-[color:var(--accent)] focus:ring-2 focus:ring-[color:var(--accent)]/30"
          />
          <input
            type="password"
            value={registerPassword}
            onChange={event => setRegisterPassword(event.target.value)}
            placeholder="รหัสผ่าน (อย่างน้อย 8 ตัวอักษร)"
            className="w-full rounded-xl border border-muted surface-2 px-3 py-2 text-sm text-[color:var(--text)] outline-none transition focus:border-[color:var(--accent)] focus:ring-2 focus:ring-[color:var(--accent)]/30"
          />
          <input
            type="text"
            value={registerEducationLevel}
            onChange={event => setRegisterEducationLevel(event.target.value)}
            placeholder="ระดับการศึกษา (ไม่บังคับ)"
            className="w-full rounded-xl border border-muted surface-2 px-3 py-2 text-sm text-[color:var(--text)] outline-none transition focus:border-[color:var(--accent)] focus:ring-2 focus:ring-[color:var(--accent)]/30"
          />
          <button
            type="button"
            onClick={handleRegister}
            disabled={
              authInProgress ||
              !acceptedTerms ||
              !registerName.trim() ||
              !registerEmail.trim() ||
              !registerPassword
            }
            className="btn-primary w-full text-sm disabled:cursor-not-allowed disabled:opacity-60"
          >
            {authInProgress ? 'กำลังสมัครสมาชิก...' : 'สมัครสมาชิก'}
          </button>
        </div>

        <div className="mt-4 flex items-start gap-3 rounded-2xl border border-muted surface-2 p-4">
          <input
            type="checkbox"
            id="terms"
            checked={acceptedTerms}
            onChange={event => setAcceptedTerms(event.target.checked)}
            className="mt-0.5 h-4 w-4 rounded border-muted text-[color:var(--accent)] focus:ring-2 focus:ring-[color:var(--accent)]/40 focus:ring-offset-2"
          />
          <label htmlFor="terms" className="cursor-pointer text-sm text-muted">
            ฉันยอมรับ{' '}
            <a href="/terms" className="text-accent hover:underline" target="_blank" rel="noopener noreferrer">
              เงื่อนไขการใช้งาน
            </a>{' '}
            และ{' '}
            <a href="/privacy" className="text-accent hover:underline" target="_blank" rel="noopener noreferrer">
              นโยบายความเป็นส่วนตัว
            </a>
          </label>
        </div>

        {!acceptedTerms ? <p className="mt-2 text-xs text-muted">* ต้องยอมรับเงื่อนไขก่อนจึงจะสมัครสมาชิกได้</p> : null}
      </div>
    </div>
  );
};
  const isLocalhost =
    typeof window !== 'undefined' &&
    (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1');
