import { ChangeEvent, DragEvent, useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Link, useLocation } from 'react-router-dom';
import { format, isValid, parseISO } from 'date-fns';
import { th } from 'date-fns/locale';
import { api } from '../../services/api';
import { getLastSubjectKey } from '../../constants/storage';
import { aiApi } from "../../services/aiApi";
// import { useAuth } from '../../context/AuthContext';
import { useAuth } from '../../context/AuthContext';
import { useSemesterOptions } from '../../hooks/useSemesterOptions';
import { filterBySemester, toNumberOrNull } from '../../utils/semester';
import { subscribeSubjectsUpdated } from '../../utils/subjectSync';


type VoiceSummaryResponse = {
  transcript?: string;
  summary?: string;
  analysis?: string;
};

type ResultBucket = {
  source: Exclude<ProcessingSource, null>;
  data: VoiceSummaryResponse | null;
};

type SubjectOption = {
  id: number;
  name: string;
  semester_id?: number | null;
  semester?: number | null;
  academic_year?: number | null;
};

type ProcessingSource = 'upload' | 'record' | null;

type SavedAudioSummary = {
  id: number;
  study_log_id?: number | null;
  summary: string;
  transcript?: string;
  created_at?: string;
  title?: string;
  subject?: string;
  source_mode?: 'upload' | 'record' | null;
};

type VoiceSummaryPageProps = {
  embedded?: boolean;
  initialSection?: 'upload' | 'record';
  onSummaryReady?: (payload: {
    summary: string;
    transcript?: string;
    source: Exclude<ProcessingSource, null>;
  }) => void;
};

const voiceSummaryListEndpoint = '/ai/summaries/audio';
const LARGE_UPLOAD_THRESHOLD_BYTES = 8 * 1024 * 1024; // 8MB

