import { createContext, useCallback, useContext, useMemo, useState } from 'react';
import type { PropsWithChildren } from 'react';

type AppAlertType = 'success' | 'error' | 'info';

type AppAlertItem = {
  id: number;
  type: AppAlertType;
  text: string;
};

type AppAlertContextValue = {
  showAlert: (type: AppAlertType, text: string) => void;
  success: (text: string) => void;
  error: (text: string) => void;
  info: (text: string) => void;
};

const AppAlertContext = createContext<AppAlertContextValue | undefined>(undefined);

export const AppAlertProvider = ({ children }: PropsWithChildren) => {
  const [alerts, setAlerts] = useState<AppAlertItem[]>([]);

  const dismissAlert = useCallback((id: number) => {
    setAlerts(prev => prev.filter(item => item.id !== id));
  }, []);

  const showAlert = useCallback((type: AppAlertType, text: string) => {
    const id = Date.now() + Math.floor(Math.random() * 1000);
    setAlerts(prev => [...prev, { id, type, text }].slice(-3));
    window.setTimeout(() => dismissAlert(id), 3200);
  }, [dismissAlert]);

  const value = useMemo<AppAlertContextValue>(() => ({
    showAlert,
    success: (text: string) => showAlert('success', text),
    error: (text: string) => showAlert('error', text),
    info: (text: string) => showAlert('info', text),
  }), [showAlert]);

  return (
    <AppAlertContext.Provider value={value}>
      {children}

      <div className="pointer-events-none fixed left-1/2 top-5 z-[90] flex w-[min(92vw,380px)] -translate-x-1/2 flex-col gap-3 sm:top-6">
        {alerts.map(alert => (
          <div key={alert.id} className="pointer-events-auto">
            <div
              className={`relative overflow-hidden rounded-[22px] border bg-white/96 px-4 py-4 shadow-[0_18px_60px_rgba(15,23,42,0.18)] backdrop-blur-xl ${
                alert.type === 'success'
                  ? 'border-emerald-100'
                  : alert.type === 'error'
                    ? 'border-rose-100'
                    : 'border-sky-100'
              }`}
              role="status"
              aria-live="polite"
            >
              <div
                className={`absolute inset-x-0 bottom-0 h-1 ${
                  alert.type === 'success'
                    ? 'bg-emerald-400'
                    : alert.type === 'error'
                      ? 'bg-rose-400'
                      : 'bg-sky-400'
                }`}
              />
              <div className="flex items-start gap-3">
                <div
                  className={`mt-0.5 flex h-11 w-11 shrink-0 items-center justify-center rounded-full text-base font-bold shadow-sm ${
                    alert.type === 'success'
                      ? 'bg-emerald-500 text-white'
                      : alert.type === 'error'
                        ? 'bg-rose-500 text-white'
                        : 'bg-sky-500 text-white'
                  }`}
                >
                  {alert.type === 'success' ? '✓' : alert.type === 'error' ? '×' : 'i'}
                </div>
                <div className="min-w-0 flex-1">
                  <p className="text-[15px] font-semibold text-slate-900">
                    {alert.type === 'success' ? 'บันทึกสำเร็จ' : alert.type === 'error' ? 'เกิดข้อผิดพลาด' : 'แจ้งเตือน'}
                  </p>
                  <p className="mt-1 text-sm leading-6 text-slate-600">{alert.text}</p>
                </div>
                <button
                  type="button"
                  onClick={() => dismissAlert(alert.id)}
                  className="rounded-full px-2 py-1 text-xs font-semibold text-slate-400 transition hover:bg-slate-100 hover:text-slate-700"
                >
                  ปิด
                </button>
              </div>
            </div>
          </div>
        ))}
      </div>
    </AppAlertContext.Provider>
  );
};

export const useAppAlert = () => {
  const ctx = useContext(AppAlertContext);
  if (!ctx) throw new Error('useAppAlert must be used within AppAlertProvider');
  return ctx;
};
