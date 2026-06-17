import axios from 'axios';
import { useEffect, useMemo, useRef, useState } from 'react';
import { api, legacyApi } from '../../services/api';
import { format, parseISO, isValid } from 'date-fns';
import { th } from 'date-fns/locale';
import {
  CalendarDays,
  Check,
  Clock,
  Edit2,
  Plus,
  RefreshCw,
  Settings,
  Sparkles,
  Trash2,
} from 'lucide-react';
import { useAuth } from '../../context/AuthContext';
import {
  addLocalNotification,
  deleteLocalNotification,
  emitRemoteNotificationsChanged,
  markLocalNotificationAsRead,
  updateLocalNotificationTime,
} from '../../services/localNotifications';
import { useSemesterOptions } from '../../hooks/useSemesterOptions';
import { filterBySemester, toNumberOrNull } from '../../utils/semester';
import { subscribeSubjectsUpdated } from '../../utils/subjectSync';

type Subject = {
  id: number;
  name: string;
  semester_id?: number | null;
  semester?: number | null;
  academic_year?: number | null;
};

type StudyNotification = {
  id: number;
  title: string;
  message: string;
  notify_at: string;
  delivered_at?: string | null;
  is_read: boolean;
  type: string;
  subject_id?: number | null;
  status?: string | null;
  channel?: string | null;
};

type NotificationSettings = {
  send_time?: string;
  timezone?: string;
  email_enabled?: boolean;
};

type Time24Parts = {
  hour24: string;
  minute: string;
};

type TimePickerProps = {
  value?: string;
  onChange: (value: string) => void;
  className?: string;
  compact?: boolean;
};
type TimeSelectProps = {
  value: string;
  options: string[];
  onChange: (value: string) => void;
  widthClass?: string;
};

const NOTIFY_TIME_STORAGE_KEY = 'slt::daily-notify-time';
const NOTIFY_TIME_EVENT = 'slt:daily-notify-time:changed';

const shouldFallbackRequest = (err: unknown) => {
  if (!axios.isAxiosError(err)) return false;
  const status = err.response?.status;
  return status === 404 || status === 405 || status === 500;
};

const isMissingTableResponse = (payload: unknown) => {
  if (!payload || typeof payload !== 'object') return false;
  const message = (payload as { message?: string }).message;
  return typeof message === 'string' && message.toLowerCase().includes('missing');
};

const requestWithFallback = async <T,>(
  primary: () => Promise<T>,
  fallback: () => Promise<T>,
  isValidResponse?: (response: T) => boolean
): Promise<T> => {
  try {
    const response = await primary();
    if (isValidResponse && !isValidResponse(response)) {
      return await fallback();
    }
    return response;
  } catch (err) {
    if (!shouldFallbackRequest(err)) {
      throw err;
    }
    return fallback();
  }
};

const unwrapCollection = (payload: any) => {
  if (Array.isArray(payload)) return payload;
  if (Array.isArray(payload?.data)) return payload.data;
  if (Array.isArray(payload?.data?.data)) return payload.data.data;
  return [];
};

const parseNotifyAt = (value: string) => {
  if (!value) return new Date();

  const trimmed = value.trim();
  if (!trimmed) return new Date();

  const match = trimmed.match(
    /^(\d{4})[-/](\d{2})[-/](\d{2})[ T](\d{2}):(\d{2})(?::(\d{2}))?/
  );
  if (match) {
    const [, year, month, day, hour, minute, second] = match;
    return new Date(
      Number(year),
      Number(month) - 1,
      Number(day),
      Number(hour),
      Number(minute),
      Number(second || '0')
    );
  }

  // Fallback for timezone-aware ISO when date-time part is not in the expected format.
  if (/[zZ]|[+-]\d{2}:\d{2}$/.test(trimmed)) {
    const parsedIso = parseISO(trimmed);
    if (isValid(parsedIso)) return parsedIso;
  }

  let normalized = trimmed.replace(' ', 'T');
  const parsed = parseISO(normalized);
  if (isValid(parsed)) return parsed;

  const fallback = new Date(trimmed);
  return isValid(fallback) ? fallback : new Date();
};

const normalizeTimeValue = (value?: string, fallback = '00:00') => {
  if (!value) return fallback;
  const trimmed = value.trim();
  const match = trimmed.match(/^(\d{1,2}):(\d{2})/);
  if (!match) return fallback;
  const hour = Number(match[1]);
  const minute = Number(match[2]);
  if (!Number.isFinite(hour) || !Number.isFinite(minute) || hour < 0 || hour > 23 || minute < 0 || minute > 59) {
    return fallback;
  }
  return `${String(hour).padStart(2, '0')}:${String(minute).padStart(2, '0')}`;
};

const persistNotifyTime = (value: string) => {
  if (typeof window === 'undefined') return;
  window.localStorage.setItem(NOTIFY_TIME_STORAGE_KEY, value);
  window.dispatchEvent(new CustomEvent(NOTIFY_TIME_EVENT, { detail: { value } }));
};

