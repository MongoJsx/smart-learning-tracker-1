import { useEffect, useMemo, useState } from 'react';
import { useLocation, useParams } from 'react-router-dom';
import { useForm } from 'react-hook-form';
import { format } from 'date-fns';
import { api, assetBaseURL } from '../../services/api';
import { getLastSubjectKey } from '../../constants/storage';
import { useAppAlert } from '../../context/AppAlertContext';
import { useAuth } from '../../context/AuthContext';
import { resolveStudyFileUrl, uploadStudyFileToSupabase } from '../../utils/studyFiles';

interface Subject {
  id: number;
  name: string;
  description?: string | null;
  target_hours?: number | null;
  semester?: number | null;
  academic_year?: number | null;
}

interface StudyFile {
  id: number;
  original_name: string;
  file_path: string;
  file_url?: string | null;
  file_type: string;
}

interface Summary {
  id: number;
  content: string;
  created_at: string;
}

interface AudioSummary {
  id: number;
  summary: string;
  transcript?: string | null;
  created_at?: string;
  source?: string;
}

interface StudyLog {
  id: number;
  title: string;
  note: string;
  log_date: string;
  duration_minutes?: number | null; // Added to match database
  mood?: string | null; // Added to match database
  files: StudyFile[];
  summaries: Summary[];
  audio_summaries?: AudioSummary[];
}

interface StudyLogForm {
  title: string;
  note: string;
  log_date: string;
  duration_minutes?: number;
  mood?: string;
}

const storageBase = assetBaseURL || (api.defaults.baseURL ?? '').replace(/\/api\/?$/, '');

