import { useEffect, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { useForm } from 'react-hook-form';
import { api } from '../../services/api';
import { getLastQuizResultKey } from '../../constants/storage';
import { useAuth } from '../../context/AuthContext';

interface QuizQuestion {
  id: number;
  question_text: string;
  question_type: 'multiple_choice' | 'true_false' | 'short_answer';
  options?: string[];
}

interface Quiz {
  id: number;
  title: string;
  description?: string | null;
  questions: QuizQuestion[];
}

interface AnswerPayload {
  question_id: number;
  selected_answer: string;
}

interface AttemptResult {
  score: number;
  total: number;
  answers: AttemptAnswer[];
}

interface AttemptAnswer {
  question_id: number;
  selected_answer: string;
  is_correct: boolean;
  correct_answer?: string | null;
  explanation?: string | null;
  question_text?: string;
  question_type?: string;
  options?: string[] | null;
}

interface AttemptApiResponse {
  score?: number;
  total?: number;
  answers?: AttemptAnswer[] | { data?: AttemptAnswer[] } | null;
}

const stripTrailingEllipsis = (value?: string | null) => {
  if (typeof value !== 'string') return '';
  return value.replace(/\s*\.\.\.\s*$/, '').trimEnd();
};

const normalizeQuizDescription = (value?: string | null) => {
  const text = typeof value === 'string' ? value.trim() : '';
  if (!text) return '';

  const lowered = text.toLowerCase();
  if (
    lowered.includes('สร้างแบบฝึกหัด') ||
    lowered.includes('ข้อความจริง') ||
    lowered.includes('เอกสาร') ||
    lowered.includes('readable')
  ) {
    return '';
  }

  return text;
};

const normalizeAttemptResult = (payload: AttemptApiResponse | null | undefined): AttemptResult => {
  const rawAnswers = Array.isArray(payload?.answers)
    ? payload?.answers
    : Array.isArray(payload?.answers?.data)
      ? payload?.answers?.data
      : [];

  return {
    score: Number(payload?.score ?? 0),
    total: Number(payload?.total ?? 0),
    answers: rawAnswers,
  };
};

const extractErrorMessage = (error: any, fallback: string) => {
  const message = error?.response?.data?.message;
  if (typeof message === 'string' && message.trim() !== '') {
    return message;
  }

  const errors = error?.response?.data?.errors;
  if (errors && typeof errors === 'object') {
    const firstKey = Object.keys(errors)[0];
    const firstMessage = firstKey ? errors[firstKey]?.[0] : null;
    if (typeof firstMessage === 'string' && firstMessage.trim() !== '') {
      return firstMessage;
    }
  }

  if (typeof error?.message === 'string' && error.message.trim() !== '') {
    return error.message;
  }

  return fallback;
};

export const QuizAttemptPage = () => {
  const { user } = useAuth();
  const { quizId } = useParams();
  const navigate = useNavigate();
  const [quiz, setQuiz] = useState<Quiz | null>(null);
  const [result, setResult] = useState<AttemptResult | null>(null);
  const [showModal, setShowModal] = useState(false);
  const [submitError, setSubmitError] = useState<string | null>(null);
  const lastResultKey = getLastQuizResultKey(user?.id);
  const {
    register,
    handleSubmit,
    formState: { isSubmitting }
  } = useForm<Record<string, string>>({});

  useEffect(() => {
    if (!quizId) return;
    api.get(`/quizzes/${quizId}`).then(res => {
      const payload = res.data?.data ?? res.data;
      setQuiz(payload ?? null);
    });
  }, [quizId]);

  const onSubmit = async (values: Record<string, string>) => {
    if (!quizId || !quiz) return;
    const payload: AnswerPayload[] = (quiz.questions ?? []).map(question => ({
      question_id: question.id,
      selected_answer: values[`question-${question.id}`]
    }));
    setSubmitError(null);

    try {
      const response = await api.post<AttemptApiResponse>(`/quizzes/${quizId}/attempts`, { answers: payload });
      setResult(normalizeAttemptResult(response.data));
      setShowModal(true);
    } catch (error) {
      setResult(null);
      setShowModal(false);
      setSubmitError(extractErrorMessage(error, 'ไม่สามารถตรวจคำตอบได้'));
    }
  };

  const closeResultModal = () => {
    setShowModal(false);
    navigate('/quizzes');
  };

  if (!quiz) {
    return <p className="text-muted">กำลังโหลดแบบฝึกหัด...</p>;
  }

  return (
    <div className="mx-auto w-full max-w-7xl space-y-6 pb-[calc(124px+env(safe-area-inset-bottom,0px))] lg:pb-8">
      <div className="flex items-center justify-between">
        <button
          onClick={() => navigate(-1)}
          className="inline-flex items-center gap-2 rounded-full border border-muted surface px-4 py-2 text-sm font-semibold text-[color:var(--text)] shadow-sm transition hover:-translate-y-0.5 hover:border-primary/30 hover:bg-primary/5 hover:text-primary"
        >
          <span>กลับไปยังรายการแบบฝึกหัด</span>
        </button>
      </div>
      <div className="w-full rounded-[32px] border border-muted surface p-5 shadow-glow sm:p-6 lg:p-8">
        <header>
          <h2 className="text-2xl font-semibold text-[color:var(--text)] sm:text-3xl">{quiz.title}</h2>
          {normalizeQuizDescription(quiz.description) ? (
            <p className="text-sm text-muted">{normalizeQuizDescription(quiz.description)}</p>
          ) : null}
        </header>
        {submitError ? (
          <div className="rounded-2xl border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-sm text-rose-500">
            {submitError}
          </div>
        ) : null}
        <form onSubmit={handleSubmit(onSubmit)} className="space-y-6">
          {(quiz.questions ?? []).map((question, index) => (
            <div key={question.id} className="w-full rounded-[28px] border border-muted surface-2 px-4 py-4 shadow-sm sm:px-5 lg:px-6">
              <p className="text-xs font-medium uppercase tracking-widest text-muted">ข้อที่ {index + 1}</p>
              <p className="mt-2 whitespace-pre-wrap break-words text-base font-medium text-[color:var(--text)] sm:text-lg">
                {stripTrailingEllipsis(question.question_text)}
              </p>
              {question.question_type === 'multiple_choice' && (
                <div className="mt-4 space-y-3 text-sm text-muted">
                  {question.options?.map(option => (
                    <label key={option} className="flex w-full items-start gap-3 rounded-2xl border border-transparent px-1 py-1 transition hover:border-primary/20 hover:bg-primary/5">
                      <input
                        type="radio"
                        value={option}
                        className="mt-1 shrink-0"
                        {...register(`question-${question.id}`, { required: true })}
                      />
                      <span className="min-w-0 whitespace-pre-wrap break-words text-[color:var(--text)]">
                        {stripTrailingEllipsis(option)}
                      </span>
                    </label>
                  ))}
                </div>
              )}
              {question.question_type === 'true_false' && (
                <div className="mt-4 flex flex-col gap-3 text-sm text-muted sm:flex-row sm:flex-wrap sm:gap-4">
                  {['true', 'false'].map(option => (
                    <label key={option} className="flex items-start gap-3 rounded-2xl border border-transparent px-1 py-1 transition hover:border-primary/20 hover:bg-primary/5">
                      <input type="radio" value={option} className="mt-1 shrink-0" {...register(`question-${question.id}`, { required: true })} />
                      <span className="min-w-0 whitespace-pre-wrap break-words text-[color:var(--text)]">{option === 'true' ? 'ถูก' : 'ผิด'}</span>
                    </label>
                  ))}
                </div>
              )}
              {question.question_type === 'short_answer' && (
                <textarea
                  className="mt-3 w-full rounded-2xl border border-muted surface-2 px-3 py-3 text-sm text-[color:var(--text)] shadow-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/30"
                  {...register(`question-${question.id}`)}
                />
              )}
            </div>
          ))}
          <div className="sticky bottom-[calc(96px+env(safe-area-inset-bottom,0px))] z-20 -mx-1 rounded-[1.75rem] bg-[color:rgba(248,250,252,0.92)] px-1 py-3 backdrop-blur sm:bottom-4 sm:bg-transparent sm:px-0 sm:py-0">
            <button type="submit" className="btn-primary w-full sm:w-auto" disabled={isSubmitting}>
              {isSubmitting ? 'กำลังตรวจคำตอบ...' : 'ส่งคำตอบ'}
            </button>
          </div>
        </form>
      </div>

      {result && showModal && (
        <div className="fixed inset-0 z-50 flex items-end justify-center bg-slate-900/40 px-4 pb-8 pt-10 backdrop-blur sm:items-center">
          <div className="w-full max-w-4xl rounded-3xl border border-muted surface p-6 shadow-glow">
            <div className="flex items-start justify-between">
              <div>
                <h3 className="text-2xl font-semibold text-[color:var(--text)]">ผลการทำแบบฝึกหัด</h3>
                <p className="mt-1 text-sm text-muted">
                  คะแนน {result.score} จาก {result.total}{' '}
                  {result.total > 0 ? `(${Math.round((result.score / result.total) * 100)}%)` : ''}
                </p>
              </div>
              <button onClick={closeResultModal} className="rounded-full bg-primary/10 px-3 py-1 text-sm text-primary hover:bg-primary/20">
                ปิด
              </button>
            </div>

            <div className="mt-5 max-h-[60vh] space-y-3 overflow-y-auto pr-2 text-sm text-muted">
              {result.answers.map(answer => (
                <div
                  key={answer.question_id}
                  className={`space-y-2 rounded-2xl border px-3 py-3 ${
                    answer.is_correct
                      ? 'border-primary/40 bg-primary/10 text-primary'
                      : 'border-rose-500/30 bg-rose-500/10 text-rose-500'
                  }`}
                >
                  <p className="text-sm font-semibold text-[color:var(--text)]">
                    {stripTrailingEllipsis(answer.question_text) || `คำถาม #${answer.question_id}`}
                  </p>
                  <p className="text-xs uppercase tracking-widest">
                    {answer.is_correct ? 'คำตอบถูกต้อง' : 'คำตอบไม่ถูกต้อง'}
                  </p>
                  <div className="rounded-xl surface-2 px-3 py-2 text-sm text-muted">
                    <p className="font-medium">คำตอบของคุณ: {stripTrailingEllipsis(answer.selected_answer) || '-'}</p>
                    {!answer.is_correct && (
                      <p className="mt-1 text-primary">คำตอบที่ถูกต้อง: {stripTrailingEllipsis(answer.correct_answer) || '-'}</p>
                    )}
                  </div>
                  {answer.explanation && (
                    <p className="rounded-xl surface-2 px-3 py-2 text-xs text-muted">
                      {stripTrailingEllipsis(answer.explanation)}
                    </p>
                  )}
                </div>
              ))}
            </div>

            <div className="mt-5 flex flex-col gap-2 sm:flex-row sm:justify-end">
              <button
                onClick={() => {
                  if (result && quiz) {
                    localStorage.setItem(
                      lastResultKey,
                      JSON.stringify({
                        quizId: quiz.id,
                        title: quiz.title,
                        score: result.score,
                        total: result.total,
                        percentage: result.total > 0 ? Math.round((result.score / result.total) * 100) : 0,
                        answered_at: new Date().toISOString()
                      })
                    );
                  }
                  setShowModal(false);
                  navigate('/quizzes');
                }}
                className="btn-primary w-full sm:w-auto"
              >
                กลับไปหน้ารายการแบบฝึกหัด
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};