const hour24Options = Array.from({ length: 24 }, (_, idx) => String(idx).padStart(2, '0'));
const minuteOptions = Array.from({ length: 60 }, (_, idx) => String(idx).padStart(2, '0'));

const toTime24Parts = (value?: string): Time24Parts => {
  const normalized = normalizeTimeValue(value, '00:00');
  const [hourRaw, minuteRaw] = normalized.split(':');
  return {
    hour24: String(Number(hourRaw ?? '0')).padStart(2, '0'),
    minute: minuteRaw ?? '00',
  };
};

const fromTime24Parts = (parts: Time24Parts): string => {
  const hour24 = Number(parts.hour24);
  const minute = Number(parts.minute);
  if (!Number.isFinite(hour24) || hour24 < 0 || hour24 > 23) return '00:00';
  if (!Number.isFinite(minute) || minute < 0 || minute > 59) return '00:00';
  return `${String(hour24).padStart(2, '0')}:${String(minute).padStart(2, '0')}`;
};

const TimeSelect = ({ value, options, onChange, widthClass = 'w-[72px]' }: TimeSelectProps) => (
  <div className={`relative ${widthClass}`}>
    <select
      value={value}
      onChange={event => onChange(event.target.value)}
      className="h-8 w-full appearance-none rounded-lg border px-3 pr-7 text-sm font-bold shadow-sm outline-none transition"
      style={{
        borderColor: 'var(--border)',
        background: 'color-mix(in srgb, var(--surface) 92%, var(--surface-2) 8%)',
        color: 'var(--text)',
      }}
    >
      {options.map(option => (
        <option key={option} value={option}>
          {option}
        </option>
      ))}
    </select>
    <svg
      viewBox="0 0 20 20"
      className="pointer-events-none absolute right-2 top-1/2 h-4 w-4 -translate-y-1/2"
      style={{ color: 'var(--muted)' }}
      fill="currentColor"
      aria-hidden="true"
    >
      <path d="M5.5 7.5L10 12l4.5-4.5" />
    </svg>
  </div>
);

const IPhoneTimePicker = ({ value, onChange, className = '', compact = false }: TimePickerProps) => {
  const parts = toTime24Parts(value);
  const controlHeight = compact ? 'h-9' : 'h-11';

  return (
    <div
      className={`inline-flex items-center gap-2 rounded-xl border px-2 ${controlHeight} ${
        ''
      } ${className}`}
      style={{
        borderColor: 'var(--border)',
        background: 'color-mix(in srgb, var(--surface) 90%, var(--surface-2) 10%)',
      }}
      role="group"
      aria-label="เลือกเวลา"
    >
      <TimeSelect
        value={parts.hour24}
        options={hour24Options}
        onChange={nextHour =>
          onChange(
            fromTime24Parts({
              ...parts,
              hour24: nextHour,
            })
          )
        }
      />
      <span className="text-sm font-semibold" style={{ color: 'var(--text)' }}>:</span>
      <TimeSelect
        value={parts.minute}
        options={minuteOptions}
        onChange={nextMinute =>
          onChange(
            fromTime24Parts({
              ...parts,
              minute: nextMinute,
            })
          )
        }
      />
    </div>
  );
};