export const StudyLogPage = () => {
  const { user } = useAuth();
  const { success, error } = useAppAlert();
  const { subjectId } = useParams();
  const location = useLocation();
  const [subject, setSubject] = useState<Subject | null>(null);
  const [logs, setLogs] = useState<StudyLog[]>([]);
  const [selectedLog, setSelectedLog] = useState<StudyLog | null>(null);
  const [loading, setLoading] = useState(true);
  const [deletingLogId, setDeletingLogId] = useState<number | null>(null);
  const [deletingFileId, setDeletingFileId] = useState<number | null>(null);
  const [deletingSummaryId, setDeletingSummaryId] = useState<string | null>(null);
  const selectedLogIdFromState = Number((location.state as { selectedLogId?: number } | null)?.selectedLogId);
  const lastSubjectKey = useMemo(() => getLastSubjectKey(user?.id), [user?.id]);

  useEffect(() => {
    if (subjectId) {
      localStorage.setItem(lastSubjectKey, subjectId);
    }
  }, [subjectId, lastSubjectKey]);
  const {
    register,
    handleSubmit,
    reset,
    formState: { isSubmitting }
  } = useForm<StudyLogForm>({
    defaultValues: {
      title: '',
      note: '',
      log_date: format(new Date(), 'yyyy-MM-dd'),
      duration_minutes: 60
    }
  });

  useEffect(() => {
    if (!subjectId) return;

    Promise.all([
      api.get(`/subjects/${subjectId}`),
      api.get(`/subjects/${subjectId}/study-logs`)
    ])
      .then(([subjectRes, logsRes]) => {
        const subjectPayload = subjectRes.data?.data ?? subjectRes.data;
        const logsPayload = logsRes.data?.data ?? logsRes.data;
        const logsList: StudyLog[] = Array.isArray(logsPayload) ? logsPayload : [];
        setSubject(subjectPayload);
        setLogs(logsList);
        if (Number.isFinite(selectedLogIdFromState)) {
          const matched = logsList.find(log => log.id === selectedLogIdFromState);
          setSelectedLog(matched ?? logsList[0] ?? null);
        } else {
          setSelectedLog(logsList[0] ?? null);
        }
      })
      .finally(() => setLoading(false));
  }, [subjectId, selectedLogIdFromState]);

  const onSubmit = async (data: StudyLogForm) => {
    if (!subjectId) return;
    const response = await api.post<StudyLog>(`/subjects/${subjectId}/study-logs`, data);
    setLogs(prev => [response.data, ...prev]);
    reset();
  };

  const uploadFile = async (logId: number, file: File) => {
    if (!subject) {
      error('ไม่พบข้อมูลวิชา');
      return;
    }

    const uploaded = await uploadStudyFileToSupabase({
      file,
      userId: user?.id,
      subject: {
        id: subject.id,
        name: subject.name,
        semester: subject.semester,
        academic_year: subject.academic_year,
      },
    });

    const response = await api.post<StudyFile>(`/study-logs/${logId}/files`, {
      original_name: file.name,
      storage_path: uploaded.storagePath,
      file_type: uploaded.fileType,
      mime_type: file.type || null,
      file_size: file.size,
    });
    setLogs(prev =>
      prev.map(log => (log.id === logId ? { ...log, files: [...log.files, response.data] } : log))
    );
  };

  const requestSummary = async (logId: number) => {
    const response = await api.post<Summary>(`/study-logs/${logId}/summaries`);
    setLogs(prev =>
      prev.map(log => (log.id === logId ? { ...log, summaries: [response.data, ...log.summaries] } : log))
    );
  };

  const deleteLog = async (logId: number) => {
    if (!subjectId) return;
    const confirmed = window.confirm('ต้องการลบบันทึกการเรียนนี้ใช่หรือไม่?');
    if (!confirmed) return;

    setDeletingLogId(logId);
    try {
      await api.delete(`/subjects/${subjectId}/study-logs/${logId}`);
      let nextLogs: StudyLog[] = [];
      setLogs(prev => {
        nextLogs = prev.filter(log => log.id !== logId);
        return nextLogs;
      });
      setSelectedLog(prev => (prev?.id === logId ? nextLogs[0] ?? null : prev));
      success('ลบบันทึกการเรียนเรียบร้อยแล้ว');
    } catch {
      error('ลบบันทึกการเรียนไม่สำเร็จ');
    } finally {
      setDeletingLogId(null);
    }
  };

  const deleteFile = async (logId: number, file: StudyFile) => {
    const confirmed = window.confirm(`ต้องการลบไฟล์ "${file.original_name}" ใช่หรือไม่?`);
    if (!confirmed) return;

    setDeletingFileId(file.id);
    try {
      await api.delete(`/files/${file.id}`);
      setLogs(prev =>
        prev.map(log =>
          log.id === logId ? { ...log, files: log.files.filter(item => item.id !== file.id) } : log
        )
      );
      success('ลบไฟล์เรียบร้อยแล้ว');
    } catch {
      error('ลบไฟล์ไม่สำเร็จ');
    } finally {
      setDeletingFileId(null);
    }
  };

  const deleteSummary = async (logId: number, source: 'text' | 'audio', summaryId: number) => {
    const confirmed = window.confirm('ต้องการลบสรุปรายการนี้ใช่หรือไม่?');
    if (!confirmed) return;

    const stateId = `${source}-${summaryId}`;
    setDeletingSummaryId(stateId);
    try {
      if (source === 'audio') {
        await api.delete(`/ai/summaries/audio/${summaryId}`);
      } else {
        await api.delete(`/summaries/${summaryId}`);
      }
      setLogs(prev =>
        prev.map(log => {
          if (log.id !== logId) return log;
          if (source === 'audio') {
            return {
              ...log,
              audio_summaries: (log.audio_summaries ?? []).filter(item => item.id !== summaryId),
            };
          }
          return {
            ...log,
            summaries: log.summaries.filter(item => item.id !== summaryId),
          };
        })
      );
      success('ลบสรุปเรียบร้อยแล้ว');
    } catch {
      error('ลบสรุปไม่สำเร็จ');
    } finally {
      setDeletingSummaryId(null);
    }
  };

  const currentLog = useMemo(
    () => logs.find(log => log.id === selectedLog?.id) ?? selectedLog,
    [logs, selectedLog]
  );

  const combinedSummaries = useMemo(() => {
    if (!currentLog) return [];
    const textSummaries = (currentLog.summaries ?? []).map(item => ({
      id: `summary-${item.id}`,
      recordId: item.id,
      source: 'text' as const,
      content: item.content,
      created_at: item.created_at,
      transcript: null,
    }));
    const audioSummaries = (currentLog.audio_summaries ?? []).map(item => ({
      id: `audio-${item.id}`,
      recordId: item.id,
      source: 'audio' as const,
      content: item.summary,
      created_at: item.created_at,
      transcript: item.transcript ?? null,
    }));
    const items = [...audioSummaries, ...textSummaries].filter(item => item.content?.trim());
    return items.sort((a, b) => {
      const aTime = a.created_at ? new Date(a.created_at).getTime() : 0;
      const bTime = b.created_at ? new Date(b.created_at).getTime() : 0;
      return bTime - aTime;
    });
  }, [currentLog]);

  if (loading) {
    return <p className="text-muted">กำลังโหลดข้อมูล...</p>;
  }

  if (!subject) {
    return <p className="text-rose-500">ไม่พบรายวิชา</p>;
  }

  return (
    <div className="space-y-6 text-[color:var(--text)]">
      <section className="rounded-3xl bg-gradient-to-r from-primary to-primaryDark p-6 text-white shadow-glow">
        <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
          <div>
            <p className="text-xs uppercase tracking-[0.35em] text-white/70">Subject Timeline</p>
            <h2 className="mt-2 text-3xl font-semibold">{subject.name}</h2>
            <p className="mt-2 text-sm text-white/85">{subject.description ?? 'เพิ่มรายละเอียดรายวิชาภายหลังได้'}</p>
          </div>
          <div className="rounded-2xl bg-white/15 p-4 text-sm">
            <p className="text-white/80">ชั่วโมงเป้าหมาย/เดือน</p>
            <p className="mt-1 text-2xl font-semibold">{subject.target_hours ?? 0} ชม.</p>
          </div>
        </div>
      </section>

      <div className="grid gap-6 lg:grid-cols-[320px,1fr]">
        <aside className="rounded-3xl border border-muted surface p-5 shadow-soft">
          <h3 className="text-lg font-semibold text-[color:var(--text)]">บันทึกการเรียน</h3>
          <p className="mt-1 text-sm text-muted">เพิ่มหัวข้อ รายละเอียด และเวลาเรียนในแต่ละวัน</p>
          <form onSubmit={handleSubmit(onSubmit)} className="mt-4 space-y-3">
            <div>
              <label className="mb-1 block text-xs uppercase tracking-widest text-muted">หัวข้อ</label>
              <input
                className="w-full rounded-2xl border border-muted surface-2 px-3 py-3 text-[color:var(--text)] shadow-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/30"
                {...register('title', { required: true })}
              />
            </div>
          <div>
            <label className="mb-1 block text-xs uppercase tracking-widest text-muted">Reflection / สิ่งที่เรียนรู้</label>
            <p className="mb-2 text-xs text-muted">เขียนสิ่งที่เข้าใจ สิ่งที่ยังสงสัย และแผนปรับปรุงในครั้งถัดไป</p>
            <textarea
              placeholder="สรุปบทเรียนสั้น ๆ หรือสะท้อนสิ่งที่ต้องพัฒนา"
              className="h-28 w-full rounded-2xl border border-muted surface-2 px-3 py-3 text-[color:var(--text)] shadow-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/30"
              {...register('note')}
            />
          </div>
          <div className="grid grid-cols-2 gap-2 text-sm">
            <div>
              <label className="mb-1 block text-xs uppercase tracking-widest text-muted">วันที่เรียน</label>
              <input
                type="date"
                className="w-full rounded-2xl border border-muted surface-2 px-3 py-3 text-[color:var(--text)] shadow-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/30"
                {...register('log_date', { required: true })}
              />
            </div>
            <div>
              <label className="mb-1 block text-xs uppercase tracking-widest text-muted">ระยะเวลา (นาที)</label>
              <input
                type="number"
                className="w-full rounded-2xl border border-muted surface-2 px-3 py-3 text-[color:var(--text)] shadow-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/30"
                {...register('duration_minutes')}
              />
            </div>
          </div>
          <div>
            <label className="mb-1 block text-xs uppercase tracking-widest text-muted">อารมณ์</label>
            <input
              className="w-full rounded-2xl border border-muted surface-2 px-3 py-3 text-[color:var(--text)] shadow-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/30"
              {...register('mood')}
            />
          </div>
          <button type="submit" className="btn-primary w-full" disabled={isSubmitting}>
            {isSubmitting ? 'กำลังบันทึก...' : 'บันทึกการเรียน'}
          </button>
        </form>
        </aside>

        <section className="space-y-4">
          <div className="rounded-3xl border border-muted surface p-5 shadow-soft">
            <div className="mb-4 flex items-center justify-between">
              <h3 className="text-lg font-semibold text-[color:var(--text)]">บันทึกล่าสุด</h3>
              <span className="text-xs font-semibold uppercase tracking-[0.35em] text-primary/70">Timeline</span>
            </div>
            <div className="max-h-72 space-y-3 overflow-y-auto pr-2">
              {logs.map(log => (
                <article
                  key={log.id}
                  className={`w-full rounded-2xl border px-4 py-3 text-left transition ${
                    currentLog?.id === log.id
                      ? 'border-primary/60 surface-2 shadow-glow'
                      : 'border-muted surface'
                  }`}
                >
                  <button type="button" onClick={() => setSelectedLog(log)} className="w-full text-left">
                    <p className="text-xs font-medium uppercase tracking-widest text-muted">
                      {format(new Date(log.log_date), 'd MMM yyyy')}
                    </p>
                    <p className="mt-1 font-semibold text-[color:var(--text)]">{log.title}</p>
                    {log.duration_minutes && (
                      <p className="text-xs text-muted">{log.duration_minutes} นาที | {log.mood ?? 'ไม่ระบุอารมณ์'}</p>
                    )}
                  </button>
                  <div className="mt-3 flex justify-end">
                    <button
                      type="button"
                      onClick={() => void deleteLog(log.id)}
                      disabled={deletingLogId === log.id}
                      className="rounded-full border border-rose-400/30 bg-rose-400/10 px-3 py-1 text-xs font-semibold text-rose-300 transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                      {deletingLogId === log.id ? 'กำลังลบ...' : 'ลบ'}
                    </button>
                  </div>
                </article>
              ))}
            </div>
            {logs.length === 0 && (
              <p className="rounded-2xl border border-dashed border-primary/30 surface-2 p-4 text-center text-sm text-muted">
                ยังไม่มีบันทึก เริ่มต้นด้วยการเพิ่มหัวข้อใหม่ทางด้านซ้าย
              </p>
            )}
          </div>

          {currentLog && (
            <article className="rounded-3xl border border-muted surface p-6 shadow-soft">
              <header>
                <h3 className="text-xl font-semibold text-[color:var(--text)]">{currentLog.title}</h3>
                <p className="text-sm text-muted">{format(new Date(currentLog.log_date), 'EEEE d MMM yyyy')}</p>
              </header>
              <p className="mt-3 whitespace-pre-line rounded-2xl surface-2 p-4 text-muted">
                {currentLog.note || 'ไม่มีโน้ต'}
              </p>

              <div className="mt-4">
                <h4 className="mb-2 text-sm font-semibold uppercase tracking-widest text-primary/70">ไฟล์แนบ</h4>
                <div className="space-y-2">
                  <label className="flex cursor-pointer items-center justify-between rounded-2xl border border-dashed border-primary/30 surface-2 px-3 py-3 text-sm text-muted transition hover:border-primary">
                    <span>อัปโหลดไฟล์ (.pdf, .docx, .mp3, รูปภาพ)</span>
                    <input
                      type="file"
                      hidden
                      accept=".pdf,.doc,.docx,.txt,.mp3,.wav,.m4a,.jpg,.jpeg,.png,.gif,.webp,image/*,audio/*"
                      onChange={event => {
                        const file = event.target.files?.[0];
                        if (file) {
                          void uploadFile(currentLog.id, file);
                        }
                      }}
                    />
                  </label>
                  {currentLog.files.length > 0 ? (
                    <ul className="space-y-2 text-sm text-muted">
                      {currentLog.files.map(file => (
                        <li key={file.id} className="flex items-center justify-between rounded-2xl border border-muted surface-2 px-3 py-2">
                          <span>{file.original_name}</span>
                          <div className="flex items-center gap-2">
                            <a
                              href={file.file_url || resolveStudyFileUrl(file.file_path, storageBase)}
                              target="_blank"
                              rel="noreferrer"
                              className="text-primary"
                            >
                              เปิดไฟล์
                            </a>
                            <button
                              type="button"
                              onClick={() => void deleteFile(currentLog.id, file)}
                              disabled={deletingFileId === file.id}
                              className="rounded-full border border-rose-400/30 bg-rose-400/10 px-3 py-1 text-xs font-semibold text-rose-300 transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-60"
                            >
                              {deletingFileId === file.id ? 'กำลังลบ...' : 'ลบ'}
                            </button>
                          </div>
                        </li>
                      ))}
                    </ul>
                  ) : (
                    <p className="text-xs text-muted">ยังไม่มีไฟล์แนบ</p>
                  )}
                </div>
              </div>

              <div className="mt-4">
                <div className="mb-2 flex items-center justify-between">
                  <h4 className="text-sm font-semibold uppercase tracking-widest text-primary/70">สรุปจาก AI</h4>
                  <button
                    className="rounded-full border border-primary/40 px-4 py-2 text-xs font-semibold text-primary transition hover:shadow-glow"
                    onClick={() => requestSummary(currentLog.id)}
                  >
                    สร้างสรุป
                  </button>
                </div>
                {combinedSummaries.length > 0 ? (
                  <div className="space-y-3">
                    {combinedSummaries.map(summary => (
                      <div key={summary.id} className="rounded-2xl border border-muted surface-2 px-4 py-3 text-sm leading-6 text-muted shadow-sm">
                        <div className="flex flex-wrap items-center justify-between gap-2">
                          <p className="text-xs font-medium uppercase tracking-widest text-muted">
                            {summary.source === 'audio' ? 'สรุปจากเสียง' : 'สรุปจาก AI'}
                          </p>
                          <div className="flex items-center gap-2">
                            {summary.created_at ? (
                              <span className="text-xs text-muted">
                                {format(new Date(summary.created_at), 'd MMM yyyy HH:mm')}
                              </span>
                            ) : null}
                            <button
                              type="button"
                              onClick={() => void deleteSummary(currentLog.id, summary.source, summary.recordId)}
                              disabled={deletingSummaryId === summary.id}
                              className="rounded-full border border-rose-400/30 bg-rose-400/10 px-3 py-1 text-xs font-semibold text-rose-300 transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-60"
                            >
                              {deletingSummaryId === summary.id ? 'กำลังลบ...' : 'ลบ'}
                            </button>
                          </div>
                        </div>
                        <p className="mt-2 whitespace-pre-line">{summary.content}</p>
                        {summary.source === 'audio' && summary.transcript ? (
                          <details className="mt-2 text-xs text-muted">
                            <summary className="cursor-pointer">ดูคำถอดเสียง</summary>
                            <p className="mt-2 whitespace-pre-line">{summary.transcript}</p>
                          </details>
                        ) : null}
                      </div>
                    ))}
                  </div>
                ) : (
                  <p className="text-xs text-muted">ยังไม่มีสรุปจาก AI</p>
                )}
              </div>
            </article>
          )}
        </section>
      </div>
    </div>
  );
};