const formatTimer = (value: number) => {
  const minutes = Math.floor(value / 60);
  const seconds = value % 60;
  return `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
};

const formatSummaryTime = (value?: string) => {
  if (!value) return '';
  const parsed = parseISO(value);
  if (!isValid(parsed)) return value;
  return format(parsed, 'd MMM yyyy HH:mm', { locale: th });
};

const toSingleLine = (value: string) => value.replace(/\s+/g, ' ').trim();
const truncateText = (value: string, max = 160) => {
  const normalized = toSingleLine(value);
  if (normalized.length <= max) return normalized;
  return `${normalized.slice(0, Math.max(0, max - 3))}...`;
};
const buildRealtimeSummary = (value: string) => {
  const normalized = value.replace(/\s+/g, ' ').trim();
  if (!normalized) return '';
  const segments = normalized
    .split(/[.!?。\n]+/g)
    .map(item => item.trim())
    .filter(item => item.length >= 12)
    .slice(0, 4);
  if (!segments.length) return normalized.slice(0, 220);
  return segments.map((segment, index) => `${index + 1}. ${segment}`).join('\n');
};

const buildDocumentStyleSummaryFallback = (value: string) => {
  const normalized = value.replace(/\s+/g, ' ').trim();
  if (!normalized) return '';
  const segments = normalized
    .split(/[.!?。\n]+/g)
    .map(item => item.trim())
    .filter(item => item.length >= 12)
    .slice(0, 6);
  if (!segments.length) return normalized.slice(0, 260);

  return [
    'ประเด็นสำคัญจากไฟล์เสียง:',
    ...segments.map(segment => `- ${segment}`),
  ].join('\n');
};

const isGooglePayloadLimitError = (message?: string | null) => {
  const value = (message ?? '').toLowerCase();
  if (!value) return false;
  return (
    value.includes('payload size exceeds the limit') ||
    value.includes('10485760') ||
    value.includes('google speech request failed (400)')
  );
};

const isGoogleMonoChannelError = (message?: string | null) => {
  const value = (message ?? '').toLowerCase();
  if (!value) return false;
  return (
    value.includes('single channel (mono)') ||
    value.includes('wav header indicates') ||
    value.includes('2 channels')
  );
};

const isLowConfidenceTranscriptError = (message?: string | null) => {
  const value = (message ?? '').toLowerCase();
  if (!value) return false;
  return (
    value.includes('low-confidence') ||
    value.includes('ไม่สามารถถอดเสียง') ||
    value.includes('returned an empty transcript') ||
    value.includes('ถอดเสียงจากไฟล์ไม่สำเร็จ')
  );
};

const encodeWavChunk = (audioBuffer: AudioBuffer, startSample: number, endSample: number) => {
  // Google Speech synchronous recognize is most stable with mono PCM.
  const channels = 1;
  const sampleRate = audioBuffer.sampleRate;
  const frameCount = endSample - startSample;
  const bytesPerSample = 2;
  const blockAlign = channels * bytesPerSample;
  const byteRate = sampleRate * blockAlign;
  const dataSize = frameCount * blockAlign;
  const buffer = new ArrayBuffer(44 + dataSize);
  const view = new DataView(buffer);

  const writeString = (offset: number, text: string) => {
    for (let i = 0; i < text.length; i += 1) {
      view.setUint8(offset + i, text.charCodeAt(i));
    }
  };

  writeString(0, 'RIFF');
  view.setUint32(4, 36 + dataSize, true);
  writeString(8, 'WAVE');
  writeString(12, 'fmt ');
  view.setUint32(16, 16, true);
  view.setUint16(20, 1, true);
  view.setUint16(22, channels, true);
  view.setUint32(24, sampleRate, true);
  view.setUint32(28, byteRate, true);
  view.setUint16(32, blockAlign, true);
  view.setUint16(34, 16, true);
  writeString(36, 'data');
  view.setUint32(40, dataSize, true);

  let offset = 44;
  for (let i = startSample; i < endSample; i += 1) {
    let mixed = 0;
    const sourceChannels = audioBuffer.numberOfChannels;
    for (let channel = 0; channel < sourceChannels; channel += 1) {
      mixed += audioBuffer.getChannelData(channel)[i] ?? 0;
    }
    const sample = mixed / Math.max(1, sourceChannels);
    const clamped = Math.max(-1, Math.min(1, sample));
    const intSample = clamped < 0 ? clamped * 0x8000 : clamped * 0x7fff;
    view.setInt16(offset, intSample, true);
    offset += 2;
  }

  return new Blob([buffer], { type: 'audio/wav' });
};

type ExpandableTextProps = {
  text: string;
  previewChars?: number;
  className?: string;
  buttonClassName?: string;
  emptyText?: string;
};

const ExpandableText = ({
  text,
  previewChars = 280,
  className = '',
  buttonClassName = '',
  emptyText = '-',
}: ExpandableTextProps) => {
  const [expanded, setExpanded] = useState(false);
  const normalizedText = text?.trim() ?? '';
  const shouldTruncate = normalizedText.length > previewChars;
  const previewText = shouldTruncate ? `${normalizedText.slice(0, Math.max(0, previewChars)).trimEnd()}...` : normalizedText;

  return (
    <div>
      <p className={className}>{normalizedText ? (expanded ? normalizedText : previewText) : emptyText}</p>
      {shouldTruncate ? (
        <button
          type="button"
          onClick={() => setExpanded(value => !value)}
          className={buttonClassName}
        >
          {expanded ? 'ย่อข้อความ' : 'ดูข้อความเพิ่มเติม'}
        </button>
      ) : null}
    </div>
  );
};

export const VoiceSummaryPage = ({ embedded = false, initialSection, onSummaryReady }: VoiceSummaryPageProps) => {
  const { user } = useAuth();
  const location = useLocation();
  const [isRecording, setIsRecording] = useState(false);
  const [timer, setTimer] = useState(0);
  const [statusText, setStatusText] = useState('อัปโหลดไฟล์เสียงที่บันทึกไว้ หรือกดอัดใหม่เพื่อเริ่มสรุป');
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [uploadResult, setUploadResult] = useState<VoiceSummaryResponse | null>(null);
  const [recordResult, setRecordResult] = useState<VoiceSummaryResponse | null>(null);
  const [isRecorderSupported, setIsRecorderSupported] = useState(true);
  const [isLiveRecognitionSupported, setIsLiveRecognitionSupported] = useState(false);
  const [selectedFileName, setSelectedFileName] = useState<string | null>(null);
  const [isDragging, setIsDragging] = useState(false);
  const [processingSource, setProcessingSource] = useState<ProcessingSource>(null);
  const [liveTranscript, setLiveTranscript] = useState('');
  const [liveSummary, setLiveSummary] = useState('');
  const [pendingFile, setPendingFile] = useState<File | null>(null);
  const [subjects, setSubjects] = useState<SubjectOption[]>([]);
  const [selectedSemesterKey, setSelectedSemesterKey] = useState('all');
  const [selectedSubjectId, setSelectedSubjectId] = useState('');
  const [analysisType, setAnalysisType] = useState<'summary' | 'career'>('summary');
  const [savedSummaries, setSavedSummaries] = useState<SavedAudioSummary[]>([]);
  const [savedLoading, setSavedLoading] = useState(false);
  const [savedError, setSavedError] = useState<string | null>(null);
  const [deletingSummaryId, setDeletingSummaryId] = useState<number | null>(null);
  const [archivingSummaryId, setArchivingSummaryId] = useState<number | null>(null);
  const [isDarkTheme, setIsDarkTheme] = useState(false);
  const lastSubjectKey = useMemo(() => getLastSubjectKey(user?.id), [user?.id]);
  const semesterOptions = useSemesterOptions();

  const mediaRecorderRef = useRef<MediaRecorder | null>(null);
  const speechRecognitionRef = useRef<any>(null);
  const timerRef = useRef<number | null>(null);
  const chunksRef = useRef<Blob[]>([]);
  const fileInputRef = useRef<HTMLInputElement | null>(null);

  const filteredSubjects = useMemo(() => filterBySemester(subjects, selectedSemesterKey), [subjects, selectedSemesterKey]);
  const selectableSubjects = useMemo(
    () => (filteredSubjects.length > 0 ? filteredSubjects : subjects),
    [filteredSubjects, subjects]
  );
  const uploadedSummaries = useMemo(
    () => savedSummaries.filter(item => (item.source_mode ?? 'upload') !== 'record'),
    [savedSummaries]
  );
  const recordedSummaries = useMemo(
    () => savedSummaries.filter(item => item.source_mode === 'record'),
    [savedSummaries]
  );

  useEffect(() => {
    const supported =
      typeof window !== 'undefined' &&
      typeof window.MediaRecorder !== 'undefined' &&
      typeof navigator !== 'undefined' &&
      !!navigator.mediaDevices?.getUserMedia;
    const speechRecognitionCtor =
      typeof window !== 'undefined'
        ? (window as any).SpeechRecognition ?? (window as any).webkitSpeechRecognition
        : null;
    setIsRecorderSupported(supported);
    setIsLiveRecognitionSupported(Boolean(speechRecognitionCtor));
    return () => {
      if (timerRef.current) {
        window.clearInterval(timerRef.current);
      }
      if (mediaRecorderRef.current) {
        mediaRecorderRef.current.stream.getTracks().forEach(track => track.stop());
        mediaRecorderRef.current = null;
      }
      if (speechRecognitionRef.current) {
        try {
          speechRecognitionRef.current.stop();
        } catch {
          // ignore
        }
        speechRecognitionRef.current = null;
      }
    };
  }, []);

  useEffect(() => {
    if (!location.hash) return;
    const targetId = location.hash.replace(/^#/, '');
    const target = document.getElementById(targetId);
    if (!target) return;
    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }, [location.hash]);

  useEffect(() => {
    if (!embedded || !initialSection) return;
    const target = document.getElementById(initialSection);
    if (!target) return;
    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }, [embedded, initialSection]);

  const cardClass = embedded
    ? 'rounded-3xl border bg-[color:var(--surface)]/92 p-5 shadow-xl backdrop-blur-2xl'
    : 'doc-summary-card rounded-[30px] border border-[color:var(--border)] bg-[color:var(--surface)]/95 p-6 shadow-[0_16px_34px_rgba(15,23,42,0.10)] backdrop-blur-xl';
  const shellClass = embedded
    ? 'rounded-3xl border bg-[color:var(--surface)]/92 shadow-xl backdrop-blur-2xl'
    : 'doc-summary-card rounded-[30px] border border-[color:var(--border)] bg-[color:var(--surface)]/95 shadow-[0_16px_34px_rgba(15,23,42,0.10)] backdrop-blur-xl';
  const activeClass = embedded
    ? 'border-[color:rgba(var(--accent-rgb),0.35)] ring-2 ring-[color:rgba(var(--accent-rgb),0.15)]'
    : 'border-[color:rgba(var(--accent-rgb),0.35)] ring-2 ring-[color:rgba(var(--accent-rgb),0.15)]';
  const fieldClass = embedded
    ? 'w-full appearance-none rounded-xl border border-[color:var(--border)] bg-[color:var(--surface-2)] px-4 py-3 text-sm font-semibold text-[color:var(--text)] shadow-sm outline-none transition focus:border-[color:var(--accent)] focus:ring-2 focus:ring-[color:rgba(var(--accent-rgb),0.25)]'
    : 'w-full rounded-[18px] border border-[color:var(--border)] bg-[color:var(--surface-2)] px-4 py-3 text-sm font-semibold text-[color:var(--text)] shadow-sm outline-none transition focus:border-[color:var(--accent)] focus:ring-4 focus:ring-[color:rgba(var(--accent-rgb),0.12)]';
  const showUploadSection = !embedded || initialSection !== 'record';
  const showRecordSection = !embedded || initialSection !== 'upload';

  useEffect(() => {
    if (typeof document === 'undefined') return;

    const syncTheme = () => {
      const dark =
        document.documentElement.classList.contains('theme-dark') ||
        document.body.classList.contains('theme-dark');
      setIsDarkTheme(dark);
    };

    syncTheme();
    const observer = new MutationObserver(syncTheme);
    observer.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
    observer.observe(document.body, { attributes: true, attributeFilter: ['class'] });
    return () => observer.disconnect();
  }, []);
  const leftPanelLayoutClass = embedded
    ? 'grid gap-6'
    : (showUploadSection && showRecordSection ? 'grid gap-6 xl:grid-cols-2' : 'space-y-6');
  const mainLayoutClass = embedded
    ? 'grid gap-6 2xl:grid-cols-[320px,minmax(0,1fr)]'
    : 'grid gap-6 xl:grid-cols-[1.2fr,0.8fr]';
  const uploadPreviewText = truncateText(uploadResult?.summary || uploadResult?.transcript || '', 120);
  const recordPreviewText = truncateText(recordResult?.summary || recordResult?.transcript || '', 120);

  const fetchSubjects = async () => {
    try {
      const res = await api.get('/subjects');
      const payload = Array.isArray(res.data) ? res.data : res.data?.data;
      const list = Array.isArray(payload)
        ? payload.map((item: any) => ({
            id: item.id,
            name: item.name,
            semester_id: toNumberOrNull(item.semester_id),
            semester: toNumberOrNull(item.semester),
            academic_year: toNumberOrNull(item.academic_year),
          }))
        : [];
      setSubjects(list);

      const storedId = localStorage.getItem(lastSubjectKey);
      if (storedId && list.some(subject => String(subject.id) === storedId)) {
        setSelectedSubjectId(storedId);
        return;
      }

      if (list[0]?.id) {
        const fallbackId = String(list[0].id);
        setSelectedSubjectId(fallbackId);
        localStorage.setItem(lastSubjectKey, fallbackId);
        return;
      }

      setSelectedSubjectId('');
      localStorage.removeItem(lastSubjectKey);
    } catch (err) {
      setSubjects([]);
      setSelectedSubjectId('');
      localStorage.removeItem(lastSubjectKey);
    }
  };

  useEffect(() => {
    void fetchSubjects();
    return subscribeSubjectsUpdated(() => {
      void fetchSubjects();
    });
  }, [lastSubjectKey]);

  useEffect(() => {
    if (selectedSubjectId && selectableSubjects.some(subject => String(subject.id) === selectedSubjectId)) return;
    const fallback = selectableSubjects[0];
    if (!fallback) {
      setSelectedSubjectId('');
      return;
    }
    const fallbackId = String(fallback.id);
    setSelectedSubjectId(fallbackId);
    localStorage.setItem(lastSubjectKey, fallbackId);
  }, [selectableSubjects, lastSubjectKey, selectedSubjectId]);

  const fetchSavedSummaries = useCallback(async (subjectId?: string) => {
    setSavedLoading(true);
    setSavedError(null);
    try {
      const res = await api.get(voiceSummaryListEndpoint, {
        params: subjectId ? { subject_id: subjectId } : undefined,
      });
      const payload = Array.isArray(res.data) ? res.data : res.data?.data;
      const remote = Array.isArray(payload) ? (payload as SavedAudioSummary[]) : [];

      setSavedSummaries(remote.slice(0, 30));
    } catch (err: any) {
      setSavedError(err.response?.data?.message ?? 'โหลดสรุปจากเสียงไม่สำเร็จ');
    } finally {
      setSavedLoading(false);
    }
  }, []);

  useEffect(() => {
    if (!selectedSubjectId) {
      setSavedSummaries([]);
      setPendingFile(null);
      setSelectedFileName(null);
      setUploadResult(null);
      setRecordResult(null);
      return;
    }

    setSavedSummaries([]);
    setPendingFile(null);
    setSelectedFileName(null);
    setUploadResult(null);
    setRecordResult(null);
    fetchSavedSummaries(selectedSubjectId);
  }, [selectedSubjectId, fetchSavedSummaries]);

  useEffect(() => {
    if (embedded) return;
    if (!selectedSubjectId) return;
    if (isLoading || savedLoading) return;

    const syncLatestResult = (
      latest: SavedAudioSummary | undefined,
      current: VoiceSummaryResponse | null,
      setter: (value: VoiceSummaryResponse | null) => void
    ) => {
      if (!latest) return false;

      const nextSummary = latest.summary ?? '';
      const nextTranscript = latest.transcript ?? '';
      const currSummary = current?.summary ?? '';
      const currTranscript = current?.transcript ?? '';

      // ถ้ามีผลลัพธ์แสดงอยู่แล้ว ไม่บังคับทับทันที
      if (current) {
        return false;
      }

      if (nextSummary === currSummary && nextTranscript === currTranscript) {
        return false;
      }

      setter({ summary: latest.summary, transcript: latest.transcript });
      return true;
    };

    const syncedUpload = syncLatestResult(uploadedSummaries[0], uploadResult, setUploadResult);
    const syncedRecord = syncLatestResult(recordedSummaries[0], recordResult, setRecordResult);

    if (syncedUpload || syncedRecord) {
      setError(null);
      setSelectedFileName(null);
      setTimer(0);
      setStatusText('แสดงสรุปจากเสียงล่าสุดแล้ว');
    }
  }, [embedded, selectedSubjectId, isLoading, savedLoading, uploadedSummaries, recordedSummaries]);

  const triggerFileSelect = () => {
    if (isLoading) return;
    fileInputRef.current?.click();
  };

  const pushBellNotification = async (input: { title: string; message: string }) => {
    // Best-effort: if backend fails, local notification already covers UX.
    try {
      const payload: Record<string, any> = {
        type: 'voice_summary',
        title: input.title,
        message: input.message,
        notify_at: new Date().toISOString(),
      };
      if (selectedSubjectId) {
        payload.subject_id = selectedSubjectId;
      }
      await api.post('/notifications', payload);
      window.dispatchEvent(new Event('slt:archive-refresh'));
    } catch {
      // ignore
    }
  };

  const deleteSavedSummary = async (item: SavedAudioSummary) => {
    const confirmed = window.confirm(`ต้องการลบ "${item.title ?? 'สรุปจากเสียง'}" ใช่หรือไม่?`);
    if (!confirmed) return;

    setDeletingSummaryId(item.id);
    setSavedError(null);
    try {
      if (item.id < 0) {
        setSavedSummaries(prev => prev.filter(summary => summary.id !== item.id));
      } else {
        await api.delete(`/ai/summaries/audio/${item.id}`);
        setSavedSummaries(prev => prev.filter(summary => summary.id !== item.id));
      }

      const targetSetter = item.source_mode === 'record' ? setRecordResult : setUploadResult;
      const targetResult = item.source_mode === 'record' ? recordResult : uploadResult;
      if (
        targetResult &&
        (targetResult.summary ?? '') === (item.summary ?? '') &&
        (targetResult.transcript ?? '') === (item.transcript ?? '')
      ) {
        targetSetter(null);
      }
      setStatusText('ลบสรุปจากเสียงเรียบร้อยแล้ว');
    } catch (err: any) {
      setSavedError(err.response?.data?.message ?? 'ลบสรุปจากเสียงไม่สำเร็จ');
    } finally {
      setDeletingSummaryId(null);
    }
  };

  const clearResult = (source: Exclude<ProcessingSource, null>) => {
    if (source === 'record') {
      setRecordResult(null);
      setLiveTranscript('');
      setLiveSummary('');
    } else {
      setUploadResult(null);
    }
    setError(null);
    setStatusText('ล้างผลลัพธ์เรียบร้อยแล้ว');
  };

  const archiveAudioSummary = async (item: {
    id?: number | null;
    title?: string | null;
    summary?: string | null;
    transcript?: string | null;
  }) => {
    if (!selectedSubjectId) {
      setSavedError('กรุณาเลือกวิชาก่อนเก็บถาวร');
      return;
    }

    const content = (item.summary ?? '').trim() || (item.transcript ?? '').trim();
    if (!content) {
      setSavedError('ยังไม่มีเนื้อหาสำหรับเก็บถาวร');
      return;
    }

    const archiveKey = Number.isFinite(Number(item.id)) ? Number(item.id) : -1;
    setArchivingSummaryId(archiveKey);
    setSavedError(null);
    try {
      await api.post(`/subjects/${selectedSubjectId}/summary-archives`, {
        name: item.title?.trim() || 'สรุปจากเสียง',
        description: content,
      });
      window.dispatchEvent(new Event('slt:archive-refresh'));
      setStatusText('เก็บถาวรสรุปจากเสียงเรียบร้อยแล้ว');
    } catch (err: any) {
      setSavedError(err.response?.data?.message ?? 'เก็บถาวรสรุปจากเสียงไม่สำเร็จ');
    } finally {
      setArchivingSummaryId(null);
    }
  };

  const handleFileSelected = (file: File) => {
    if (!file.type.startsWith('audio/')) {
      setError('กรุณาเลือกไฟล์เสียงเท่านั้น');
      setStatusText('ไฟล์ที่อัปโหลดไม่ใช่เสียง กรุณาลองใหม่');
      return;
    }

    if (!selectedSubjectId) {
      setError('กรุณาเลือกวิชาเพื่อบันทึกเสียงลงฐานข้อมูล');
      setStatusText('กรุณาเลือกวิชา ก่อนเริ่มสรุปเสียง');
      return;
    }

    setSelectedFileName(file.name);
    setPendingFile(file);
    setError(null);
    setUploadResult(null);
    setStatusText('ไฟล์พร้อมสรุปแล้ว กดปุ่ม “สรุปไฟล์นี้” เพื่อเริ่มประมวลผล');
  };

  const handleFileChange = (event: ChangeEvent<HTMLInputElement>) => {
    const file = event.target.files?.[0];
    if (file) {
      handleFileSelected(file);
    }
    event.target.value = '';
  };

  const handleDrop = (event: DragEvent<HTMLDivElement>) => {
    event.preventDefault();
    if (isLoading) return;
    setIsDragging(false);
    const file = event.dataTransfer.files?.[0];
    if (file) {
      handleFileSelected(file);
    }
  };

  const handleDragOver = (event: DragEvent<HTMLDivElement>) => {
    event.preventDefault();
    if (!isDragging) setIsDragging(true);
  };

  const handleDragLeave = (event: DragEvent<HTMLDivElement>) => {
    event.preventDefault();
    setIsDragging(false);
  };

  const summarizePendingFile = () => {
    if (!pendingFile || isLoading) return;
    if (!selectedSubjectId) {
      setError('กรุณาเลือกวิชาเพื่อบันทึกเสียงลงฐานข้อมูล');
      setStatusText('กรุณาเลือกวิชา ก่อนเริ่มสรุปเสียง');
      return;
    }
    setStatusText('กำลังอัปโหลดและสรุปจากไฟล์เสียง...');
    processAudio(pendingFile, 'upload');
  };

  const toggleRecording = async () => {
    if (isRecording) {
      stopRecording();
    } else {
      await startRecording();
    }
  };

  const startRecording = async () => {
    if (!isRecorderSupported) {
      setError('เบราว์เซอร์นี้ไม่รองรับการบันทึกเสียงด้วย MediaRecorder');
      return;
    }

    if (!selectedSubjectId) {
      setError('กรุณาเลือกวิชาเพื่อบันทึกเสียงลงฐานข้อมูล');
      return;
    }

    try {
      const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
      const preferredMimeTypes = [
        'audio/webm;codecs=opus',
        'audio/webm',
        'audio/ogg;codecs=opus',
        'audio/ogg',
      ];
      const supportedMimeType = preferredMimeTypes.find(type =>
        typeof MediaRecorder !== 'undefined' && MediaRecorder.isTypeSupported(type)
      );
      const recorder = supportedMimeType
        ? new MediaRecorder(stream, { mimeType: supportedMimeType })
        : new MediaRecorder(stream);
      const SpeechRecognitionCtor =
        (window as any).SpeechRecognition ?? (window as any).webkitSpeechRecognition;
      chunksRef.current = [];

      recorder.ondataavailable = event => {
        if (event.data.size > 0) {
          chunksRef.current.push(event.data);
        }
      };

      recorder.onstop = () => {
        stream.getTracks().forEach(track => track.stop());
        mediaRecorderRef.current = null;
        if (chunksRef.current.length) {
          const audioBlob = new Blob(chunksRef.current, { type: 'audio/webm' });
          const recordedFile = new File([audioBlob], 'recording.webm', {
            type: 'audio/webm',
          });
          processAudio(recordedFile, 'record');
        }
      };

      recorder.start();
      mediaRecorderRef.current = recorder;
      if (SpeechRecognitionCtor) {
        try {
          const recognition = new SpeechRecognitionCtor();
          recognition.lang = 'th-TH';
          recognition.continuous = true;
          recognition.interimResults = true;
          recognition.onresult = (event: any) => {
            const fullText = Array.from(event.results ?? [])
              .map((item: any) => item?.[0]?.transcript ?? '')
              .join(' ')
              .replace(/\s+/g, ' ')
              .trim();
            setLiveTranscript(fullText);
            setLiveSummary(buildRealtimeSummary(fullText));
          };
          recognition.onerror = () => {
            setStatusText('กำลังบันทึกเสียง... (ถอดเสียงเรียลไทม์ใช้งานไม่ได้ชั่วคราว)');
          };
          recognition.onend = () => {
            if (mediaRecorderRef.current && mediaRecorderRef.current.state !== 'inactive') {
              try {
                recognition.start();
              } catch {
                // ignore restart errors
              }
            }
          };
          recognition.start();
          speechRecognitionRef.current = recognition;
        } catch {
          speechRecognitionRef.current = null;
        }
      }

      setIsRecording(true);
      setTimer(0);
      setError(null);
      setRecordResult(null);
      setSelectedFileName(null);
      setLiveTranscript('');
      setLiveSummary('');
      setStatusText('กำลังบันทึกเสียง... กดอีกครั้งเพื่อหยุด');

      timerRef.current = window.setInterval(() => {
        setTimer(prev => prev + 1);
      }, 1000);
    } catch (err) {
      console.error(err);
      setError('ไม่สามารถเข้าถึงไมโครโฟนได้ กรุณาอนุญาตการใช้งานไมโครโฟน');
    }
  };

  const stopRecording = () => {
    if (timerRef.current) {
      window.clearInterval(timerRef.current);
      timerRef.current = null;
    }

    const recorder = mediaRecorderRef.current;
    if (recorder && recorder.state !== 'inactive') {
      recorder.stop();
    }
    if (speechRecognitionRef.current) {
      try {
        speechRecognitionRef.current.stop();
      } catch {
        // ignore
      }
      speechRecognitionRef.current = null;
    }

    setIsRecording(false);
    setStatusText('กำลังประมวลผลเสียงจากไมโครโฟน...');
  };

  const summarizeTranscriptLikeDocument = async (transcript: string) => {
    const text = transcript.trim();
    if (!text) return '';

    const formData = new FormData();
    const txtBlob = new Blob([text], { type: 'text/plain' });
    formData.append('file', txtBlob, 'audio-transcript.txt');

    try {
      const response = await api.post('/ai/summarize/document', formData);
      const payload = response.data ?? {};
      const summary = String(payload.summary ?? payload.text ?? '').trim();
      return summary || buildDocumentStyleSummaryFallback(text);
    } catch {
      return buildDocumentStyleSummaryFallback(text);
    }
  };

  const transcribeLargeAudioByChunks = async (blob: Blob) => {
    const AudioContextCtor =
      (window as any).AudioContext ?? (window as any).webkitAudioContext;
    if (!AudioContextCtor) {
      throw new Error('เบราว์เซอร์ไม่รองรับการแบ่งไฟล์เสียงอัตโนมัติ');
    }

    const audioContext = new AudioContextCtor();
    try {
      const arrayBuffer = await blob.arrayBuffer();
      const decoded = await audioContext.decodeAudioData(arrayBuffer.slice(0));
      // Google Speech synchronous recognize supports about 10MB/request.
      // Use a conservative raw WAV target to avoid base64/request overhead issues.
      const targetBytesPerChunk = 4 * 1024 * 1024; // 4MB
      const bytesPerSample = 2;
      const bytesPerFrame = decoded.numberOfChannels * bytesPerSample;
      const samplesPerChunk = Math.max(
        1,
        Math.floor(targetBytesPerChunk / Math.max(1, bytesPerFrame))
      );
      const totalSamples = decoded.length;
      const totalChunks = Math.ceil(totalSamples / samplesPerChunk);

      const transcripts: string[] = [];
      for (let chunkIndex = 0; chunkIndex < totalChunks; chunkIndex += 1) {
        const startSample = chunkIndex * samplesPerChunk;
        const endSample = Math.min(totalSamples, startSample + samplesPerChunk);
        const wavBlob = encodeWavChunk(decoded, startSample, endSample);

        const formData = new FormData();
        formData.append('file', wavBlob, `audio-chunk-${chunkIndex + 1}.wav`);

        setStatusText(`กำลังถอดเสียงช่วงที่ ${chunkIndex + 1}/${totalChunks}...`);
        const response = await api.post('/ai/transcribe/audio', formData);
        const text = String(response.data?.text ?? '').trim();
        if (text) transcripts.push(text);
      }

      const mergedTranscript = transcripts.join('\n').trim();
      if (!mergedTranscript) {
        throw new Error('ถอดเสียงจากไฟล์ไม่สำเร็จ');
      }

      const docStyleSummary = await summarizeTranscriptLikeDocument(mergedTranscript);

      return {
        transcript: mergedTranscript,
        summary: docStyleSummary || buildDocumentStyleSummaryFallback(mergedTranscript),
      };
    } finally {
      try {
        await audioContext.close();
      } catch {
        // ignore
      }
    }
  };

  const processAudio = async (blob: Blob, source: ProcessingSource) => {
    setIsLoading(true);
    setProcessingSource(source);
    setError(null);
    setLiveTranscript('');
    setLiveSummary('');
    if (source === 'record') {
      setRecordResult(null);
    } else {
      setUploadResult(null);
    }

    const formData = new FormData();
    const fileName = source === 'upload' && blob instanceof File ? blob.name : 'recording.webm';
    const isCareer = analysisType === 'career';
    const shouldUseChunkMode =
      source === 'upload' &&
      !isCareer &&
      blob.size >= LARGE_UPLOAD_THRESHOLD_BYTES;

    if (shouldUseChunkMode) {
      try {
        setStatusText('ไฟล์ขนาดใหญ่ ระบบกำลังแบ่งไฟล์และถอดเสียงอัตโนมัติ...');
        const chunked = await transcribeLargeAudioByChunks(blob);
        const nextResult = {
          transcript: chunked.transcript,
          summary: chunked.summary,
        };
        setUploadResult(nextResult);
        setLiveTranscript(chunked.transcript);
        setLiveSummary(chunked.summary);
        onSummaryReady?.({
          summary: chunked.summary,
          transcript: chunked.transcript,
          source: 'upload',
        });
        setPendingFile(null);
        setStatusText('สรุปเสร็จแล้ว (โหมดแบ่งไฟล์อัตโนมัติ)');
        return;
      } catch (chunkErr: any) {
        const chunkMessage =
          chunkErr?.response?.data?.message ||
          chunkErr?.message ||
          'แบ่งไฟล์อัตโนมัติไม่สำเร็จ';
        setError(chunkMessage);
        setStatusText('เกิดข้อผิดพลาดในการแบ่งไฟล์เสียงอัตโนมัติ');
        return;
      }
    }

    const fileField = isCareer ? 'audio' : 'file';
    formData.append(fileField, blob, fileName);
    if (selectedSubjectId) {
      formData.append('subject_id', selectedSubjectId);
    }
    if (source) {
      formData.append('source_mode', source);
    }

    const endpoint = isCareer ? '/ai/analyze/career/audio' : '/ai/summarize/audio';
    const client = isCareer ? aiApi : api;

    try {
      const response = await client.post(endpoint, formData);
      const payload = response.data ?? {};
      let resolvedSummary = String(payload.summary ?? payload.analysis ?? '').trim();
      const resolvedTranscript = String(payload.transcript ?? payload.text ?? '').trim();

      if (!resolvedSummary && resolvedTranscript) {
        resolvedSummary = await summarizeTranscriptLikeDocument(resolvedTranscript);
      }

      if (!resolvedSummary && !resolvedTranscript) {
        throw new Error('ไม่สามารถถอดเสียงหรือสรุปจากไฟล์นี้ได้ กรุณาลองไฟล์อื่นหรืออัปโหลดใหม่');
      }

      const nextResult = {
        transcript: resolvedTranscript,
        summary: resolvedSummary
      };
      if (source === 'record') {
        setRecordResult(nextResult);
      } else {
        setUploadResult(nextResult);
      }
      onSummaryReady?.({
        summary: resolvedSummary || buildDocumentStyleSummaryFallback(resolvedTranscript),
        transcript: resolvedTranscript,
        source: source === 'record' ? 'record' : 'upload',
      });
      setPendingFile(null);
      setLiveTranscript(resolvedTranscript);
      setLiveSummary(
        resolvedSummary ||
          buildDocumentStyleSummaryFallback(resolvedTranscript)
      );
      setStatusText('สรุปเสร็จแล้ว! เลือกไฟล์ใหม่หรือลองอัดอีกครั้งได้เลย');
      const title = 'สรุปจากเสียงเสร็จแล้ว';
      const message = truncateText(resolvedSummary || resolvedTranscript || 'สรุปจากเสียงเสร็จแล้ว');
      void pushBellNotification({ title, message });
      if (selectedSubjectId) {
        fetchSavedSummaries(selectedSubjectId);
      }
    } catch (err) {
      console.error('voice-summary request failed', {
        endpoint,
        status: (err as any)?.response?.status,
        data: (err as any)?.response?.data,
      });
      const serverMessage = (err as any)?.response?.data?.message;
      const serverDetail = (err as any)?.response?.data?.detail;
      const serverErrors = (err as any)?.response?.data?.errors;
      const firstError = serverErrors ? serverErrors[Object.keys(serverErrors)[0]]?.[0] : null;
      const message =
        (typeof serverMessage === 'string' && serverMessage.trim() !== '' && serverMessage) ||
        (typeof serverDetail === 'string' && serverDetail.trim() !== '' && serverDetail) ||
        (typeof firstError === 'string' && firstError.trim() !== '' && firstError) ||
        (err instanceof Error ? err.message : 'เกิดข้อผิดพลาดระหว่างการประมวลผล');

      // Auto fallback for long files when Google Speech synchronous payload hits 10MB limit.
      if (
        source === 'upload' &&
        (isGooglePayloadLimitError(message) || isGoogleMonoChannelError(message) || isLowConfidenceTranscriptError(message))
      ) {
        try {
          setError(null);
          setStatusText('ระบบกำลังแบ่งไฟล์และถอดเสียงอัตโนมัติ...');
          const chunked = await transcribeLargeAudioByChunks(blob);
          const nextResult = {
            transcript: chunked.transcript,
            summary: chunked.summary,
          };
          setUploadResult(nextResult);
          setLiveTranscript(chunked.transcript);
          setLiveSummary(chunked.summary);
          onSummaryReady?.({
            summary: chunked.summary,
            transcript: chunked.transcript,
            source: 'upload',
          });
          setPendingFile(null);
          setStatusText('สรุปเสร็จแล้ว (โหมดแบ่งไฟล์อัตโนมัติ)');
          return;
        } catch (chunkErr: any) {
          const chunkMessage =
            chunkErr?.response?.data?.message ||
            chunkErr?.message ||
            'แบ่งไฟล์อัตโนมัติไม่สำเร็จ';
          setError(chunkMessage);
          setStatusText('เกิดข้อผิดพลาดในการแบ่งไฟล์เสียงอัตโนมัติ');
          return;
        }
      }

      setError(message);
      setStatusText('เกิดข้อผิดพลาดในการประมวลผลเสียง กรุณาลองอีกครั้ง');
    } finally {
      setIsLoading(false);
      setProcessingSource(null);
      setTimer(0);
    }
  };

  const renderResultPanel = ({ source, data }: ResultBucket) => {
    const title = source === 'record' ? 'ผลการสรุปจากการบันทึกสด' : 'ผลการสรุปจากไฟล์เสียง';
    const description =
      source === 'record'
        ? 'ระบบจะแสดงผลสรุปจากเสียงที่อัดสดในส่วนนี้โดยเฉพาะ'
        : 'ระบบจะแสดงผลสรุปจากไฟล์เสียงที่อัปโหลดในส่วนนี้โดยเฉพาะ';
    const archiveId = source === 'record' ? -2 : 0;
    const archiveTitle =
      source === 'record'
        ? 'สรุปจากการอัดสด'
        : selectedFileName || 'สรุปจากไฟล์เสียง';

    return (
      <section className={`doc-result-panel ${shellClass} ${source === 'upload' ? 'border-l-[4px] border-l-[color:var(--accent)]' : 'border-l-[4px] border-l-[color:var(--border)]'}`}>
        <div className="flex flex-col gap-3 border-b border-[color:var(--border)] px-6 py-5 sm:flex-row sm:items-start sm:justify-between">
          <div>
            <h2 className="text-xl font-bold tracking-[-0.03em] text-[color:var(--text)]">{title}</h2>
            <p className="text-sm text-muted">{description}</p>
          </div>
          <div className="flex items-center gap-2">
            {data ? (
              <button
                type="button"
                onClick={() =>
                  void archiveAudioSummary({
                    id: archiveId,
                    title: archiveTitle,
                    summary: data.summary,
                    transcript: data.transcript,
                  })
                }
                disabled={archivingSummaryId === archiveId || !selectedSubjectId}
                className={`doc-toolbar-btn rounded-full px-4 py-2 text-xs font-semibold transition disabled:cursor-not-allowed disabled:opacity-60 ${
                  embedded
                    ? 'border border-[color:var(--border)] bg-[color:var(--surface)] text-[color:var(--accent)] hover:opacity-90'
                    : 'border border-[color:rgba(var(--accent-rgb),0.24)] bg-[color:var(--surface)] text-[color:var(--accent)] hover:bg-[color:rgba(var(--accent-rgb),0.08)]'
                }`}
              >
                {archivingSummaryId === archiveId ? 'กำลังเก็บถาวร...' : 'เก็บถาวร'}
              </button>
            ) : null}
            {data ? (
              <button
                type="button"
                onClick={() => clearResult(source)}
                className={`doc-toolbar-btn rounded-full border px-4 py-2 text-xs font-semibold transition ${
                  embedded
                    ? 'border-[color:var(--border)] bg-[color:var(--surface)] text-muted hover:opacity-90'
                    : 'border-[color:var(--border)] bg-[color:var(--surface-2)] text-muted hover:opacity-85'
                }`}
              >
                ล้างผลลัพธ์
              </button>
            ) : null}
          </div>
        </div>

        <div className="space-y-4 px-6 py-6">
          <div className={`rounded-[24px] p-5 ${embedded ? 'border border-[color:rgba(var(--accent-rgb),0.18)] bg-[color:rgba(var(--accent-rgb),0.08)]' : 'border border-[color:rgba(var(--accent-rgb),0.18)] bg-[color:rgba(var(--accent-rgb),0.08)]'}`}>
            <div className="flex flex-wrap items-center justify-between gap-2">
              <p className="text-sm font-semibold text-[color:var(--text)]">ใจความสำคัญล่าสุด</p>
            </div>
            <ExpandableText
              text={data?.summary || data?.transcript || ''}
              previewChars={420}
              className="mt-3 whitespace-pre-wrap text-sm leading-7 text-[color:var(--text)]/90"
              buttonClassName={`mt-3 text-sm font-semibold transition hover:opacity-80 text-[color:var(--accent)]`}
              emptyText="ยังไม่มีข้อมูล กรุณาเริ่มสรุปเสียงในส่วนนี้ก่อน"
            />
          </div>

          {data?.transcript ? (
            <div className="rounded-2xl border border-[color:var(--border)] bg-[color:var(--surface-2)] p-4">
              <p className="text-sm font-semibold text-[color:var(--text)]">ถอดเสียงจากไฟล์</p>
              <ExpandableText
                text={data.transcript}
                previewChars={520}
                className="mt-2 whitespace-pre-wrap text-sm text-muted"
                buttonClassName={`mt-3 text-sm font-semibold transition hover:opacity-80 text-[color:var(--accent)]`}
              />
            </div>
          ) : null}

          <div className="doc-result-empty rounded-2xl border border-dashed border-[color:var(--border)] bg-[color:var(--surface-2)] p-6 text-center text-sm text-muted">
            ระบบจะแยกผลลัพธ์ของแต่ละโหมดออกจากกันเพื่อให้ดูง่ายขึ้น
          </div>
        </div>
      </section>
    );
  };

  const renderSavedSection = () => (
    <section className={`doc-summary-card ${shellClass}`}>
      <div className="flex items-center justify-between border-b border-[color:var(--border)] px-6 py-5">
        <h2 className="text-xl font-bold tracking-[-0.03em] text-[color:var(--text)]">สรุปจากเสียงที่บันทึกไว้</h2>
        <span className={`rounded-full px-3 py-1 text-xs font-semibold ${embedded ? 'bg-[color:var(--surface-2)] text-muted border border-[color:var(--border)]' : 'bg-[color:var(--surface-2)] text-muted'}`}>{savedSummaries.length} รายการ</span>
      </div>
      <div className="space-y-6 px-6 py-6">
        <p className="text-sm text-muted">รวมรายการสรุปจากไฟล์เสียงและการอัดสดไว้ในที่เดียว</p>

        {savedLoading ? (
          <div className="flex items-center gap-2 text-sm text-muted">
            <span className="h-5 w-5 animate-spin rounded-full border-2 border-[color:rgba(var(--accent-rgb),0.24)] border-t-[color:var(--accent)]"></span>
            <p>กำลังโหลดสรุปจากเสียง...</p>
          </div>
        ) : savedError ? (
          <p className="rounded-2xl border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-sm text-rose-300">{savedError}</p>
        ) : savedSummaries.length === 0 ? (
          <div className="doc-empty-state rounded-2xl border border-dashed border-[color:var(--border)] bg-[color:var(--surface-2)] px-4 py-6 text-center text-sm text-muted">
            ยังไม่มีสรุปจากเสียงที่บันทึกไว้
          </div>
        ) : (
          <div className="space-y-3">
            {savedSummaries.map(item => (
              <article key={item.id} className="rounded-2xl border border-[color:var(--border)] bg-[color:var(--surface-2)] px-4 py-3 shadow-sm">
                <div className="flex flex-wrap items-start justify-between gap-2">
                  <div>
                    <div className="flex flex-wrap items-center gap-2">
                      <p className="text-xs uppercase tracking-widest text-muted">{item.subject ?? 'ไม่ระบุวิชา'}</p>
                      <span className="rounded-full bg-[color:var(--surface)] px-2.5 py-1 text-[11px] font-semibold text-muted">
                        {item.source_mode === 'record' ? 'อัดสด' : 'ไฟล์เสียง'}
                      </span>
                    </div>
                    <p className="text-sm font-semibold text-[color:var(--text)]">
                      {item.title ?? (item.source_mode === 'record' ? 'สรุปจากการอัดสด' : 'สรุปจากเสียง')}
                    </p>
                  </div>
                  <div className="flex items-center gap-2">
                    {item.created_at ? <span className="text-xs text-muted">{formatSummaryTime(item.created_at)}</span> : null}
                    <button
                      type="button"
                      onClick={() => void archiveAudioSummary(item)}
                      disabled={archivingSummaryId === item.id}
                      className={`rounded-full px-3 py-1 text-xs font-semibold transition disabled:cursor-not-allowed disabled:opacity-60 ${
                        embedded
                          ? 'border border-[color:var(--border)] bg-[color:var(--surface)] text-[color:var(--accent)] hover:opacity-90'
                          : 'border border-[color:rgba(var(--accent-rgb),0.24)] bg-[color:var(--surface)] text-[color:var(--accent)] hover:bg-[color:rgba(var(--accent-rgb),0.08)]'
                      }`}
                    >
                      {archivingSummaryId === item.id ? 'กำลังเก็บ...' : 'เก็บถาวร'}
                    </button>
                    <button
                      type="button"
                      onClick={() => void deleteSavedSummary(item)}
                      disabled={deletingSummaryId === item.id}
                    className="rounded-full border border-rose-500/30 bg-rose-500/10 px-3 py-1 text-xs font-semibold text-rose-300 transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                      {deletingSummaryId === item.id ? 'กำลังลบ...' : 'ลบ'}
                    </button>
                  </div>
                </div>
                <ExpandableText
                  text={item.summary || item.transcript || ''}
                  previewChars={260}
                  className="mt-2 whitespace-pre-wrap text-sm leading-6 text-muted"
                  buttonClassName={`mt-2 text-sm font-semibold transition hover:opacity-80 text-[color:var(--accent)]`}
                />
              </article>
            ))}
          </div>
        )}
      </div>
    </section>
  );

  return (
    <div className={`ai-summary-theme voice-summary-page doc-summary-page ${embedded ? 'space-y-6' : 'relative min-h-screen overflow-hidden space-y-6 pb-16 bg-transparent font-sans'}`}>
      {!embedded ? (
        <section className="doc-summary-card rounded-[34px] border border-[color:var(--border)] bg-[linear-gradient(135deg,rgba(var(--accent-rgb),0.14)_0%,rgba(var(--accent-rgb),0.05)_100%)] px-6 py-6 shadow-[0_18px_42px_rgba(15,23,42,0.10)]">
          <div className="flex flex-wrap items-start justify-between gap-4">
            <div className="doc-summary-header">
              <span className="inline-flex items-center rounded-full border border-[color:rgba(var(--accent-rgb),0.35)] bg-[color:rgba(var(--accent-rgb),0.08)] px-3 py-1 text-[11px] font-bold uppercase tracking-[0.2em] text-accent">
                Voice Summary
              </span>
              <h1 className="mt-3 text-3xl font-black tracking-tight text-[color:var(--text)]">สรุปจากเสียง</h1>
              <p className="mt-1 text-sm text-muted">อัปโหลดหรืออัดเสียง แล้วให้ AI สรุปใจความสำคัญทันที</p>
            </div>
            <div className="rounded-2xl border border-[color:rgba(var(--accent-rgb),0.22)] bg-[color:var(--surface)]/80 px-4 py-3 text-right">
              <p className="text-[10px] font-bold uppercase tracking-[0.18em] text-muted">บันทึกทั้งหมด</p>
              <p className="mt-1 text-2xl font-black text-[color:var(--text)]">{savedSummaries.length}</p>
            </div>
          </div>
        </section>
      ) : null}
        <div className={mainLayoutClass}>
        <div className={leftPanelLayoutClass}>
          {!embedded ? (
          <section className={`doc-summary-card ${shellClass} xl:col-span-2`}>
            <div className="flex flex-col gap-5 border-b border-[color:var(--border)] px-6 py-6 lg:flex-row lg:items-center lg:justify-between">
              <div className="flex items-center gap-4">
                <span className="doc-summary-header-icon flex h-14 w-14 items-center justify-center rounded-2xl bg-[color:var(--accent)] text-[color:var(--on-accent)] shadow-[0_14px_30px_rgba(var(--accent-rgb),0.24)]">
                  <svg viewBox="0 0 24 24" className="h-7 w-7" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
                    <path d="M12 3a3 3 0 0 0-3 3v6a3 3 0 1 0 6 0V6a3 3 0 0 0-3-3Z" />
                    <path d="M19 10v2a7 7 0 0 1-14 0v-2" />
                    <path d="M12 19v2" />
                  </svg>
                </span>
                <div>
                  <h2 className="text-2xl font-black tracking-[-0.03em] text-[color:var(--text)]">ให้ AI ช่วยสรุปเนื้อหาวิชา</h2>
                  <p className="text-sm text-muted">อัปโหลดไฟล์เสียงหรือบันทึกสดเพื่อให้ระบบวิเคราะห์ทันที</p>
                </div>
              </div>
              <div className="grid w-full gap-3 sm:grid-cols-3 lg:w-auto lg:min-w-[560px]">
                <div>
                  <label className="mb-2 block text-xs font-semibold text-muted">ภาคเรียน</label>
                  <select
                    value={selectedSemesterKey}
                    onChange={event => setSelectedSemesterKey(event.target.value)}
                    className={fieldClass}
                  >
                    {semesterOptions.map(option => (
                      <option key={option.key} value={option.key}>
                        {option.label}
                      </option>
                    ))}
                  </select>
                </div>
                <div>
                  <label className="mb-2 block text-xs font-semibold text-muted">วิชาที่เกี่ยวข้อง</label>
                  <select
                    value={selectedSubjectId}
                    onChange={event => {
                      const value = event.target.value;
                      setSelectedSubjectId(value);
                      if (value) localStorage.setItem(lastSubjectKey, value);
                    }}
                    className={fieldClass}
                  >
                    <option value="">เลือกวิชา</option>
                    {selectableSubjects.map(subject => (
                      <option key={subject.id} value={subject.id}>
                        {subject.name}
                      </option>
                    ))}
                  </select>
                </div>
                <div>
                  <label className="mb-2 block text-xs font-semibold text-muted">ประเภทการวิเคราะห์</label>
                  <select
                    value={analysisType}
                    onChange={e => setAnalysisType(e.target.value as 'summary' | 'career')}
                    className={fieldClass}
                  >
                    <option value="summary">สรุปเนื้อหา</option>
                    <option value="career">วิเคราะห์แนวทางอาชีพ</option>
                  </select>
                </div>
              </div>
            </div>
            <div className="px-6 pb-5 pt-3">
              {subjects.length === 0 ? (
                <p className="text-xs text-muted">
                  ยังไม่มีวิชาในระบบ <Link to="/subjects" className="font-semibold text-[color:var(--accent)]">ไปเพิ่มวิชา</Link>
                </p>
              ) : filteredSubjects.length === 0 && selectedSemesterKey !== 'all' ? (
                <p className="text-xs text-[color:var(--accent)]">เทอมนี้ยังไม่มีวิชาที่ผูกไว้ ระบบแสดงวิชาทั้งหมดให้เลือกแทน</p>
              ) : null}
            </div>
          </section>
          ) : null}
          {showUploadSection ? (
          <section
            id="upload"
            className={`${cardClass} ${initialSection === 'upload' ? activeClass : ''} ${embedded ? 'order-2 border-dashed p-5' : ''}`}
            style={
              embedded
                ? {
                    borderColor: isDarkTheme ? 'rgba(255,255,255,0.14)' : 'var(--border)',
                    background: 'color-mix(in srgb, var(--surface) 92%, transparent)',
                    boxShadow: isDarkTheme ? '0 18px 36px rgba(0,0,0,0.45)' : undefined
                  }
                : undefined
            }
          >
            <div className="mb-4 flex items-center gap-3">
              <span className={`flex h-10 w-10 items-center justify-center rounded-2xl ${embedded ? 'bg-[color:rgba(var(--accent-rgb),0.12)] text-accent' : 'bg-[color:rgba(var(--accent-rgb),0.12)] text-accent'}`}>
                <svg viewBox="0 0 24 24" className="h-6 w-6" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
                  <path d="M9 18V5l12-2v13" />
                  <circle cx="6" cy="18" r="3" />
                  <circle cx="18" cy="16" r="3" />
                </svg>
              </span>
              <div>
                <h2 className="text-xl font-black tracking-[-0.03em] text-[color:var(--text)]">สรุปจากไฟล์เสียง</h2>
                <p className="mt-1 text-sm text-muted">{embedded ? 'อัปโหลดไฟล์เพื่อสรุปทันที' : 'เลือกไฟล์เสียงที่มีอยู่แล้ว แล้วให้ระบบถอดและสรุปใจความทันที'}</p>
              </div>
            </div>

            <div
              className={`doc-upload-zone mt-5 rounded-[28px] border-2 border-dashed px-6 py-10 text-center transition ${
                isDragging
                  ? 'border-[color:var(--accent)] bg-[color:rgba(var(--accent-rgb),0.12)]'
                  : 'border-[color:var(--border)] bg-[color:var(--surface-2)]'
              } ${embedded ? 'px-4 py-8' : ''}`}
              onDrop={handleDrop}
              onDragOver={handleDragOver}
              onDragLeave={handleDragLeave}
            >
              <div className={`mx-auto flex h-24 w-24 items-center justify-center rounded-full ${embedded ? 'bg-[color:rgba(var(--accent-rgb),0.12)] text-[color:var(--accent)]' : 'bg-[color:rgba(var(--accent-rgb),0.12)] text-[color:var(--accent)]'}`}>
                <svg viewBox="0 0 24 24" className="h-12 w-12" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
                  <path d="M9 18V5l12-2v13" />
                  <circle cx="6" cy="18" r="3" />
                  <circle cx="18" cy="16" r="3" />
                </svg>
              </div>
              <h3 className={`mt-5 font-black tracking-[-0.03em] text-[color:var(--text)] ${embedded ? 'text-lg' : 'text-2xl'}`}>
                {selectedFileName ? 'พร้อมสรุปไฟล์เสียงที่เลือกแล้ว' : 'ลากและวางไฟล์เสียง หรือคลิกเพื่อเลือก'}
              </h3>
              <p className="mt-2 text-sm text-muted">
                {selectedFileName || 'ลากและวางไฟล์ MP3, WAV, M4A หรือคลิกเพื่อเลือก'}
              </p>
              <p className="mt-1 text-xs text-muted">
                ไฟล์ใหญ่เกิน 8MB ระบบจะสลับเป็นโหมดแบ่งไฟล์อัตโนมัติเพื่อถอดเสียงให้
              </p>
              <div className="mt-6 flex flex-wrap items-center justify-center gap-3">
                <button
                  type="button"
                  onClick={triggerFileSelect}
                  disabled={isLoading}
                  className={`doc-toolbar-btn rounded-2xl border px-6 py-3 text-sm font-bold transition disabled:cursor-not-allowed disabled:opacity-60 ${
                    embedded
                      ? 'border-[color:var(--border)] bg-[color:var(--surface)] text-[color:var(--text)] shadow-sm hover:border-[color:rgba(var(--accent-rgb),0.22)] hover:text-[color:var(--accent)]'
                      : 'border-[color:var(--border)] bg-[color:var(--surface)] px-8 text-base font-black text-[color:var(--text)] shadow-sm hover:border-[color:rgba(var(--accent-rgb),0.22)] hover:text-[color:var(--accent)]'
                  }`}
                >
                  เลือกไฟล์จากเครื่อง
                </button>
                  <button
                    type="button"
                    onClick={summarizePendingFile}
                    disabled={isLoading || !pendingFile}
                    className={`doc-primary-action rounded-2xl transition disabled:cursor-not-allowed disabled:opacity-50 ${
                      embedded
                        ? 'bg-[color:var(--accent)] px-5 py-3 text-sm font-black text-[color:var(--on-accent)] shadow-[0_16px_32px_rgba(var(--accent-rgb),0.22)] hover:opacity-95'
                        : 'bg-[color:var(--accent)] px-6 py-3 text-base font-bold text-[color:var(--on-accent)] shadow-[0_16px_32px_rgba(var(--accent-rgb),0.22)] hover:opacity-95'
                    }`}
                  >
                    {processingSource === 'upload' && isLoading ? 'กำลังประมวลผล...' : 'สร้างสรุปจากไฟล์'}
                  </button>
              </div>
              <input ref={fileInputRef} type="file" accept="audio/*" className="hidden" onChange={handleFileChange} />
            </div>
            <p className={`mt-4 flex items-center justify-center gap-2 text-xs font-medium text-muted ${embedded ? 'text-center' : ''}`}>
              {processingSource === 'upload' && isLoading ? (
                <span className="h-4 w-4 animate-spin rounded-full border-2 border-[color:rgba(var(--accent-rgb),0.24)] border-t-[color:var(--accent)]"></span>
              ) : null}
              ไฟล์จะถูกอัปโหลดอย่างปลอดภัยและประมวลผลทันที
            </p>
            <p className="mt-1 text-center text-xs text-muted">
              รองรับไฟล์เสียงสูงสุด 50MB (โดยทั่วไปประมาณ 1-2 ชั่วโมง ขึ้นกับคุณภาพเสียง)
            </p>

            {processingSource === 'upload' && isLoading ? (
              <div className={`mt-4 rounded-2xl px-4 py-3 text-sm ${embedded ? 'border border-[color:rgba(var(--accent-rgb),0.16)] bg-[color:rgba(var(--accent-rgb),0.08)] text-[color:var(--text)]' : 'border border-[color:rgba(var(--accent-rgb),0.16)] bg-[color:rgba(var(--accent-rgb),0.08)] text-[color:var(--text)]'}`}>
                กำลังอัปโหลดและถอดใจความจากไฟล์เสียง...
              </div>
            ) : null}
          </section>
          ) : null}

          {showRecordSection ? (
          <section
            id="record"
            className={`${cardClass} ${initialSection === 'record' ? activeClass : ''} ${embedded ? 'order-1 p-5' : ''}`}
            style={
              embedded
                ? {
                    borderColor: isDarkTheme ? 'rgba(255,255,255,0.14)' : 'var(--border)',
                    boxShadow: isDarkTheme ? '0 18px 36px rgba(0,0,0,0.45)' : undefined
                  }
                : undefined
            }
          >
            <div className="mb-4 flex items-center gap-3">
              <span className={`flex h-10 w-10 items-center justify-center rounded-2xl ${embedded ? 'bg-[color:rgba(var(--accent-rgb),0.12)] text-[color:var(--accent)]' : 'bg-[color:rgba(var(--accent-rgb),0.12)] text-[color:var(--accent)]'}`}>
                <svg viewBox="0 0 24 24" className="h-6 w-6" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
                  <path d="M12 1v11" />
                  <path d="M8 5a4 4 0 1 1 8 0v7a4 4 0 1 1-8 0V5Z" />
                  <path d="M19 10v2a7 7 0 0 1-14 0v-2" />
                  <path d="M12 19v4" />
                </svg>
              </span>
              <div>
                <h2 className="text-xl font-black tracking-[-0.03em] text-[color:var(--text)]">บันทึกสด</h2>
                <div className="mt-1 flex items-center gap-2">
                  <p className="text-sm text-muted">พร้อมเริ่มบันทึกใหม่</p>
                  {embedded ? (
                    <span className="rounded-full px-2 py-0.5 text-[10px] font-bold" style={{ background: 'rgba(var(--accent-rgb),0.16)', color: 'var(--accent)' }}>
                      Live
                    </span>
                  ) : null}
                </div>
              </div>
            </div>

            <div className={`doc-upload-zone rounded-[28px] border px-6 py-10 text-center ${embedded ? 'border-dashed border-[color:var(--border)] bg-[color:var(--surface-2)]' : 'border-dashed border-[color:var(--border)] bg-[color:var(--surface-2)]'}`}>
              <div className={`mx-auto flex h-24 w-24 items-center justify-center rounded-full ${embedded ? 'relative bg-[color:rgba(var(--accent-rgb),0.12)] text-[color:var(--accent)]' : 'bg-[color:rgba(var(--accent-rgb),0.12)] text-[color:var(--accent)]'}`}>
                {embedded ? (
                  <>
                    <span className="pointer-events-none absolute -inset-3 rounded-full border border-[color:rgba(var(--accent-rgb),0.24)]" />
                    <span className="pointer-events-none absolute -inset-6 rounded-full border border-[color:rgba(var(--accent-rgb),0.18)]" />
                  </>
                ) : null}
                <svg viewBox="0 0 24 24" className="h-12 w-12" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
                  <path d="M12 1v11" />
                  <path d="M8 5a4 4 0 1 1 8 0v7a4 4 0 1 1-8 0V5Z" />
                  <path d="M19 10v2a7 7 0 0 1-14 0v-2" />
                  <path d="M12 19v4" />
                </svg>
              </div>

            <div className="mt-6 flex flex-col items-center gap-4">
              <button
                type="button"
                onClick={toggleRecording}
                disabled={!isRecorderSupported || isLoading}
                className={`flex h-24 w-24 items-center justify-center rounded-full transition focus:outline-none ${
                  embedded
                    ? `text-[color:var(--on-accent)] shadow-[0_18px_36px_rgba(var(--accent-rgb),0.25)] focus:ring-4 focus:ring-[color:rgba(var(--accent-rgb),0.16)] ${isRecording ? 'animate-pulse bg-[color:var(--accent)]' : 'bg-[color:var(--accent)] hover:scale-105'}`
                    : `text-[color:var(--on-accent)] shadow-[0_18px_36px_rgba(var(--accent-rgb),0.25)] focus:ring-4 focus:ring-[color:rgba(var(--accent-rgb),0.16)] ${isRecording ? 'animate-pulse bg-[color:var(--accent)]' : 'bg-[color:var(--accent)] hover:scale-105'}`
                } ${(!isRecorderSupported || isLoading) && 'cursor-not-allowed opacity-60'}`}
              >
                {isRecording ? (
                  <svg viewBox="0 0 24 24" className="h-10 w-10" fill="currentColor" aria-hidden="true">
                    <rect x="7" y="7" width="10" height="10" rx="2" />
                  </svg>
                ) : (
                  <svg viewBox="0 0 24 24" className="h-10 w-10" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
                    <path d="M12 1v11" />
                    <path d="M8 5a4 4 0 1 1 8 0v7a4 4 0 1 1-8 0V5Z" />
                    <path d="M19 10v2a7 7 0 0 1-14 0v-2" />
                    <path d="M12 19v4" />
                  </svg>
                )}
              </button>

              <div className="text-center">
                <p className={`${embedded ? 'text-4xl' : 'text-5xl'} font-mono font-black text-[color:var(--text)]`}>{formatTimer(timer)}</p>
                <p className="mt-2 text-sm text-muted">{isRecording ? 'กำลังบันทึกเสียง...' : 'พร้อมเริ่มบันทึกใหม่'}</p>
                <p className="mt-1 text-xs text-muted">อัดเสียงได้ตามขนาดไฟล์สูงสุด 50MB (โดยทั่วไปประมาณ 1-2 ชั่วโมง ขึ้นกับคุณภาพเสียง)</p>
              </div>
              {embedded ? (
                <button
                  type="button"
                  onClick={toggleRecording}
                  disabled={!isRecorderSupported || isLoading}
                  className="doc-toolbar-btn rounded-2xl border border-[color:var(--border)] bg-[color:var(--surface)] px-6 py-3 text-base font-black text-[color:var(--text)] shadow-sm transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-60"
                >
                  {isRecording ? 'หยุดและประมวลผล' : 'เริ่มบันทึก'}
                </button>
              ) : null}
            </div>
            </div>

            {!isRecorderSupported ? (
              <div className="mt-4 rounded-2xl border border-[color:var(--border)] bg-[color:var(--surface-2)] p-4 text-sm text-muted">
                เบราว์เซอร์ของคุณไม่รองรับการใช้งาน MediaRecorder กรุณาเปลี่ยนเบราว์เซอร์หรืออัปเดตเวอร์ชันล่าสุด
              </div>
            ) : null}
            {isRecorderSupported && !isLiveRecognitionSupported ? (
              <div className="mt-4 rounded-2xl border border-[color:var(--border)] bg-[color:var(--surface-2)] p-4 text-sm text-muted">
                เบราว์เซอร์นี้ยังไม่รองรับถอดเสียงสดระหว่างอัด ระบบจะสรุปให้อัตโนมัติหลังหยุดอัดเสียง
              </div>
            ) : null}

            {processingSource === 'record' && isLoading ? (
              <div className={`mt-4 rounded-2xl px-4 py-3 text-sm ${embedded ? 'border border-[color:rgba(var(--accent-rgb),0.16)] bg-[color:rgba(var(--accent-rgb),0.08)] text-[color:var(--text)]' : 'border border-[color:rgba(var(--accent-rgb),0.16)] bg-[color:rgba(var(--accent-rgb),0.08)] text-[color:var(--text)]'}`}>
                กำลังประมวลผลเสียงที่บันทึก...
              </div>
            ) : null}
            {(isRecording || liveSummary || liveTranscript) ? (
              <div className="mt-4 rounded-2xl border border-[color:rgba(var(--accent-rgb),0.16)] bg-[color:rgba(var(--accent-rgb),0.08)] p-4">
                <p className="text-sm font-semibold text-[color:var(--text)]">สรุประหว่างอัดเสียง</p>
                <p className="mt-2 whitespace-pre-wrap text-sm text-muted">
                  {liveSummary || liveTranscript || 'กำลังรอเสียงพูด...'}
                </p>
              </div>
            ) : null}
          </section>
          ) : null}

          <div className={`doc-status-banner rounded-2xl border border-[color:rgba(var(--accent-rgb),0.16)] bg-[color:rgba(var(--accent-rgb),0.08)] p-4 text-sm font-medium text-[color:var(--text)] xl:col-span-2 ${embedded ? 'hidden' : ''}`}>
            {statusText}
          </div>

          {error ? (
            <div className={`rounded-2xl border border-rose-500/30 bg-rose-500/10 p-4 text-sm text-rose-300 xl:col-span-2 ${embedded ? 'hidden' : ''}`}>{error}</div>
          ) : null}
        </div>

        <div className={`${embedded ? 'space-y-5' : 'space-y-6'}`}>
          <section
            className={`doc-summary-card ${shellClass}`}
            style={
              embedded
                ? {
                    background: isDarkTheme ? 'rgba(15,20,27,0.92)' : 'color-mix(in srgb, var(--surface) 92%, transparent)',
                    borderColor: isDarkTheme ? 'rgba(255,255,255,0.14)' : 'var(--border)',
                    boxShadow: isDarkTheme ? '0 18px 36px rgba(0,0,0,0.45)' : undefined
                  }
                : isDarkTheme
                  ? { background: 'rgba(15,20,27,0.92)', borderColor: 'rgba(255,255,255,0.10)' }
                  : undefined
            }
          >
            <div className="flex items-center justify-between border-b border-[color:var(--border)] px-6 py-5">
              <h2 className="text-xl font-black tracking-[-0.03em] text-[color:var(--text)]">ผลการสรุปใจความสำคัญ</h2>
              <button
                type="button"
                onClick={() => {
                  clearResult('upload');
                  clearResult('record');
                }}
                className={`text-xs font-bold text-accent hover:opacity-80`}
              >
                รีเฟรชผลลัพธ์
              </button>
            </div>
            <div className={`${embedded ? 'grid gap-3 px-6 py-6' : 'grid gap-4 px-6 py-6 sm:grid-cols-2'}`}>
              <div
                className={`rounded-[22px] p-4 ${embedded ? 'border border-[color:rgba(var(--accent-rgb),0.24)] bg-[color:rgba(var(--accent-rgb),0.08)]' : 'border border-[color:rgba(var(--accent-rgb),0.24)] bg-[color:rgba(var(--accent-rgb),0.08)]'}`}
                style={isDarkTheme ? { background: 'rgba(var(--accent-rgb),0.14)' } : undefined}
              >
                <p className={`text-sm font-bold ${embedded ? 'text-[color:var(--accent)]' : 'text-[color:var(--accent)]'}`}>ผลการสรุปจากไฟล์</p>
                <p className={`mt-2 text-sm ${embedded ? 'text-[color:var(--text)]/90' : 'text-[color:var(--text)]/90'}`}>{uploadPreviewText || 'ยังไม่มีข้อมูล กรุณาเริ่มสรุปไฟล์เสียงก่อน'}</p>
              </div>
              <div
                className={`rounded-[22px] p-4 ${embedded ? 'border border-dashed border-[color:var(--border)] bg-[color:var(--surface-2)]/80' : 'border border-dashed border-[color:var(--border)] bg-[color:var(--surface-2)]/80'}`}
                style={isDarkTheme ? { background: 'rgba(22,27,34,0.90)' } : undefined}
              >
                <p className={`text-sm font-bold ${embedded ? 'text-muted' : 'text-muted'}`}>ผลการสรุปจากการบันทึกสด</p>
                <p className={`mt-2 text-sm ${embedded ? 'text-muted' : 'text-muted'}`}>{recordPreviewText || 'รอการบันทึกเสียงในส่วนนี้...'}</p>
              </div>
            </div>
          </section>

          {showUploadSection ? renderResultPanel({ source: 'upload', data: uploadResult }) : null}
          {embedded ? (
            <div className="grid gap-5 xl:grid-cols-2">
              {showRecordSection ? renderResultPanel({ source: 'record', data: recordResult }) : null}
              {renderSavedSection()}
            </div>
          ) : (
            <>
              {showRecordSection ? renderResultPanel({ source: 'record', data: recordResult }) : null}
              {renderSavedSection()}
            </>
          )}
        </div>
        </div>

      {embedded ? (
        <div className="space-y-3">
          <div className="rounded-2xl border border-[color:rgba(var(--accent-rgb),0.16)] bg-[color:rgba(var(--accent-rgb),0.08)] px-4 py-3 text-sm font-medium text-[color:var(--text)]">
            {statusText}
          </div>
          {error ? (
            <div className="rounded-2xl border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-sm text-rose-300">{error}</div>
          ) : null}
        </div>
      ) : null}
    </div>
  );
};