const normalizeDateInputValue = (value?: string | null): string | null => {
  const raw = String(value ?? '').trim();
  if (!raw) return null;
  const normalizedSlash = raw.replace(/\/{2,}/g, '/');

  const isoMatch = normalizedSlash.match(/^(\d{4})-(\d{2})-(\d{2})$/);
  if (isoMatch) {
    const year = Number(isoMatch[1]);
    const month = Number(isoMatch[2]);
    const day = Number(isoMatch[3]);
    const date = new Date(year, month - 1, day);
    if (date.getFullYear() === year && date.getMonth() === month - 1 && date.getDate() === day) {
      return `${isoMatch[1]}-${isoMatch[2]}-${isoMatch[3]}`;
    }
    return null;
  }

  const slashMatch = normalizedSlash.match(/^(\d{1,2})\/(\d{1,2})\/(\d{2}|\d{4})$/);
  if (!slashMatch) return null;
  const day = Number(slashMatch[1]);
  const month = Number(slashMatch[2]);
  const yearRaw = slashMatch[3];
  const year = yearRaw.length === 2 ? 2000 + Number(yearRaw) : Number(yearRaw);
  if (!Number.isFinite(day) || !Number.isFinite(month) || !Number.isFinite(year)) return null;

  const date = new Date(year, month - 1, day);
  if (date.getFullYear() !== year || date.getMonth() !== month - 1 || date.getDate() !== day) {
    return null;
  }

  return `${String(year).padStart(4, '0')}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
};

const formatDateDisplayValue = (isoDate?: string | null): string => {
  const normalized = normalizeDateInputValue(isoDate);
  if (!normalized) return String(isoDate ?? '');
  const [year, month, day] = normalized.split('-');
  return `${day}/${month}/${year}`;
};

export const NotificationPage = () => {
  const { user } = useAuth();
  const [notifications, setNotifications] = useState<StudyNotification[]>([]);
  const [subjects, setSubjects] = useState<Subject[]>([]);
  const [selectedSemesterKey, setSelectedSemesterKey] = useState('all');
  const [subjectsLoading, setSubjectsLoading] = useState(false);
  const [createOpen, setCreateOpen] = useState(false);
  const [createSubjectId, setCreateSubjectId] = useState('');
  const [createDate, setCreateDate] = useState(() => formatDateDisplayValue(format(new Date(), 'yyyy-MM-dd')));
  const [createTime, setCreateTime] = useState('00:00');
  const [createSaving, setCreateSaving] = useState(false);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [notifyTime, setNotifyTime] = useState('00:00');
  const [editingId, setEditingId] = useState<number | null>(null);
  const [editingTime, setEditingTime] = useState('00:00');
  const [editingDate, setEditingDate] = useState('');
  const [savingId, setSavingId] = useState<number | null>(null);
  const semesterOptions = useSemesterOptions();
  const createDatePickerRef = useRef<HTMLInputElement | null>(null);
  const editDatePickerRef = useRef<HTMLInputElement | null>(null);

  const isScheduleTimedNotification = (notification: StudyNotification) =>
    notification.type === 'today_schedule' ||
    notification.type === 'tomorrow_schedule' ||
    notification.type === 'schedule_day' ||
    notification.type === 'schedule_range';

  const isStudyNotification = (notification: StudyNotification) =>
    isScheduleTimedNotification(notification) || notification.type === 'subject_reminder';

  const baseNotifications = useMemo(() => notifications, [notifications]);
  const allNotifications = useMemo(
    () => baseNotifications.filter(isStudyNotification),
    [baseNotifications]
  );
  const filteredSubjects = useMemo(() => filterBySemester(subjects, selectedSemesterKey), [subjects, selectedSemesterKey]);

  const openNativeDatePicker = (target: HTMLInputElement | null) => {
    if (!target) return;
    const picker = target as HTMLInputElement & { showPicker?: () => void };
    if (typeof picker.showPicker === 'function') {
      picker.showPicker();
      return;
    }
    target.focus();
    target.click();
  };

  const isLocalNotification = (notification: StudyNotification) => notification.channel === 'local';

  const resolveNotification = (id: number) => notifications.find(item => item.id === id) ?? null;

  const formatNotifyLabel = (value?: string | null) => {
    if (!value) return '';
    return format(parseNotifyAt(value), 'd MMM yyyy HH:mm', { locale: th });
  };
  const formatNotifyTimeOnly = (value?: string | null) => {
    if (!value) return '--:--';
    return format(parseNotifyAt(value), 'HH:mm');
  };

  const buildNotifyAtValue = (base: Date, dateValue?: string, timeValue?: string) => {
    const date = dateValue || format(base, 'yyyy-MM-dd');
    const time = timeValue || format(base, 'HH:mm');
    return `${date} ${time}:00`;
  };

  const loadSubjects = async () => {
    setSubjectsLoading(true);
    try {
      const res = await requestWithFallback(
        () => api.get('/subjects'),
        () => legacyApi.get('/subjects')
      );
      const items = unwrapCollection((res as any).data) as any[];
      const normalized: Subject[] = items
        .map(item => {
          if (!item || typeof item !== 'object') return null;
          const rawId = (item as any).id;
          const name = (item as any).name ?? (item as any).subject_name;
          const id = typeof rawId === 'string' ? Number(rawId) : rawId;
          if (!Number.isFinite(id) || !name) return null;
          return {
            id,
            name: String(name),
            semester_id: toNumberOrNull((item as any).semester_id),
            semester: toNumberOrNull((item as any).semester),
            academic_year: toNumberOrNull((item as any).academic_year),
          };
        })
        .filter(Boolean) as Subject[];
      setSubjects(normalized);
      setCreateSubjectId(prev => (prev && normalized.some(subject => String(subject.id) === prev) ? prev : ''));
    } catch {
      // ignore
    } finally {
      setSubjectsLoading(false);
    }
  };

  const fetchNotifications = async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await requestWithFallback(
        () => api.get<StudyNotification[]>('/notifications'),
        () => legacyApi.get<StudyNotification[]>('/notifications'),
        (response: any) => !isMissingTableResponse(response?.data)
      );
      setNotifications(unwrapCollection(res.data) as StudyNotification[]);
    } catch (err: any) {
      setError(err.response?.data?.message ?? 'โหลดการแจ้งเตือนไม่สำเร็จ');
    } finally {
      setLoading(false);
    }
  };

  const fetchSettings = async () => {
    try {
      const res = await requestWithFallback(
        () => api.get<NotificationSettings>('/notifications/settings'),
        () => legacyApi.get<NotificationSettings>('/notifications/settings'),
        (response: any) => !isMissingTableResponse(response?.data)
      );
      const normalized = normalizeTimeValue(res.data?.send_time);
      setNotifyTime(normalized);
      persistNotifyTime(normalized);
    } catch (err) {
      // ignore
    }
  };

  useEffect(() => {
    fetchNotifications();
    fetchSettings();
    loadSubjects();
  }, []);

  useEffect(() => subscribeSubjectsUpdated(() => {
    void loadSubjects();
  }), []);

  useEffect(() => {
    const handleArchiveRefresh = () => {
      void fetchNotifications();
    };
    window.addEventListener('slt:archive-refresh', handleArchiveRefresh as EventListener);
    return () => window.removeEventListener('slt:archive-refresh', handleArchiveRefresh as EventListener);
  }, []);

  useEffect(() => {
    if (!createSubjectId) return;
    if (!filteredSubjects.some(subject => String(subject.id) === createSubjectId)) {
      setCreateSubjectId('');
    }
  }, [createSubjectId, filteredSubjects]);

  const updateNotifyTime = async (value: string) => {
    if (!value) return;
    const normalizedTime = normalizeTimeValue(value);
    const previousNotifications = notifications;

    setNotifications(prev =>
      prev.map(notification => {
        if (!isScheduleTimedNotification(notification)) {
          return notification;
        }

        const current = parseNotifyAt(notification.notify_at);
        return {
          ...notification,
          notify_at: buildNotifyAtValue(current, format(current, 'yyyy-MM-dd'), normalizedTime),
        };
      })
    );

    try {
      await requestWithFallback(
        () =>
          api.put('/notifications/settings', {
            send_time: normalizedTime
          }),
        () =>
          legacyApi.put('/notifications/settings', {
            send_time: normalizedTime
          }),
        (response: any) => !isMissingTableResponse(response?.data)
      );
      persistNotifyTime(normalizedTime);
      await fetchNotifications();
    } catch (err: any) {
      setNotifications(previousNotifications);
      setError(err.response?.data?.message ?? 'บันทึกเวลาแจ้งเตือนไม่สำเร็จ');
    }
  };

  const startEditTime = (notification: StudyNotification) => {
    const parsed = parseNotifyAt(notification.notify_at);
    setEditingId(notification.id);
    setEditingTime(format(parsed, 'HH:mm'));
    setEditingDate(formatDateDisplayValue(format(parsed, 'yyyy-MM-dd')));
  };

  const cancelEditTime = () => {
    setEditingId(null);
    setEditingTime('00:00');
    setEditingDate('');
  };

  const saveEditTime = async (id: number) => {
    if (!editingTime) return;
    const editingDateIso = normalizeDateInputValue(editingDate);
    if (!editingDateIso) {
      setError('รูปแบบวันที่ไม่ถูกต้อง (วัน/เดือน/ปี)');
      return;
    }
    const current = resolveNotification(id);
    if (current && isLocalNotification(current)) {
      const base = parseNotifyAt(current.notify_at);
      const nextNotifyAt = buildNotifyAtValue(base, editingDateIso, normalizeTimeValue(editingTime));
      updateLocalNotificationTime(user?.id, id, nextNotifyAt);
      setNotifications(prev =>
        prev.map(item => (item.id === id ? { ...item, notify_at: nextNotifyAt } : item))
      );
      setEditingId(null);
      return;
    }
    const previousNotifications = notifications;
    const currentBase = current ? parseNotifyAt(current.notify_at) : new Date();
    const nextNotifyAt = buildNotifyAtValue(currentBase, editingDateIso, normalizeTimeValue(editingTime));
    setNotifications(prev => prev.map(n => (n.id === id ? { ...n, notify_at: nextNotifyAt } : n)));
    setSavingId(id);
    setError(null);
    try {
      const res = await requestWithFallback(
        () =>
          api.post<StudyNotification>(`/notifications/${id}/time`, {
            notify_time: normalizeTimeValue(editingTime) || null,
            notify_date: editingDateIso || null
          }),
        () =>
          api.patch<StudyNotification>(`/notifications/${id}/time`, {
            notify_time: normalizeTimeValue(editingTime) || null,
            notify_date: editingDateIso || null
          }),
        (response: any) => !isMissingTableResponse(response?.data)
      );
      const updated = (res as any)?.data?.data ?? (res as any)?.data ?? null;
      if (updated?.notify_at) {
        setNotifications(prev => prev.map(n => (n.id === id ? { ...n, notify_at: updated.notify_at } : n)));
      }
      setEditingId(null);
      emitRemoteNotificationsChanged();
      await fetchNotifications();
    } catch (err: any) {
      setNotifications(previousNotifications);
      setError(err.response?.data?.message ?? 'แก้ไขเวลาแจ้งเตือนไม่สำเร็จ');
    } finally {
      setSavingId(null);
    }
  };

  const markAsRead = async (notification: StudyNotification) => {
    if (isLocalNotification(notification)) {
      markLocalNotificationAsRead(user?.id, notification.id);
      setNotifications(prev => prev.map(n => (n.id === notification.id ? { ...n, is_read: true } : n)));
      return;
    }
    try {
      await requestWithFallback(
        () => api.post(`/notifications/${notification.id}/read`),
        () => api.patch(`/notifications/${notification.id}/read`),
        (response: any) => !isMissingTableResponse(response?.data)
      );
      setNotifications(prev => prev.map(n => (n.id === notification.id ? { ...n, is_read: true } : n)));
    } catch (err) {
      // ignore
    }
  };

  const deleteNotification = async (notification: StudyNotification) => {
    if (isLocalNotification(notification)) {
      deleteLocalNotification(user?.id, notification.id);
      setNotifications(prev => prev.filter(n => n.id !== notification.id));
      return;
    }
    try {
      await requestWithFallback(
        () => api.delete(`/notifications/${notification.id}`),
        () => api.post(`/notifications/${notification.id}/delete`),
        (response: any) => !isMissingTableResponse(response?.data)
      );
      setNotifications(prev => prev.filter(n => n.id !== notification.id));
      emitRemoteNotificationsChanged();
    } catch (err) {
      setError('ลบการแจ้งเตือนไม่สำเร็จ');
    }
  };

  const createSubjectReminder = async () => {
    const subjectId = Number.parseInt(createSubjectId, 10);
    if (!Number.isFinite(subjectId)) {
      setError('กรุณาเลือกวิชา');
      return;
    }
    const createDateIso = normalizeDateInputValue(createDate);
    if (!createDateIso) {
      setError('กรุณาเลือกวันที่แจ้งเตือน');
      return;
    }
    if (!createTime) {
      setError('กรุณาเลือกเวลาแจ้งเตือน');
      return;
    }

    const subject = subjects.find(s => s.id === subjectId);
    const subjectName = subject?.name ?? 'รายวิชา';
    const notifyAt = buildNotifyAtValue(new Date(), createDateIso, normalizeTimeValue(createTime));
    const notifyLabel = format(parseNotifyAt(notifyAt), 'd MMM yyyy HH:mm', { locale: th });

    setCreateSaving(true);
    setError(null);
    try {
      await requestWithFallback(
        () =>
          api.post('/notifications', {
            subject_id: subjectId,
            type: 'subject_reminder',
            title: `แจ้งเตือนวิชา: ${subjectName}`,
            message: `แจ้งเตือนว่า ${format(parseNotifyAt(notifyAt), 'd MMM yyyy', { locale: th })} เวลา ${normalizeTimeValue(createTime)} น. มีเรียนวิชา "${subjectName}"`,
            notify_at: notifyAt,
            channel: 'in_app'
          }),
        () =>
          legacyApi.post('/notifications', {
            subject_id: subjectId,
            type: 'subject_reminder',
            title: `แจ้งเตือนวิชา: ${subjectName}`,
            message: `แจ้งเตือนว่า ${format(parseNotifyAt(notifyAt), 'd MMM yyyy', { locale: th })} เวลา ${normalizeTimeValue(createTime)} น. มีเรียนวิชา "${subjectName}"`,
            notify_at: notifyAt,
            channel: 'in_app'
          }),
        (response: any) => !isMissingTableResponse(response?.data)
      );
      setCreateOpen(false);
      emitRemoteNotificationsChanged();
      await fetchNotifications();
    } catch (err: any) {
      // Fallback to local in-app notification so user still receives reminders.
      const local = addLocalNotification(user?.id, {
        title: `แจ้งเตือนวิชา: ${subjectName}`,
        message: `ถึงเวลาเรียน "${subjectName}" แล้ว (${notifyLabel})`,
        type: 'subject_reminder',
        notify_at: notifyAt,
      });
      setNotifications(prev => [local as StudyNotification, ...prev]);
      setCreateOpen(false);
      setError('บันทึกบนเซิร์ฟเวอร์ไม่สำเร็จ แต่สร้างแจ้งเตือนในระบบให้แล้ว');
    } finally {
      setCreateSaving(false);
    }
  };

  const renderNotificationCard = (notification: StudyNotification) => (
    <article
      key={notification.id}
      className={`group relative rounded-[1.5rem] border p-5 transition-all duration-300 hover:-translate-y-0.5 ${
        notification.is_read
          ? 'border-[color:var(--border)] bg-[color:color-mix(in_srgb,var(--surface)_88%,white_12%)]'
          : 'border-[color:rgba(var(--accent-rgb),0.28)] bg-[color:color-mix(in_srgb,var(--surface)_78%,white_22%)] shadow-[0_12px_30px_rgba(0,0,0,0.08)]'
      }`}
    >
      <div className="flex items-start gap-4">
        <div className="pt-1">
          {notification.is_read ? (
            <div className="h-3 w-3 rounded-full bg-slate-300 dark:bg-slate-700" />
          ) : (
            <div className="relative flex h-3 w-3">
              <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-[color:var(--accent)] opacity-65" />
              <span className="relative inline-flex h-3 w-3 rounded-full bg-[color:var(--accent)]" />
            </div>
          )}
        </div>
        <div className="flex-1">
          <div className="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
            <div>
              <p className={`text-base font-bold tracking-tight ${notification.is_read ? 'text-muted' : 'text-[color:var(--text)]'}`}>
                {notification.title}
              </p>
              <div className="mt-2 flex flex-wrap items-center gap-2">
                <span className="inline-flex items-center rounded-lg border border-[color:var(--border)] px-2.5 py-1 text-[11px] font-semibold text-muted">
                  {formatNotifyLabel(notification.notify_at)}
                </span>
                <span className="inline-flex items-center rounded-lg border border-[color:rgba(var(--accent-rgb),0.3)] bg-[color:rgba(var(--accent-rgb),0.12)] px-2.5 py-1 text-[11px] font-bold text-accent">
                  <Clock size={12} className="mr-1" />
                  {formatNotifyTimeOnly(notification.notify_at)} น.
                </span>
              </div>
              <p className={`mt-3 whitespace-pre-wrap text-sm ${notification.is_read ? 'text-muted' : 'text-[color:var(--text)]/80'}`}>
                {notification.message}
              </p>
            </div>
            <div className="mt-2 flex flex-wrap items-center gap-2 sm:mt-0 sm:justify-end">
              {!notification.is_read && (
                <button
                  onClick={() => markAsRead(notification)}
                  className="inline-flex items-center gap-1.5 rounded-xl border border-[color:var(--border)] bg-white/80 px-3 py-2 text-xs font-bold text-emerald-600 transition hover:bg-emerald-50 dark:bg-white/5"
                >
                  <Check size={14} />
                  อ่านแล้ว
                </button>
              )}
              {editingId === notification.id ? (
                <>
                  <div className="flex w-full items-center gap-2 sm:w-auto">
                    <input
                      type="text"
                      inputMode="numeric"
                      placeholder="dd/mm/yyyy"
                      value={editingDate}
                      onChange={e => setEditingDate(e.target.value)}
                      onBlur={() => {
                        const normalized = normalizeDateInputValue(editingDate);
                        if (normalized) {
                          setEditingDate(formatDateDisplayValue(normalized));
                        }
                      }}
                      className="w-full rounded-full border border-muted surface-2 px-3 py-1 text-xs font-semibold text-[color:var(--text)] shadow-sm outline-none focus:border-[color:var(--accent)] focus:ring-2 focus:ring-[color:var(--accent)]/20 sm:w-36"
                    />
                    <button
                      type="button"
                      onClick={() => openNativeDatePicker(editDatePickerRef.current)}
                      className="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-full border border-muted surface-2 text-xs text-muted transition hover:opacity-90"
                      aria-label="เลือกวันที่"
                      title="เลือกวันที่"
                    >
                      📅
                    </button>
                    <input
                      ref={editDatePickerRef}
                      type="date"
                      value={normalizeDateInputValue(editingDate) ?? ''}
                      onChange={e => setEditingDate(formatDateDisplayValue(e.target.value))}
                      tabIndex={-1}
                      aria-hidden="true"
                      className="sr-only"
                    />
                  </div>
                  <IPhoneTimePicker
                    value={editingTime}
                    onChange={value => setEditingTime(normalizeTimeValue(value))}
                    className="w-full sm:w-auto"
                    compact
                  />
                  <button
                    onClick={() => saveEditTime(notification.id)}
                    disabled={savingId === notification.id}
                    className="shrink-0 rounded-xl border border-[color:var(--border)] bg-white/80 px-3 py-2 text-xs font-bold text-accent transition hover:opacity-90 disabled:opacity-60 dark:bg-white/5"
                  >
                    {savingId === notification.id ? 'บันทึก...' : 'บันทึก'}
                  </button>
                  <button
                    onClick={cancelEditTime}
                    className="shrink-0 rounded-xl border border-[color:var(--border)] bg-white/80 px-3 py-2 text-xs font-bold text-muted transition hover:opacity-90 dark:bg-white/5"
                  >
                    ยกเลิก
                  </button>
                </>
              ) : (
                <button
                  onClick={() => startEditTime(notification)}
                  className="inline-flex items-center gap-1 rounded-xl border border-[color:var(--border)] bg-white/80 px-3 py-2 text-xs font-bold text-muted transition hover:text-indigo-600 dark:bg-white/5"
                >
                  <Edit2 size={13} />
                  แก้วัน/เวลา
                </button>
              )}
              <button
                onClick={() => deleteNotification(notification)}
                className="inline-flex items-center gap-1 rounded-xl border border-[color:var(--border)] bg-white/80 px-3 py-2 text-xs font-bold text-rose-600 transition hover:bg-rose-50 dark:bg-white/5"
              >
                <Trash2 size={13} />
                ลบ
              </button>
            </div>
          </div>
        </div>
      </div>
    </article>
  );

  return (
    <div className="mx-auto w-full max-w-6xl space-y-6 pb-14 text-[color:var(--text)]">
      <section className="rounded-[2rem] border border-[color:var(--border)] bg-[color:color-mix(in_srgb,var(--surface)_90%,white_10%)] p-6 shadow-[0_10px_38px_rgba(0,0,0,0.06)] backdrop-blur-xl">
        <div className="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
          <div className="flex items-start gap-3">
            <div className="mt-0.5 flex h-11 w-11 items-center justify-center rounded-xl border border-[color:var(--border)] bg-[color:rgba(var(--accent-rgb),0.12)] text-accent">
              <Settings size={18} />
            </div>
            <div>
              <p className="inline-flex items-center gap-1 rounded-full border border-[color:var(--border)] bg-[color:rgba(var(--accent-rgb),0.12)] px-2.5 py-1 text-[10px] font-bold uppercase tracking-[0.18em] text-accent">
                <Sparkles size={10} />
                Notification Hub
              </p>
              <h2 className="mt-2 text-3xl font-black tracking-tight">ตั้งค่าการแจ้งเตือน</h2>
            </div>
          </div>
          <button
            type="button"
            onClick={() => setCreateOpen(prev => !prev)}
            className="inline-flex items-center justify-center gap-2 rounded-full px-6 py-3 text-sm font-bold text-[color:var(--on-accent)] shadow-[0_12px_26px_rgba(var(--accent-rgb),0.3)] transition hover:-translate-y-0.5"
            style={{ background: 'linear-gradient(135deg, color-mix(in srgb, var(--accent) 92%, white), color-mix(in srgb, var(--accent) 82%, black))' }}
          >
            <Plus size={16} />
            {createOpen ? 'ปิดรายการใหม่' : 'สร้างรายการใหม่'}
          </button>
        </div>
      </section>

      {error && <div className="rounded-2xl bg-red-100 px-4 py-3 text-sm text-red-700">{error}</div>}

      <div className="grid gap-5 xl:grid-cols-[330px,1fr]">
        <aside className="rounded-[2rem] border border-[color:var(--border)] bg-[color:color-mix(in_srgb,var(--surface)_90%,white_10%)] p-5 shadow-[0_10px_38px_rgba(0,0,0,0.06)]">
          <div className="flex items-center gap-3">
            <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-[color:rgba(var(--accent-rgb),0.12)] text-accent"><Clock size={16} /></div>
            <h3 className="text-2xl font-black tracking-tight">แจ้งเตือนรายวัน</h3>
          </div>
          <p className="mt-3 text-xs text-muted">ระบุเวลาสรุปตารางเรียนในแต่ละวัน</p>

          <div className="mt-5 rounded-2xl border border-muted surface-2 p-4 text-center">
            <p className="text-[10px] font-bold uppercase tracking-[0.16em] text-muted">Hour</p>
            <p className="mt-1 text-4xl font-black text-accent">{notifyTime.split(':')[0] ?? '00'}</p>
            <p className="mt-1 text-[10px] font-bold uppercase tracking-[0.16em] text-muted">Min</p>
            <p className="mt-1 text-4xl font-black text-accent">{notifyTime.split(':')[1] ?? '00'}</p>
          </div>

          <div className="mt-4 flex justify-center">
            <IPhoneTimePicker
              value={notifyTime}
              onChange={value => {
                const normalized = normalizeTimeValue(value);
                setNotifyTime(normalized);
                updateNotifyTime(normalized);
              }}
            />
          </div>

          <button
            type="button"
            onClick={() => void fetchNotifications()}
            disabled={loading}
            className="mt-5 inline-flex w-full items-center justify-center gap-2 rounded-2xl px-4 py-3 text-sm font-bold text-[color:var(--on-accent)] transition hover:opacity-95 disabled:opacity-70"
            style={{ background: 'var(--accent)' }}
          >
            <RefreshCw size={16} />
            อัปเดตเวลา
          </button>
        </aside>

        <div className="space-y-4">
          {createOpen ? (
            <section className="rounded-[28px] border border-muted surface p-5 shadow-soft">
              <div className="mb-4">
                <h3 className="text-lg font-semibold">เพิ่มการแจ้งเตือนรายวิชา</h3>
                <p className="text-sm text-muted">เลือกวิชา พร้อมกำหนดวันและเวลาที่ต้องการให้ระบบแจ้งเตือน</p>
              </div>
              <div className="grid gap-3 md:grid-cols-4">
                <div className="md:col-span-2">
                  <label className="text-sm text-muted">ภาคเรียน</label>
                  <select
                    value={selectedSemesterKey}
                    onChange={e => setSelectedSemesterKey(e.target.value)}
                    className="mt-2 w-full rounded-xl border border-muted surface-2 px-3 py-2 text-sm shadow-sm outline-none focus:border-[color:var(--accent)] focus:ring-2 focus:ring-[color:var(--accent)]/20"
                  >
                    {semesterOptions.map(option => (
                      <option key={option.key} value={option.key}>
                        {option.label}
                      </option>
                    ))}
                  </select>
                </div>
                <div className="md:col-span-2">
                  <label className="text-sm text-muted">วิชา</label>
                  <select
                    value={createSubjectId}
                    onChange={e => setCreateSubjectId(e.target.value)}
                    className="mt-2 w-full rounded-xl border border-muted surface-2 px-3 py-2 text-sm shadow-sm outline-none focus:border-[color:var(--accent)] focus:ring-2 focus:ring-[color:var(--accent)]/20"
                  >
                    <option value="">เลือกวิชา...</option>
                    {filteredSubjects.map(s => (
                      <option key={s.id} value={String(s.id)}>
                        {s.name}
                      </option>
                    ))}
                  </select>
                  {subjectsLoading ? <p className="mt-2 text-xs text-muted">กำลังโหลดรายวิชา...</p> : null}
                  {!subjectsLoading && filteredSubjects.length === 0 ? (
                    <p className="mt-2 text-xs text-muted">ยังไม่มีรายวิชา (ไปที่หน้า "รายวิชา" เพื่อเพิ่มก่อน)</p>
                  ) : null}
                </div>
                <div>
                  <label className="text-sm text-muted">วันที่</label>
                  <div className="mt-2 flex items-center gap-2">
                    <input
                      type="text"
                      inputMode="numeric"
                      placeholder="dd/mm/yyyy"
                      value={createDate}
                      onChange={e => setCreateDate(e.target.value)}
                      onBlur={() => {
                        const normalized = normalizeDateInputValue(createDate);
                        if (normalized) {
                          setCreateDate(formatDateDisplayValue(normalized));
                        }
                      }}
                      className="w-full rounded-xl border border-muted surface-2 px-3 py-2 text-sm shadow-sm outline-none focus:border-[color:var(--accent)] focus:ring-2 focus:ring-[color:var(--accent)]/20"
                    />
                    <button
                      type="button"
                      onClick={() => openNativeDatePicker(createDatePickerRef.current)}
                      className="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-muted surface-2 text-base text-muted transition hover:opacity-90"
                      aria-label="เลือกวันที่"
                      title="เลือกวันที่"
                    >
                      📅
                    </button>
                    <input
                      ref={createDatePickerRef}
                      type="date"
                      value={normalizeDateInputValue(createDate) ?? ''}
                      onChange={e => setCreateDate(formatDateDisplayValue(e.target.value))}
                      tabIndex={-1}
                      aria-hidden="true"
                      className="sr-only"
                    />
                  </div>
                </div>
                <div>
                  <label className="text-sm text-muted">เวลา</label>
                  <div className="mt-2">
                    <IPhoneTimePicker
                      value={createTime}
                      onChange={value => setCreateTime(normalizeTimeValue(value))}
                      className="w-full justify-center"
                    />
                  </div>
                </div>
                <div className="md:col-span-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                  <p className="text-xs text-muted">* แจ้งเตือนนี้จะแสดงในระบบภายในเว็บเท่านั้น</p>
                  <button
                    type="button"
                    disabled={createSaving}
                    onClick={() => void createSubjectReminder()}
                    className="inline-flex items-center justify-center rounded-full px-5 py-2 text-sm font-semibold text-[color:var(--on-accent)] shadow-soft disabled:opacity-70"
                    style={{ background: 'var(--accent)' }}
                  >
                    {createSaving ? 'กำลังบันทึก...' : 'บันทึกการแจ้งเตือน'}
                  </button>
                </div>
              </div>
            </section>
          ) : null}

          <section className="rounded-[2rem] border border-[color:var(--border)] bg-[color:color-mix(in_srgb,var(--surface)_90%,white_10%)] p-5 shadow-[0_10px_38px_rgba(0,0,0,0.06)]">
            <div className="mb-4 flex items-center justify-between">
              <div className="flex items-center gap-2 text-[color:var(--text)]">
                <CalendarDays className="h-6 w-6 text-accent" />
                <h3 className="text-lg font-semibold">รายการแจ้งเตือน</h3>
                <span className="inline-flex h-6 min-w-6 items-center justify-center rounded-full bg-[color:rgba(var(--accent-rgb),0.16)] px-1.5 text-xs font-bold text-accent">
                  {allNotifications.length}
                </span>
              </div>
              {loading && <span className="text-xs text-muted">กำลังโหลด...</span>}
            </div>

            {allNotifications.length === 0 ? (
              <div className="rounded-2xl border border-dashed border-muted surface-2 px-4 py-6 text-center text-sm text-muted">
                ยังไม่มีการแจ้งเตือน
              </div>
            ) : (
              <div className="grid gap-3">
                {allNotifications.map(renderNotificationCard)}
              </div>
            )}

          </section>
        </div>
      </div>
    </div>
  );
};
