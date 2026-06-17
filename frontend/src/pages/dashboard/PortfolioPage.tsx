import { useEffect, useRef, useState } from 'react';
import html2canvas from 'html2canvas';
import jsPDF from 'jspdf';
import {
  ArrowLeft,
  Briefcase,
  CheckCircle2,
  ChevronRight,
  Download,
  Eye,
  GitBranch,
  Globe,
  Image as ImageIcon,
  Pencil,
  Plus,
  Save,
  Sparkles,
  Share2,
  Target,
  Trash2,
  TriangleAlert,
  Upload,
} from 'lucide-react';
import { api, assetBaseURL } from '../../services/api';
import { useAppAlert } from '../../context/AppAlertContext';
import { useAuth } from '../../context/AuthContext';

type PortfolioProject = {
  id?: number;
  project_name: string;
  project_type: string;
  technologies: string;
  project_description: string;
  project_url: string;
  github_url: string;
};

type PortfolioSkill = {
  id?: number;
  skill_name: string;
  skill_level: string;
};

type PortfolioInterest = {
  id?: number;
  interest_name: string;
};

type PortfolioImageItem = {
  id: number;
  image_name?: string | null;
  image_path: string;
  image_type: 'profile' | 'cover' | 'certificate' | 'activity' | 'project' | 'other';
  description?: string | null;
  sort_order?: number;
};

type PortfolioForm = {
  title: string;
  full_name: string;
  nickname: string;
  email: string;
  cover_image: string;
  profile_image: string;
  date_of_birth: string;
  age: string;
  ethnicity: string;
  nationality: string;
  religion: string;
  family_history: string;
  phone: string;
  address: string;
  special_abilities: string;
  father_name: string;
  father_phone: string;
  mother_name: string;
  mother_phone: string;
  education_history: string;
  awards_summary: string;
  theme_color: string;
  is_public: boolean;
  description: string;
  projects: PortfolioProject[];
  skills: PortfolioSkill[];
  interests: PortfolioInterest[];
};

const emptyProject = (): PortfolioProject => ({
  project_name: '',
  project_type: 'ทั่วไป',
  technologies: '',
  project_description: '',
  project_url: '',
  github_url: '',
});

const emptySkill = (): PortfolioSkill => ({
  skill_name: '',
  skill_level: 'beginner',
});

const emptyInterest = (): PortfolioInterest => ({
  interest_name: '',
});

const toDisplayDate = (value?: string | null): string => {
  const raw = (value ?? '').trim();
  if (!raw) return '';
  const isoMatch = raw.match(/^(\d{4})-(\d{2})-(\d{2})$/);
  if (isoMatch) {
    const [, year, month, day] = isoMatch;
    return `${day}/${month}/${year}`;
  }
  const displayMatch = raw.match(/^(\d{2})\/(\d{2})\/(\d{4})$/);
  if (displayMatch) return raw;
  return raw;
};

const toIsoDate = (value?: string | null): string | null => {
  const raw = (value ?? '').trim();
  if (!raw) return null;
  if (/^\d{4}-\d{2}-\d{2}$/.test(raw)) return raw;
  const match = raw.match(/^(\d{2})\/(\d{2})\/(\d{4})$/);
  if (!match) return null;

  const day = Number(match[1]);
  const month = Number(match[2]);
  const year = Number(match[3]);
  const date = new Date(year, month - 1, day);
  if (
    Number.isNaN(date.getTime()) ||
    date.getFullYear() !== year ||
    date.getMonth() !== month - 1 ||
    date.getDate() !== day
  ) {
    return null;
  }

  return `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
};

const normalizeAssetPath = (value?: string | null): string => {
  const raw = String(value ?? '').trim().replace(/\\/g, '/');
  if (!raw) return '';
  return raw
    .replace(/^\/public\/storage\//, '/storage/')
    .replace(/^public\/storage\//, '/storage/');
};

const inferProjectBasePath = (): string => {
  if (typeof window === 'undefined') return '';
  const first = window.location.pathname.split('/').filter(Boolean)[0] ?? '';
  return first ? `/${first}` : '';
};

const buildAbsoluteHostCandidates = (path: string): string[] => {
  if (typeof window === 'undefined') return [];
  const normalized = path.startsWith('/') ? path : `/${path}`;
  const protocol = window.location.protocol || 'http:';
  const host = window.location.hostname || 'localhost';
  const candidates = [`${protocol}//${host}${normalized}`];

  if (host !== 'localhost') candidates.push(`${protocol}//localhost${normalized}`);
  if (host !== '127.0.0.1') candidates.push(`${protocol}//127.0.0.1${normalized}`);

  return candidates;
};

const resolveProfileUrl = (value?: string | null, fallbackBase?: string) => {
  const raw = normalizeAssetPath(value);
  if (!raw) return null;
  if (raw.startsWith('//')) return `https:${raw}`;
  if (raw.startsWith('http')) {
    return raw;
  }
  const base = fallbackBase
    ?.replace(/\/public\/index\.php\/api\/?$/, '')
    ?.replace(/\/index\.php\/api\/?$/, '')
    ?.replace(/\/api\/?$/, '');
  if (!base) {
    const projectBase = inferProjectBasePath();
    if (raw.startsWith('/storage/') || raw.startsWith('/uploads/')) {
      const localPath = `${projectBase}${raw}`;
      const absolute = buildAbsoluteHostCandidates(localPath)[0];
      return absolute || localPath;
    }
    return raw;
  }
  const normalized = raw.startsWith('/') ? raw : `/${raw}`;
  return `${base}${normalized}`;
};

const buildImageCandidates = (value?: string | null, fallbackBase?: string): string[] => {
  const raw = normalizeAssetPath(value);
  if (!raw) return [];

  const candidates: string[] = [];
  const push = (next?: string | null) => {
    if (!next) return;
    const url = next.trim();
    if (!url) return;
    if (!candidates.includes(url)) {
      candidates.push(url);
    }
  };

  push(resolveProfileUrl(raw, fallbackBase));

  if (/^https?:\/\//i.test(raw)) {
    try {
      const parsed = new URL(raw);
      const cleanPath = parsed.pathname.replace(/\\/g, '/');
      if (cleanPath.startsWith('/study/')) {
        push(`${parsed.origin}${cleanPath.replace(/^\/study/, '')}`);
      } else {
        push(`${parsed.origin}/study${cleanPath.startsWith('/') ? cleanPath : `/${cleanPath}`}`);
      }
      push(`${parsed.origin}${cleanPath.replace(/^\/public\/storage\//, '/storage/')}`);
    } catch {
      // ignore malformed absolute URL
    }
  }

  if (/\/public\/uploads\//.test(raw)) {
    push(raw.replace('/public/uploads/', '/uploads/'));
  }

  if (raw.startsWith('/storage/')) {
    const projectBase = inferProjectBasePath();
    if (projectBase) {
      push(`${projectBase}${raw}`);
      buildAbsoluteHostCandidates(`${projectBase}${raw}`).forEach(push);
    }
    buildAbsoluteHostCandidates(raw).forEach(push);
    push(`/study/public${raw}`);
    push(`/public${raw}`);
    push(raw);
  } else if (raw.startsWith('storage/')) {
    const projectBase = inferProjectBasePath();
    if (projectBase) {
      push(`${projectBase}/${raw}`);
      buildAbsoluteHostCandidates(`${projectBase}/${raw}`).forEach(push);
    }
    buildAbsoluteHostCandidates(`/${raw}`).forEach(push);
    push(`/study/public/${raw}`);
    push(`/public/${raw}`);
    push(`/${raw}`);
  } else if (raw.startsWith('/uploads/')) {
    const projectBase = inferProjectBasePath();
    if (projectBase) {
      push(`${projectBase}${raw}`);
      buildAbsoluteHostCandidates(`${projectBase}${raw}`).forEach(push);
    }
    buildAbsoluteHostCandidates(raw).forEach(push);
    push(`/study${raw}`);
    push(raw);
  } else if (raw.startsWith('uploads/')) {
    const projectBase = inferProjectBasePath();
    if (projectBase) {
      push(`${projectBase}/${raw}`);
      buildAbsoluteHostCandidates(`${projectBase}/${raw}`).forEach(push);
    }
    buildAbsoluteHostCandidates(`/${raw}`).forEach(push);
    push(`/study/${raw}`);
    push(`/${raw}`);
  }

  return candidates;
};

const SmartImage = ({
  src,
  sources,
  fallbackBase,
  alt,
  className,
}: {
  src?: string | null;
  sources?: Array<string | null | undefined>;
  fallbackBase?: string;
  alt: string;
  className?: string;
}) => {
  const candidates = (() => {
    const merged: string[] = [];
    const pushUnique = (url: string) => {
      if (!merged.includes(url)) merged.push(url);
    };

    if (Array.isArray(sources) && sources.length > 0) {
      sources.forEach(item => {
        buildImageCandidates(item, fallbackBase).forEach(pushUnique);
      });
      return merged;
    }

    return buildImageCandidates(src, fallbackBase);
  })();
  const candidatesKey = candidates.join('|');
  const [index, setIndex] = useState(0);

  useEffect(() => {
    setIndex(0);
  }, [src, fallbackBase, candidatesKey]);

  const current = candidates[index];
  if (!current) return null;

  return (
    <img
      src={current}
      alt={alt}
      className={className}
      loading="eager"
      decoding="sync"
      onError={() => {
        setIndex(prev => (prev + 1 < candidates.length ? prev + 1 : prev));
      }}
    />
  );
};

export const PortfolioPage = () => {
  const { user, updateUser } = useAuth();
  const { success, error: showError } = useAppAlert();
  const [viewMode, setViewMode] = useState<'edit' | 'preview'>('edit');
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [aiLoadingMode, setAiLoadingMode] = useState<'study' | 'job' | null>(null);
  const [uploadingImage, setUploadingImage] = useState(false);
  const [uploadingWorkImage, setUploadingWorkImage] = useState(false);
  const [portfolioImages, setPortfolioImages] = useState<PortfolioImageItem[]>([]);
  const profileImageInputRef = useRef<HTMLInputElement | null>(null);
  const workImageInputRef = useRef<HTMLInputElement | null>(null);
  const datePickerInputRef = useRef<HTMLInputElement | null>(null);
  const portfolioPrintRef = useRef<HTMLDivElement | null>(null);
  const [form, setForm] = useState<PortfolioForm>({
    title: 'พอร์ตโฟลิโอของฉัน',
    full_name: '',
    nickname: '',
    email: '',
    cover_image: '',
    profile_image: '',
    date_of_birth: '',
    age: '',
    ethnicity: '',
    nationality: '',
    religion: '',
    family_history: '',
    phone: '',
    address: '',
    special_abilities: '',
    father_name: '',
    father_phone: '',
    mother_name: '',
    mother_phone: '',
    education_history: '',
    awards_summary: '',
    theme_color: '#2563EB',
    is_public: true,
    description: 'สวัสดีค่ะ',
    projects: [],
    skills: [],
    interests: [],
  });

  const loadPortfolio = async () => {
    setLoading(true);
    try {
      const res = await api.get('/portfolio');
      const data = res.data ?? {};
      const nextImages: PortfolioImageItem[] = Array.isArray(data.images)
        ? data.images.map((img: any) => ({
            id: Number(img.id),
            image_name: img.image_name ?? null,
            image_path: normalizeAssetPath(String(img.image_path ?? '')),
            image_type: (img.image_type ?? 'other') as PortfolioImageItem['image_type'],
            description: img.description ?? null,
            sort_order: Number(img.sort_order ?? 0),
          }))
        : [];

      setPortfolioImages(nextImages);

      setForm({
        title: data.title ?? 'พอร์ตโฟลิโอของฉัน',
        full_name: data.full_name ?? user?.name ?? '',
        nickname: data.nickname ?? '',
        email: user?.email ?? '',
        cover_image: normalizeAssetPath(data.cover_image ?? data.profile_image ?? user?.profile_pic ?? user?.avatar ?? ''),
        profile_image: normalizeAssetPath(data.profile_image ?? user?.profile_pic ?? user?.avatar ?? ''),
        date_of_birth: toDisplayDate(data.date_of_birth ? String(data.date_of_birth).slice(0, 10) : ''),
        age: data.age ?? '',
        ethnicity: data.ethnicity ?? '',
        nationality: data.nationality ?? '',
        religion: data.religion ?? '',
        family_history: data.family_history ?? '',
        phone: data.phone ?? '',
        address: data.address ?? '',
        special_abilities: data.special_abilities ?? '',
        father_name: data.father_name ?? '',
        father_phone: data.father_phone ?? '',
        mother_name: data.mother_name ?? '',
        mother_phone: data.mother_phone ?? '',
        education_history: data.education_history ?? '',
        awards_summary: data.awards_summary ?? '',
        theme_color: data.theme_color ?? '#2563EB',
        is_public: data.is_public ?? true,
        description: data.description ?? '',
        projects: Array.isArray(data.projects)
          ? data.projects.map((p: any, i: number) => ({
              id: p.id ?? i + 1,
              project_name: p.project_name ?? '',
              project_type: p.project_type ?? 'ทั่วไป',
              technologies: p.technologies ?? '',
              project_description: p.project_description ?? '',
              project_url: p.project_url ?? '',
              github_url: p.github_url ?? '',
            }))
          : [],
        skills: Array.isArray(data.skills)
          ? data.skills.map((s: any, i: number) => ({
              id: s.id ?? i + 1,
              skill_name: s.skill_name ?? '',
              skill_level: s.skill_level ?? 'beginner',
            }))
          : [],
        interests: Array.isArray(data.interests)
          ? data.interests.map((it: any, i: number) => ({
              id: it.id ?? i + 1,
              interest_name: it.interest_name ?? '',
            }))
          : [],
      });
    } catch (err: any) {
      showError(err?.response?.data?.message ?? 'โหลดพอร์ตโฟลิโอไม่สำเร็จ');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    void loadPortfolio();
  }, []);

  useEffect(() => {
    setForm(prev => ({ ...prev, email: user?.email ?? '' }));
  }, [user?.email]);

  const setField = (name: keyof PortfolioForm, value: any) => setForm(prev => ({ ...prev, [name]: value }));

  const updateProject = (index: number, key: keyof PortfolioProject, value: string) => {
    setForm(prev => ({
      ...prev,
      projects: prev.projects.map((p, i) => (i === index ? { ...p, [key]: value } : p)),
    }));
  };

  const updateSkill = (index: number, key: keyof PortfolioSkill, value: string) => {
    setForm(prev => ({
      ...prev,
      skills: prev.skills.map((s, i) => (i === index ? { ...s, [key]: value } : s)),
    }));
  };

  const updateInterest = (index: number, value: string) => {
    setForm(prev => ({
      ...prev,
      interests: prev.interests.map((it, i) => (i === index ? { ...it, interest_name: value } : it)),
    }));
  };

  const parseAiJson = (text: string): any | null => {
    const raw = String(text || '').trim();
    if (!raw) return null;

    const normalizeJsonText = (input: string) =>
      input
        .replace(/^\uFEFF/, '')
        .replace(/[“”]/g, '"')
        .replace(/[‘’]/g, "'")
        .replace(/,\s*([}\]])/g, '$1')
        .replace(/\/\/.*$/gm, '')
        .replace(/\/\*[\s\S]*?\*\//g, '')
        .trim();

    const tryParse = (input: string): any | null => {
      const normalized = normalizeJsonText(input);
      if (!normalized) return null;
      try {
        return JSON.parse(normalized);
      } catch {
        return null;
      }
    };

    const codeBlock = raw.match(/```(?:json)?\s*([\s\S]*?)```/i);
    const candidate = codeBlock?.[1] ?? raw;

    const direct = tryParse(candidate);
    if (direct) return direct;

    // Try all object-like spans and parse the first valid JSON object.
    const spans: Array<{ start: number; end: number }> = [];
    const stack: number[] = [];
    let inString = false;
    let escaped = false;
    for (let i = 0; i < candidate.length; i += 1) {
      const ch = candidate[i];
      if (inString) {
        if (escaped) {
          escaped = false;
          continue;
        }
        if (ch === '\\') {
          escaped = true;
          continue;
        }
        if (ch === '"') inString = false;
        continue;
      }
      if (ch === '"') {
        inString = true;
        continue;
      }
      if (ch === '{') stack.push(i);
      if (ch === '}' && stack.length > 0) {
        const start = stack.pop() as number;
        spans.push({ start, end: i });
      }
    }

    spans.sort((a, b) => (b.end - b.start) - (a.end - a.start));
    for (const span of spans) {
      const parsed = tryParse(candidate.slice(span.start, span.end + 1));
      if (parsed && typeof parsed === 'object') return parsed;
    }

    return null;
  };

  const generateAiDraft = async (mode: 'study' | 'job') => {
    setAiLoadingMode(mode);
    try {
      const skillsSeed = form.skills.map(s => `${s.skill_name}(${s.skill_level})`).filter(Boolean);
      const projectsSeed = form.projects.map(p => p.project_name).filter(Boolean);
      const modeLabel = mode === 'job' ? 'สมัครงาน' : 'สมัครเรียนต่อ';
      const modeDescription =
        mode === 'job'
          ? 'เนื้อหาเน้นการสมัครงาน สายคอมพิวเตอร์/ไอที และถ้อยคำเชิงมืออาชีพสำหรับ HR/ผู้สัมภาษณ์'
          : 'เนื้อหาเน้นการสมัครเรียนต่อ สายคอมพิวเตอร์/ไอที';
      const prompt = `
ช่วยสร้างพอร์ตโฟลิโอเพื่อ "${modeLabel}" เป็นภาษาไทย โดยตอบเป็น JSON เท่านั้น
ข้อบังคับการตอบ:
- ตอบเป็น JSON object เพียว ๆ เท่านั้น
- ห้ามใส่ markdown, ห้ามใส่ \`\`\`, ห้ามมีคำอธิบายก่อนหรือหลัง JSON
- ทุก key ต้องใส่เครื่องหมาย "..."
{
  "title": "string",
  "description": "ข้อความแนะนำตัว 140-220 คำ",
  "skills": [{"skill_name":"string","skill_level":"beginner|intermediate|advanced"}],
  "projects": [{
    "project_name":"string",
    "project_type":"string",
    "technologies":"string",
    "project_description":"string",
    "project_url":"string",
    "github_url":"string"
  }]
}

ข้อกำหนด:
- skills อย่างน้อย 6 รายการ
- projects อย่างน้อย 2 รายการ
- ${modeDescription}
- project_description ระบุปัญหา วิธีทำ ผลลัพธ์

ข้อมูลตั้งต้นของผู้ใช้:
title: ${form.title}
description: ${form.description}
skills เดิม:
${skillsSeed.length > 0 ? `- ${skillsSeed.join('\n- ')}` : '-'}
projects เดิม:
${projectsSeed.length > 0 ? `- ${projectsSeed.join('\n- ')}` : '-'}
`;

      const res = await api.post('/assistant/chat/message', {
        room_id: 'portfolio-ai',
        tool: 'portfolio',
        message: prompt,
      });

      const aiText = res.data?.assistant_message?.message ?? '';
      const parsed = parseAiJson(aiText);
      if (!parsed || typeof parsed !== 'object') {
        throw new Error('AI ส่งข้อมูลไม่เป็น JSON ที่ใช้งานได้');
      }

      const nextProjects = Array.isArray(parsed.projects)
        ? parsed.projects
            .slice(0, 5)
            .map((p: any) => ({
              project_name: String(p?.project_name ?? '').trim(),
              project_type: String(p?.project_type ?? 'งานพัฒนา').trim() || 'งานพัฒนา',
              technologies: String(p?.technologies ?? '').trim(),
              project_description: String(p?.project_description ?? '').trim(),
              project_url: String(p?.project_url ?? '').trim(),
              github_url: String(p?.github_url ?? '').trim(),
            }))
            .filter((p: PortfolioProject) => p.project_name !== '')
        : [];

      const nextSkills = Array.isArray(parsed.skills)
        ? parsed.skills
            .slice(0, 12)
            .map((s: any) => ({
              skill_name: String(s?.skill_name ?? '').trim(),
              skill_level: String(s?.skill_level ?? 'intermediate').trim() || 'intermediate',
            }))
            .filter((s: PortfolioSkill) => s.skill_name !== '')
        : [];

      setForm(prev => ({
        ...prev,
        title: String(parsed.title ?? prev.title).trim() || prev.title,
        description:
          String(parsed.description ?? prev.description)
            .replace(/^ย่อหน้าแนะนำตัวเชิงวิชาการ\s*:?\s*/i, '')
            .trim() || prev.description,
        projects: nextProjects.length > 0 ? nextProjects : prev.projects,
        skills: nextSkills.length > 0 ? nextSkills : prev.skills,
      }));

      success(`AI สร้างข้อมูลพอร์ต${modeLabel}ให้แล้ว ตรวจทานและกดบันทึกได้เลย`);
    } catch (err: any) {
      showError(err?.response?.data?.message ?? err?.message ?? 'AI สร้างข้อมูลไม่สำเร็จ');
    } finally {
      setAiLoadingMode(null);
    }
  };

  const readinessChecks = [
    { label: 'มีหัวข้อพอร์ตชัดเจน', ok: form.title.trim().length >= 10 },
    { label: 'มีข้อความแนะนำตัว', ok: form.description.trim().length >= 120 },
    { label: 'มีทักษะอย่างน้อย 5 รายการ', ok: form.skills.filter(s => s.skill_name.trim() !== '').length >= 5 },
    { label: 'มีผลงานอย่างน้อย 2 โครงการ', ok: form.projects.filter(p => p.project_name.trim() !== '').length >= 2 },
    {
      label: 'ผลงานมีคำอธิบายและเทคโนโลยี',
      ok: form.projects.filter(p => p.project_name.trim() !== '').every(p => p.project_description.trim().length >= 40 && p.technologies.trim().length >= 2),
    },
  ];
  const passedChecks = readinessChecks.filter(c => c.ok).length;
  const readinessScore = Math.round((passedChecks / readinessChecks.length) * 100);
  const savePortfolio = async () => {
    if (!form.title.trim()) {
      showError('กรุณาระบุหัวข้อพอร์ตโฟลิโอ');
      return;
    }

    setSaving(true);
    try {
      const isoDateOfBirth = toIsoDate(form.date_of_birth);
      const normalizedEmail = form.email.trim();
      if (!normalizedEmail) {
        showError('กรุณาระบุอีเมล');
        setSaving(false);
        return;
      }

      const profileRes = await api.put('/auth/profile', {
        email: normalizedEmail,
      });

      if (profileRes?.data?.user) {
        updateUser(profileRes.data.user);
      }

      await api.put('/portfolio', {
        title: form.title,
        full_name: form.full_name || null,
        nickname: form.nickname || null,
        cover_image: form.cover_image || form.profile_image || user?.profile_pic || user?.avatar || null,
        profile_image: form.profile_image || user?.profile_pic || user?.avatar || null,
        date_of_birth: isoDateOfBirth,
        age: form.age || null,
        ethnicity: form.ethnicity || null,
        nationality: form.nationality || null,
        religion: form.religion || null,
        family_history: form.family_history || null,
        phone: form.phone || null,
        address: form.address || null,
        special_abilities: form.special_abilities || null,
        father_name: form.father_name || null,
        father_phone: form.father_phone || null,
        mother_name: form.mother_name || null,
        mother_phone: form.mother_phone || null,
        education_history: form.education_history || null,
        awards_summary: form.awards_summary || null,
        theme_color: form.theme_color,
        is_public: form.is_public,
        description: form.description,
        projects: form.projects.filter(p => p.project_name.trim() !== ''),
        skills: form.skills.filter(s => s.skill_name.trim() !== ''),
        interests: form.interests.filter(it => it.interest_name.trim() !== ''),
      });
      success('บันทึกการเปลี่ยนแปลงเรียบร้อย');
      await loadPortfolio();
    } catch (err: any) {
      showError(err?.response?.data?.message ?? 'บันทึกไม่สำเร็จ');
    } finally {
      setSaving(false);
    }
  };

  const resetPortfolioForm = () => {
    const confirmed = window.confirm('ต้องการล้างข้อมูลฟอร์มเพื่อกรอกใหม่ใช่หรือไม่?');
    if (!confirmed) return;

    setForm(prev => ({
      ...prev,
      title: 'พอร์ตโฟลิโอของฉัน',
      full_name: '',
      nickname: '',
      cover_image: '',
      profile_image: '',
      date_of_birth: '',
      age: '',
      ethnicity: '',
      nationality: '',
      religion: '',
      family_history: '',
      phone: '',
      address: '',
      special_abilities: '',
      father_name: '',
      father_phone: '',
      mother_name: '',
      mother_phone: '',
      education_history: '',
      awards_summary: '',
      theme_color: '#2563EB',
      is_public: true,
      description: '',
      projects: [],
      skills: [],
      interests: [],
    }));
  };

  const uploadProfileImage = async (file: File) => {
    setUploadingImage(true);
    try {
      const formData = new FormData();
      formData.append('image', file);
      const res = await api.post('/portfolio/cover-image', formData, {
        headers: {
          'Content-Type': 'multipart/form-data',
        },
      });
      const uploadedUrl = normalizeAssetPath(res.data?.cover_image ?? '');
      if (!uploadedUrl) {
        throw new Error('อัปโหลดสำเร็จแต่ไม่พบ URL รูป');
      }

      setField('cover_image', uploadedUrl);
      // Keep preview image in sync immediately after upload.
      setField('profile_image', uploadedUrl);
      success('อัปโหลดรูปหน้าปกเรียบร้อย');
    } catch (err: any) {
      showError(err?.response?.data?.message ?? err?.message ?? 'อัปโหลดรูปหน้าปกไม่สำเร็จ');
    } finally {
      setUploadingImage(false);
    }
  };

  const uploadPortfolioWorkImage = async (file: File) => {
    setUploadingWorkImage(true);
    try {
      const formData = new FormData();
      formData.append('image', file);
      formData.append('image_type', 'project');
      formData.append('image_name', file.name);

      const res = await api.post('/portfolio/images', formData);
      const uploaded = res.data?.image;
      if (!uploaded?.id) {
        throw new Error('อัปโหลดสำเร็จแต่ไม่พบข้อมูลรูป');
      }

      setPortfolioImages(prev => [
        ...prev,
        {
          id: Number(uploaded.id),
          image_name: uploaded.image_name ?? file.name,
          image_path: normalizeAssetPath(String(uploaded.image_path ?? '')),
          image_type: (uploaded.image_type ?? 'project') as PortfolioImageItem['image_type'],
          description: uploaded.description ?? null,
          sort_order: Number(uploaded.sort_order ?? prev.length),
        },
      ]);
      success('อัปโหลดรูปผลงานเรียบร้อย');
    } catch (err: any) {
      showError(err?.response?.data?.message ?? err?.message ?? 'อัปโหลดรูปผลงานไม่สำเร็จ');
    } finally {
      setUploadingWorkImage(false);
    }
  };

  const deletePortfolioImage = async (imageId: number) => {
    const confirmed = window.confirm('ต้องการลบรูปผลงานนี้ใช่หรือไม่?');
    if (!confirmed) return;

    try {
      await api.delete(`/portfolio/images/${imageId}`);
      setPortfolioImages(prev => prev.filter(img => img.id !== imageId));
      success('ลบรูปผลงานแล้ว');
    } catch (err: any) {
      showError(err?.response?.data?.message ?? 'ลบรูปผลงานไม่สำเร็จ');
    }
  };

  const updatePortfolioImageDescription = async (imageId: number, description: string) => {
    const normalized = description.trim();
    try {
      await api.put(`/portfolio/images/${imageId}`, {
        description: normalized || null,
      });
      success('บันทึกคำอธิบายรูปแล้ว');
    } catch (err: any) {
      showError(err?.response?.data?.message ?? 'บันทึกคำอธิบายรูปไม่สำเร็จ');
    }
  };

  if (loading) {
    return <div className="rounded-2xl border border-[color:var(--border)] bg-[color:var(--surface)] p-6 text-sm text-muted">กำลังโหลดข้อมูลพอร์ตโฟลิโอ...</div>;
  }

  const formFieldClass =
    'w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm font-medium text-slate-900 placeholder-slate-500 shadow-sm outline-none transition focus:border-orange-500 focus:ring-2 focus:ring-orange-200';
  const panelFieldClass = 'rounded-xl border border-slate-300 bg-white px-4 py-3 shadow-sm';
  const profileImageSources = [form.cover_image, form.profile_image, user?.profile_pic, user?.avatar];
  const resolvePortfolioFileUrl = (path?: string | null) =>
    resolveProfileUrl(path, assetBaseURL || api.defaults.baseURL) || path || '#';

  const handleSavePdf = async () => {
    const target = portfolioPrintRef.current;
    if (!target) {
      showError('ไม่พบเนื้อหาพอร์ตสำหรับสร้าง PDF');
      return;
    }

    const restoredSources: Array<{ element: HTMLImageElement; src: string }> = [];
    try {
      const blobToDataUrl = async (blob: Blob): Promise<string> =>
        new Promise<string>((resolve, reject) => {
          const reader = new FileReader();
          reader.onload = () => resolve(String(reader.result ?? ''));
          reader.onerror = () => reject(new Error('read-failed'));
          reader.readAsDataURL(blob);
        });

      const fetchImageAsDataUrl = async (absoluteSrc: string): Promise<string | null> => {
        try {
          const directResp = await fetch(absoluteSrc, {
            mode: 'cors',
            credentials: 'include',
          });
          if (directResp.ok) {
            const directBlob = await directResp.blob();
            if (directBlob.size > 0) return await blobToDataUrl(directBlob);
          }
        } catch {
          // Fall through to proxy fallback below.
        }

        try {
          const proxyResp = await api.get('/portfolio/image-proxy', {
            params: { url: absoluteSrc },
            responseType: 'blob',
          });
          const proxyBlob = proxyResp.data as Blob;
          if (!proxyBlob || proxyBlob.size === 0) return null;
          return await blobToDataUrl(proxyBlob);
        } catch {
          return null;
        }
      };

      const waitForImageReady = async (image: HTMLImageElement) => {
        if (image.complete && image.naturalWidth > 0) return;
        await new Promise<void>(resolve => {
          let done = false;
          const finish = () => {
            if (done) return;
            done = true;
            resolve();
          };
          const onLoad = () => finish();
          const onError = () => finish();
          image.addEventListener('load', onLoad, { once: true });
          image.addEventListener('error', onError, { once: true });
          window.setTimeout(finish, 2500);
        });
      };

      // Inline image sources as data URLs to reduce CORS/permission issues in html2canvas.
      const images = Array.from(target.querySelectorAll('img'));
      for (const image of images) {
        const originalSrc = image.currentSrc || image.getAttribute('src') || image.src || '';
        if (!originalSrc || originalSrc.startsWith('data:')) continue;
        try {
          const absoluteSrc = new URL(originalSrc, window.location.href).href;
          const dataUrl = await fetchImageAsDataUrl(absoluteSrc);
          if (!dataUrl) continue;
          restoredSources.push({ element: image, src: originalSrc });
          image.setAttribute('src', dataUrl);
          await waitForImageReady(image);
        } catch {
          // Keep original src if conversion fails.
        }
      }

      // Ensure all remaining images are ready before rendering canvas.
      await Promise.all(images.map(waitForImageReady));

      const canvas = await html2canvas(target, {
        scale: 2,
        useCORS: true,
        backgroundColor: '#ededed',
        logging: false,
      });

      const pdf = new jsPDF('p', 'mm', 'a4');
      const pageWidth = 210;
      const pageHeight = 297;
      const imgWidth = pageWidth;
      const imgHeight = (canvas.height * imgWidth) / canvas.width;
      const imgData = canvas.toDataURL('image/png');

      let heightLeft = imgHeight;
      let position = 0;

      pdf.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight);
      heightLeft -= pageHeight;

      while (heightLeft > 0) {
        position = heightLeft - imgHeight;
        pdf.addPage();
        pdf.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight);
        heightLeft -= pageHeight;
      }

      const fileName = `${(form.full_name || form.title || 'portfolio').replace(/[\\/:*?"<>|]/g, '_')}.pdf`;
      pdf.save(fileName);
      success('บันทึกไฟล์ PDF เรียบร้อย');
    } catch (err: any) {
      showError(err?.message ?? 'บันทึก PDF ไม่สำเร็จ');
    } finally {
      restoredSources.forEach(item => item.element.setAttribute('src', item.src));
    }
  };

  const handleSharePortfolio = async () => {
    const shareUrl = window.location.href;
    const shareTitle = form.title || 'พอร์ตโฟลิโอ';
    const shareText = `พอร์ตโฟลิโอของ ${form.full_name || user?.name || 'ผู้สมัคร'}`;

    try {
      if (navigator.share) {
        await navigator.share({
          title: shareTitle,
          text: shareText,
          url: shareUrl,
        });
        return;
      }

      if (navigator.clipboard?.writeText) {
        await navigator.clipboard.writeText(shareUrl);
        success('คัดลอกลิงก์สำหรับแชร์แล้ว');
        return;
      }

      window.prompt('คัดลอกลิงก์นี้เพื่อแชร์', shareUrl);
    } catch (err: any) {
      if (err?.name === 'AbortError') return;
      showError('แชร์ไม่สำเร็จ');
    }
  };

  return (
    <div className={`min-h-full rounded-3xl p-4 md:p-8 ${viewMode === 'preview' ? 'bg-slate-100 dark:bg-slate-900' : 'bg-[#F8FAFC] dark:bg-slate-950'}`}>
      {viewMode === 'edit' ? (
        <div className="mx-auto max-w-5xl">
          <div className="mb-6 rounded-[2rem] border border-white/70 bg-gradient-to-r from-[#ece3f5] to-[#d5edf6] p-5 shadow-sm">
            <div className="flex flex-col gap-5 xl:flex-row xl:items-center xl:justify-between">
              <div className="flex items-start gap-4">
                <div className="mt-1 flex h-12 w-12 items-center justify-center rounded-2xl bg-[#22c55e] text-white shadow-sm">
                  <Sparkles size={22} className="stroke-[1.8]" />
                </div>
                <div>
                  <h2 className="text-[30px] font-extrabold leading-none text-slate-800">ปรับแต่งและตัวอย่าง</h2>
                  <p className="mt-2 text-[15px] font-medium text-slate-500">ผลงานของคุณแบบเรียลไทม์ พร้อมบันทึกการ<br className="hidden xl:block" />เปลี่ยนแปลงได้ทันที</p>
                </div>
              </div>
              <div className="flex flex-wrap items-center gap-3">
                <button onClick={resetPortfolioForm} className="portfolio-action-btn portfolio-btn-clear portfolio-theme-btn-text rounded-2xl border border-white/70 bg-white/55 px-6 py-3 text-sm font-bold transition-all duration-200 hover:-translate-y-0.5 hover:border-rose-300 hover:bg-rose-50/90 hover:text-rose-600">
                  เคลียร์<br />ฟอร์ม
                </button>
                <button onClick={() => setField('projects', [...form.projects, emptyProject()])} className="group portfolio-action-btn portfolio-btn-add portfolio-theme-btn-text inline-flex items-center gap-3 rounded-2xl border border-white/70 bg-white/55 px-4 py-3 text-sm font-bold transition-all duration-200 hover:-translate-y-0.5 hover:border-emerald-300 hover:bg-emerald-50/90 hover:text-emerald-700">
                  <span className="portfolio-theme-btn-text flex h-9 w-9 items-center justify-center rounded-full bg-slate-100 transition-colors duration-200 group-hover:bg-emerald-100"><Plus size={18} /></span>
                  <span>เพิ่ม<br />ผลงาน</span>
                </button>
                <button onClick={() => setViewMode('preview')} className="group portfolio-action-btn portfolio-btn-view portfolio-theme-btn-text inline-flex items-center gap-3 rounded-2xl border border-white/70 bg-white/55 px-4 py-3 text-sm font-bold transition-all duration-200 hover:-translate-y-0.5 hover:border-indigo-300 hover:bg-indigo-50/90 hover:text-indigo-700">
                  <span className="portfolio-theme-btn-text flex h-9 w-9 items-center justify-center rounded-full bg-slate-100 transition-colors duration-200 group-hover:bg-indigo-100"><Eye size={18} /></span>
                  <span>ดูพอร์ต<br />โฟลิโอ</span>
                </button>
                <button onClick={savePortfolio} disabled={saving} className="portfolio-save-btn inline-flex items-center gap-3 rounded-2xl bg-[#22c55e] px-5 py-3 text-sm font-extrabold text-white shadow-sm hover:bg-[#16a34a] disabled:opacity-60">
                  <span className="flex h-9 w-9 items-center justify-center rounded-xl bg-black/10"><Save size={18} /></span>
                  <span>{saving ? 'กำลังบันทึก...' : (<><span>บันทึกการ</span><br /><span>เปลี่ยนแปลง</span></>)}</span>
                </button>
              </div>
            </div>
          </div>
          <style dangerouslySetInnerHTML={{ __html: `
            .theme-dark .portfolio-theme-text { color: #ffffff !important; }
            .theme-light .portfolio-theme-text,
            :root:not(.theme-dark) .portfolio-theme-text { color: #000000 !important; }
            .theme-dark .portfolio-theme-btn-text { color: #ffffff !important; }
            .theme-light .portfolio-theme-btn-text,
            :root:not(.theme-dark) .portfolio-theme-btn-text { color: #000000 !important; }
            .theme-dark .portfolio-action-btn {
              border-color: rgba(148, 163, 184, 0.55) !important;
              background: linear-gradient(180deg, rgba(30, 41, 59, 0.68), rgba(15, 23, 42, 0.72)) !important;
              box-shadow: inset 0 1px 0 rgba(255,255,255,0.14), 0 0 0 1px rgba(96,165,250,0.12), 0 10px 22px -16px rgba(15,23,42,0.9);
            }
            .theme-dark .portfolio-action-btn:hover {
              border-color: rgba(125, 211, 252, 0.75) !important;
              box-shadow: inset 0 1px 0 rgba(255,255,255,0.18), 0 0 0 1px rgba(56,189,248,0.32), 0 14px 28px -18px rgba(14,116,144,0.85);
            }
            .theme-dark .portfolio-save-btn {
              border: 1px solid rgba(16, 185, 129, 0.55);
              box-shadow: inset 0 1px 0 rgba(255,255,255,0.2), 0 0 0 1px rgba(52,211,153,0.25), 0 14px 28px -16px rgba(6,95,70,0.9);
            }
            .theme-light .portfolio-action-btn,
            :root:not(.theme-dark) .portfolio-action-btn {
              border-color: rgba(255, 255, 255, 0.95) !important;
              background: linear-gradient(180deg, rgba(255,255,255,0.78), rgba(255,255,255,0.58)) !important;
              box-shadow: inset 0 1px 0 rgba(255,255,255,0.95), 0 8px 20px -14px rgba(15,23,42,0.22);
            }
            .theme-light .portfolio-action-btn:hover,
            :root:not(.theme-dark) .portfolio-action-btn:hover {
              border-color: rgba(255, 255, 255, 1) !important;
              box-shadow: inset 0 1px 0 rgba(255,255,255,1), 0 14px 24px -14px rgba(15,23,42,0.28);
            }
            .theme-light .portfolio-btn-clear:hover,
            :root:not(.theme-dark) .portfolio-btn-clear:hover { background: linear-gradient(180deg, #fff1f2, #ffe4e6) !important; }
            .theme-light .portfolio-btn-add:hover,
            :root:not(.theme-dark) .portfolio-btn-add:hover { background: linear-gradient(180deg, #ecfdf5, #d1fae5) !important; }
            .theme-light .portfolio-btn-view:hover,
            :root:not(.theme-dark) .portfolio-btn-view:hover { background: linear-gradient(180deg, #eef2ff, #e0e7ff) !important; }
            .theme-light .portfolio-btn-print:hover,
            :root:not(.theme-dark) .portfolio-btn-print:hover { background: linear-gradient(180deg, #f0f9ff, #e0f2fe) !important; }
            .theme-light .portfolio-btn-clear,
            :root:not(.theme-dark) .portfolio-btn-clear { background: linear-gradient(180deg, #fff7ed, #ffedd5) !important; }
            .theme-light .portfolio-btn-add,
            :root:not(.theme-dark) .portfolio-btn-add { background: linear-gradient(180deg, #f0fdf4, #dcfce7) !important; }
            .theme-light .portfolio-btn-view,
            :root:not(.theme-dark) .portfolio-btn-view { background: linear-gradient(180deg, #eff6ff, #dbeafe) !important; }
            .theme-light .portfolio-btn-print,
            :root:not(.theme-dark) .portfolio-btn-print { background: linear-gradient(180deg, #faf5ff, #f3e8ff) !important; }
            .theme-light .portfolio-save-btn,
            :root:not(.theme-dark) .portfolio-save-btn {
              border: 1px solid rgba(99, 102, 241, 0.35);
              background: linear-gradient(135deg, #22c55e 0%, #14b8a6 50%, #3b82f6 100%) !important;
              box-shadow: inset 0 1px 0 rgba(255,255,255,0.22), 0 12px 24px -14px rgba(30,64,175,0.45);
            }
            .theme-light .portfolio-save-btn:hover,
            :root:not(.theme-dark) .portfolio-save-btn:hover {
              background: linear-gradient(135deg, #16a34a 0%, #0d9488 50%, #2563eb 100%) !important;
            }
          ` }} />

          <div className="rounded-3xl border border-white bg-gradient-to-br from-slate-100 to-slate-50 p-8 shadow-sm">
            <div className="mb-6 rounded-2xl border border-white/80 bg-white/60 p-6 dark:bg-slate-900/70">
              <h1 className="portfolio-theme-text mb-1 text-2xl font-bold">{form.title}</h1>
              <p className="portfolio-theme-text text-sm">{form.description || 'แนะนำตัวของคุณ'}</p>
            </div>

            <div className="mb-6 rounded-2xl border border-slate-100 bg-white/90 p-6 shadow-sm">
              <div className="mb-5 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-blue-200 bg-blue-50 p-4">
                <div>
                  <p className="text-sm font-bold text-blue-900">โหมดสมัครเรียนต่อ (AI Assisted)</p>
                  <p className="text-xs text-blue-800">ให้ AI ช่วยจัดข้อความและผลงานให้พร้อมใช้ยื่นสมัคร แล้วคุณตรวจทานก่อนบันทึก</p>
                </div>
                <button
                  onClick={() => void generateAiDraft('study')}
                  disabled={aiLoadingMode !== null}
                  className="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-60"
                >
                  <Sparkles size={16} /> {aiLoadingMode === 'study' ? 'AI กำลังประมวลผล...' : 'AI สร้างพอร์ตสมัครเรียนต่อ'}
                </button>
              </div>
              <div className="mb-5 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-emerald-200 bg-emerald-50 p-4">
                <div>
                  <p className="text-sm font-bold text-emerald-900">โหมดสมัครงาน (AI Assisted)</p>
                  <p className="text-xs text-emerald-800">ให้ AI ช่วยจัดข้อความและผลงานให้อ่านเป็นมืออาชีพสำหรับยื่นสมัครงาน แล้วคุณตรวจทานก่อนบันทึก</p>
                </div>
                <button
                  onClick={() => void generateAiDraft('job')}
                  disabled={aiLoadingMode !== null}
                  className="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 disabled:opacity-60"
                >
                  <Sparkles size={16} /> {aiLoadingMode === 'job' ? 'AI กำลังประมวลผล...' : 'AI สร้างพอร์ตสมัครงาน'}
                </button>
              </div>

              <div className="mb-5 rounded-xl border border-slate-200 bg-white p-4">
                <div className="mb-2 flex items-center justify-between">
                  <p className="text-sm font-bold text-slate-900 dark:text-slate-900">ความพร้อมยื่นสมัคร: {readinessScore}%</p>
                  {readinessScore >= 80 ? (
                    <span className="inline-flex items-center gap-1 text-xs font-semibold text-emerald-700"><CheckCircle2 size={14} /> พร้อมใช้งาน</span>
                  ) : (
                    <span className="inline-flex items-center gap-1 text-xs font-semibold text-amber-700"><TriangleAlert size={14} /> ควรเติมข้อมูลเพิ่ม</span>
                  )}
                </div>
                <div className="mb-3 h-2 overflow-hidden rounded-full bg-slate-200">
                  <div className={`h-full rounded-full ${readinessScore >= 80 ? 'bg-emerald-500' : 'bg-amber-500'}`} style={{ width: `${readinessScore}%` }} />
                </div>
                <div className="grid grid-cols-1 gap-1 md:grid-cols-2">
                  {readinessChecks.map(item => (
                    <div key={item.label} className={`text-xs font-medium ${item.ok ? 'text-emerald-700' : 'text-slate-600'}`}>
                      {item.ok ? '✓' : '•'} {item.label}
                    </div>
                  ))}
                </div>
              </div>

              <div className="mb-6 flex items-center gap-2">
                <div className="rounded-md bg-orange-100 p-1.5 text-orange-700"><Pencil size={18} /></div>
                <h2 className="text-lg font-bold text-slate-900 dark:text-slate-900">ข้อมูลหลัก (General Info)</h2>
              </div>
              <div className="mb-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                <input value={form.title} onChange={e => setField('title', e.target.value)} className={formFieldClass} placeholder="หัวข้อพอร์ตโฟลิโอ" />
                <div className="flex gap-2">
                  <input
                    value={form.cover_image || form.profile_image}
                    readOnly
                    className={formFieldClass}
                    placeholder="ยังไม่ได้อัปโหลดรูปหน้าปก"
                    title="ระบบจะใช้รูปหน้าปกจากการอัปโหลดอัตโนมัติ"
                  />
                  <button
                    type="button"
                    onClick={() => profileImageInputRef.current?.click()}
                    disabled={uploadingImage}
                    className="portfolio-theme-btn-text inline-flex items-center gap-1 rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-bold hover:bg-slate-50 disabled:opacity-60 dark:border-slate-600 dark:bg-slate-800 dark:hover:bg-slate-700"
                  >
                    <Upload size={16} /> {uploadingImage ? 'กำลังอัปโหลด...' : 'อัปโหลดรูปหน้าปก'}
                  </button>
                  <input
                    ref={profileImageInputRef}
                    type="file"
                    accept="image/*"
                    className="hidden"
                    onChange={e => {
                      const file = e.target.files?.[0];
                      if (file) {
                        void uploadProfileImage(file);
                      }
                      e.currentTarget.value = '';
                    }}
                  />
                </div>
              </div>
              <div className="mb-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                <div className="flex gap-2">
                  <input
                    type="text"
                    inputMode="numeric"
                    value={form.date_of_birth}
                    onChange={e => setField('date_of_birth', e.target.value)}
                    className={formFieldClass}
                    placeholder="dd/mm/yyyy"
                  />
                  <button
                    type="button"
                    onClick={() => {
                      const picker = datePickerInputRef.current;
                      if (!picker) return;
                      if (typeof picker.showPicker === 'function') {
                        picker.showPicker();
                      } else {
                        picker.click();
                      }
                    }}
                    className="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-bold text-black hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-white dark:hover:bg-slate-700"
                    aria-label="เลือกวันเกิดจากปฏิทิน"
                    title="เลือกวันเกิดจากปฏิทิน"
                  >
                    📅
                  </button>
                  <input
                    ref={datePickerInputRef}
                    type="date"
                    className="sr-only"
                    value={toIsoDate(form.date_of_birth) ?? ''}
                    onChange={e => setField('date_of_birth', toDisplayDate(e.target.value))}
                    tabIndex={-1}
                    aria-hidden="true"
                  />
                </div>
                <div className={`${panelFieldClass} flex items-center`}>
                  <span className="text-sm font-semibold text-slate-800 dark:text-slate-800">
                    วันเกิด: {form.date_of_birth || '-'}
                  </span>
                </div>
              </div>
              <div className="mb-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                <input value={form.full_name} onChange={e => setField('full_name', e.target.value)} className={formFieldClass} placeholder="ชื่อ - นามสกุล" name="full_name" />
                <input value={form.nickname} onChange={e => setField('nickname', e.target.value)} className={formFieldClass} placeholder="ชื่อเล่น" name="nickname" />
              </div>
              <div className="mb-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                <input value={form.phone} onChange={e => setField('phone', e.target.value)} className={formFieldClass} placeholder="เบอร์โทรศัพท์" />
                <input value={form.address} onChange={e => setField('address', e.target.value)} className={formFieldClass} placeholder="ที่อยู่" />
              </div>
              <div className="mb-4 grid grid-cols-1 gap-4 md:grid-cols-4">
                <input type="number" value={form.age} onChange={e => setField('age', e.target.value)} className={formFieldClass} placeholder="อายุ" name="age" />
                <input value={form.ethnicity} onChange={e => setField('ethnicity', e.target.value)} className={formFieldClass} placeholder="เชื้อชาติ" name="ethnicity" />
                <input value={form.nationality} onChange={e => setField('nationality', e.target.value)} className={formFieldClass} placeholder="สัญชาติ" name="nationality" />
                <input value={form.religion} onChange={e => setField('religion', e.target.value)} className={formFieldClass} placeholder="ศาสนา" name="religion" />
              </div>
              <div className="mb-4">
                <input
                  type="email"
                  value={form.email}
                  onChange={e => setField('email', e.target.value)}
                  className={formFieldClass}
                  placeholder="อีเมล"
                />
              </div>
              <div className="mb-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                <div className={`flex items-center gap-3 ${panelFieldClass}`}>
                  <input type="color" value={form.theme_color} onChange={e => setField('theme_color', e.target.value)} className="h-6 w-6 cursor-pointer rounded border-0 p-0" />
                  <span className="text-sm font-semibold text-slate-800 dark:text-slate-800">{form.theme_color.toUpperCase()}</span>
                </div>
                <label className={`flex items-center gap-3 ${panelFieldClass}`}>
                  <input type="checkbox" checked={form.is_public} onChange={e => setField('is_public', e.target.checked)} className="h-4 w-4 rounded border-slate-400" />
                  <span className="text-sm font-semibold text-slate-800 dark:text-slate-800">เปิดเผยสู่สาธารณะ</span>
                </label>
              </div>
              <div className="mb-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                <textarea
                  value={form.special_abilities}
                  onChange={e => setField('special_abilities', e.target.value)}
                  rows={4}
                  className={`${formFieldClass} resize-none`}
                  placeholder={"ความสามารถพิเศษ (ขึ้นบรรทัดใหม่ได้)\nเช่น\n1) พิธีกร\n2) ร้องเพลง"}
                />
                <textarea
                  value={form.education_history}
                  onChange={e => setField('education_history', e.target.value)}
                  rows={4}
                  className={`${formFieldClass} resize-none`}
                  placeholder={"ประวัติการศึกษา (ขึ้นบรรทัดใหม่ได้)\nเช่น\n- ม.ต้น โรงเรียน...\n- ม.ปลาย โรงเรียน..."}
                />
              </div>
              <div className="mb-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                <input value={form.father_name} onChange={e => setField('father_name', e.target.value)} className={formFieldClass} placeholder="ชื่อบิดา" name="father_name" />
                <input value={form.father_phone} onChange={e => setField('father_phone', e.target.value)} className={formFieldClass} placeholder="เบอร์บิดา" name="father_phone" />
                <input value={form.mother_name} onChange={e => setField('mother_name', e.target.value)} className={formFieldClass} placeholder="ชื่อมารดา" name="mother_name" />
                <input value={form.mother_phone} onChange={e => setField('mother_phone', e.target.value)} className={formFieldClass} placeholder="เบอร์มารดา" name="mother_phone" />
              </div>
              <div className="mb-4">
                <textarea
                  value={form.family_history}
                  onChange={e => setField('family_history', e.target.value)}
                  rows={3}
                  className={`${formFieldClass} resize-none`}
                  placeholder="ประวัติครอบครัว"
                  name="family_history"
                />
              </div>
              <div className="mb-4">
                <textarea
                  value={form.awards_summary}
                  onChange={e => setField('awards_summary', e.target.value)}
                  rows={4}
                  className={`${formFieldClass} resize-none`}
                  placeholder={"ผลงานและรางวัล (ขึ้นบรรทัดใหม่ได้)\nเช่น\n- รางวัล...\n- เกียรติบัตร..."}
                />
              </div>
              <textarea value={form.description} onChange={e => setField('description', e.target.value)} rows={4} className={`${formFieldClass} resize-none`} placeholder="เขียนแนะนำตัวของคุณ..." />
            </div>

            <div className="grid grid-cols-2 gap-6">
              <div className="rounded-2xl border border-slate-100 bg-white/80 p-6 shadow-sm">
                <div className="mb-4 flex items-center justify-between">
                  <div className="flex items-center gap-2">
                    <div className="rounded-md bg-blue-100 p-1.5 text-blue-600"><Briefcase size={18} /></div>
                    <h2 className="text-lg font-bold text-slate-800 dark:text-slate-800">ผลงานเด่น</h2>
                  </div>
                  <div className="flex items-center gap-2">
                    <button
                      type="button"
                      onClick={() => workImageInputRef.current?.click()}
                      disabled={uploadingWorkImage}
                      className="inline-flex items-center gap-1 rounded-lg border border-slate-300 bg-white px-2 py-1.5 text-xs font-semibold text-black hover:bg-slate-50 disabled:text-black disabled:opacity-60"
                    >
                      <ImageIcon size={14} /> {uploadingWorkImage ? 'กำลังอัปโหลด...' : 'อัปโหลดรูปผลงาน'}
                    </button>
                    <button
                      onClick={() => setField('projects', [...form.projects, emptyProject()])}
                      title="เพิ่ม"
                      aria-label="เพิ่ม"
                      className="flex h-9 w-9 items-center justify-center rounded-full border-2 border-slate-600 bg-white text-slate-900 shadow-sm transition hover:bg-slate-100 active:scale-95"
                    >
                      <Plus size={20} strokeWidth={2.8} />
                    </button>
                  </div>
                </div>
                <input
                  ref={workImageInputRef}
                  type="file"
                  accept="image/*"
                  className="hidden"
                  onChange={e => {
                    const file = e.target.files?.[0];
                    if (file) {
                      void uploadPortfolioWorkImage(file);
                    }
                    e.currentTarget.value = '';
                  }}
                />
                {portfolioImages.length > 0 ? (
                  <div className="mb-3 grid grid-cols-2 gap-2">
                    {portfolioImages.map(image => {
                      return (
                        <div key={image.id} className="overflow-hidden rounded-lg border border-slate-200 bg-slate-50">
                          {image.image_path ? (
                            <SmartImage
                              sources={[resolvePortfolioFileUrl(image.image_path), image.image_path]}
                              fallbackBase={assetBaseURL || api.defaults.baseURL}
                              alt={image.image_name ?? 'Portfolio image'}
                              className="h-24 w-full object-cover"
                            />
                          ) : (
                            <div className="flex h-24 items-center justify-center text-xs text-slate-500">ไม่พบรูป</div>
                          )}
                          <div className="p-2">
                            <div className="mb-2 flex items-center justify-between gap-2">
                              <p className="truncate text-xs font-medium text-slate-700 dark:text-white">{image.image_name || 'รูปผลงาน'}</p>
                              <button
                                type="button"
                                onClick={() => void deletePortfolioImage(image.id)}
                                className="inline-flex items-center gap-1 text-xs font-semibold text-rose-600 hover:text-rose-700"
                              >
                                <Trash2 size={13} /> ลบ
                              </button>
                            </div>
                            <textarea
                              value={image.description ?? ''}
                              onChange={e =>
                                setPortfolioImages(prev =>
                                  prev.map(img => (img.id === image.id ? { ...img, description: e.target.value } : img)),
                                )
                              }
                              rows={2}
                              className="w-full rounded border border-slate-300 bg-white px-2 py-1.5 text-xs text-slate-800 placeholder:text-slate-500"
                              placeholder="คำอธิบายใต้รูป"
                            />
                            <button
                              type="button"
                              onClick={() => void updatePortfolioImageDescription(image.id, image.description ?? '')}
                              className="mt-2 inline-flex items-center rounded-md border border-slate-300 bg-white px-2 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                            >
                              บันทึกคำอธิบาย
                            </button>
                          </div>
                        </div>
                      );
                    })}
                  </div>
                ) : null}
                <div className="mb-4 rounded-xl border border-slate-200 bg-slate-50 p-3">
                  <p className="mb-2 text-sm font-bold text-slate-800 dark:text-white">ไฟล์พอร์ตที่บันทึกแล้ว</p>
                  {portfolioImages.length > 0 ? (
                    <div className="space-y-2">
                      {portfolioImages.map((image, index) => (
                        <div key={`saved-file-${image.id}`} className="flex items-center justify-between gap-3 rounded-lg border border-slate-200 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-900/70">
                          <div className="min-w-0">
                            <p className="truncate text-sm font-semibold text-slate-800 dark:text-white">
                              {index + 1}. {image.image_name || `ไฟล์ผลงาน #${image.id}`}
                            </p>
                            <p className="text-xs text-slate-500 dark:text-slate-200">{image.image_type}</p>
                          </div>
                          <a
                            href={resolvePortfolioFileUrl(image.image_path)}
                            target="_blank"
                            rel="noreferrer"
                            className="portfolio-theme-btn-text inline-flex shrink-0 items-center gap-1 rounded-md border border-slate-300 bg-slate-50 px-2.5 py-1.5 text-xs font-semibold hover:bg-slate-100 dark:border-slate-600 dark:bg-slate-800 dark:hover:bg-slate-700"
                          >
                            <Eye size={13} /> ดูไฟล์
                          </a>
                        </div>
                      ))}
                    </div>
                  ) : (
                    <p className="text-xs text-slate-500">ยังไม่มีไฟล์พอร์ตที่บันทึก</p>
                  )}
                </div>
                {form.projects.map((project, index) => (
                  <div key={project.id ?? index} className="group relative mb-3 rounded-xl border border-slate-300 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900/70">
                    <button onClick={() => setField('projects', form.projects.filter((_, i) => i !== index))} className="absolute right-4 top-4 text-xs font-bold text-red-600">ลบ</button>
                    <input value={project.project_name} onChange={e => updateProject(index, 'project_name', e.target.value)} className="mb-2 w-full rounded border border-slate-300 bg-white px-2 py-1.5 text-sm font-bold text-slate-900 placeholder:text-slate-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white dark:placeholder:text-slate-300" placeholder="ชื่อผลงาน" />
                    <div className="mb-2 grid grid-cols-2 gap-2">
                      <input value={project.project_type} onChange={e => updateProject(index, 'project_type', e.target.value)} className="rounded border border-slate-300 bg-white px-2 py-1.5 text-xs font-medium text-slate-800 placeholder:text-slate-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white dark:placeholder:text-slate-300" placeholder="ประเภท" />
                      <input value={project.technologies} onChange={e => updateProject(index, 'technologies', e.target.value)} className="rounded border border-slate-300 bg-white px-2 py-1.5 text-xs font-medium text-slate-800 placeholder:text-slate-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white dark:placeholder:text-slate-300" placeholder="เทคโนโลยี" />
                    </div>
                    <div className="mb-2 flex gap-2">
                      <a href={project.project_url || '#'} target="_blank" rel="noreferrer" className="portfolio-theme-btn-text flex items-center gap-1.5 rounded-md border border-slate-300 bg-slate-50 px-3 py-1.5 text-xs font-bold dark:border-slate-600 dark:bg-slate-800"><Globe size={14} /> ดูผลงาน</a>
                      <a href={project.github_url || '#'} target="_blank" rel="noreferrer" className="portfolio-theme-btn-text flex items-center gap-1.5 rounded-md border border-slate-300 bg-slate-50 px-3 py-1.5 text-xs font-bold dark:border-slate-600 dark:bg-slate-800"><GitBranch size={14} /> GitHub</a>
                    </div>
                    <div className="mb-2 grid gap-2">
                      <input value={project.project_url} onChange={e => updateProject(index, 'project_url', e.target.value)} className="rounded border border-slate-300 bg-white px-2 py-1.5 text-xs text-slate-800 placeholder:text-slate-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white dark:placeholder:text-slate-300" placeholder="ลิงก์ผลงาน (URL)" />
                      <input value={project.github_url} onChange={e => updateProject(index, 'github_url', e.target.value)} className="rounded border border-slate-300 bg-white px-2 py-1.5 text-xs text-slate-800 placeholder:text-slate-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white dark:placeholder:text-slate-300" placeholder="ลิงก์ GitHub (URL)" />
                    </div>
                    <details>
                      <summary className="flex cursor-pointer items-center gap-1 text-xs font-semibold text-slate-700 dark:text-white"><ChevronRight size={14} /> แก้ไขผลงานนี้</summary>
                      <div className="mt-2 grid gap-2">
                        <textarea value={project.project_description} onChange={e => updateProject(index, 'project_description', e.target.value)} rows={2} className="rounded border border-slate-300 bg-white px-2 py-1.5 text-xs text-slate-800 placeholder:text-slate-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white dark:placeholder:text-slate-300" placeholder="รายละเอียดผลงาน" />
                      </div>
                    </details>
                  </div>
                ))}
              </div>

              <div className="rounded-2xl border border-slate-100 bg-white/80 p-6 shadow-sm">
                <div className="mb-4 flex items-center justify-between">
                  <div className="flex items-center gap-2">
                    <div className="rounded-md bg-violet-100 p-1.5 text-violet-600"><Target size={18} /></div>
                    <h2 className="text-lg font-bold text-slate-800 dark:text-slate-800">ความสนใจ</h2>
                  </div>
                  <button
                    onClick={() => setField('interests', [...form.interests, emptyInterest()])}
                    title="เพิ่ม"
                    aria-label="เพิ่ม"
                    className="flex h-9 w-9 items-center justify-center rounded-full border-2 border-slate-600 bg-white text-slate-900 shadow-sm transition hover:bg-slate-100 active:scale-95"
                  >
                    <Plus size={20} strokeWidth={2.8} />
                  </button>
                </div>
                {form.interests.length === 0 ? (
                  <div className="mb-6 flex items-center justify-center rounded-xl border-2 border-dashed border-slate-300 bg-white p-6">
                    <span className="text-sm font-semibold text-slate-600">ยังไม่ได้ระบุความสนใจ</span>
                  </div>
                ) : (
                  <div className="mb-6 space-y-2">
                    {form.interests.map((interest, index) => (
                      <div key={interest.id ?? index} className="grid grid-cols-[1fr_auto] gap-2 rounded-lg border border-slate-300 bg-white p-2 shadow-sm">
                        <input
                          value={interest.interest_name}
                          onChange={e => updateInterest(index, e.target.value)}
                          className="rounded border border-slate-300 bg-white px-2 py-1.5 text-sm font-medium text-slate-900 placeholder:text-slate-600 outline-none focus:border-orange-400 focus:ring-2 focus:ring-orange-100"
                          placeholder="เช่น AI, Data Science, UX/UI"
                        />
                        <button onClick={() => setField('interests', form.interests.filter((_, i) => i !== index))} className="px-2 text-xs font-bold text-red-600">ลบ</button>
                      </div>
                    ))}
                  </div>
                )}

                <div className="mb-4 flex items-center justify-between">
                  <div className="flex items-center gap-2">
                    <div className="rounded-md bg-emerald-100 p-1.5 text-emerald-600"><Target size={18} /></div>
                    <h2 className="text-lg font-bold text-slate-800 dark:text-slate-800">ทักษะความสามารถ</h2>
                  </div>
                  <button
                    onClick={() => setField('skills', [...form.skills, emptySkill()])}
                    title="เพิ่ม"
                    aria-label="เพิ่ม"
                    className="flex h-9 w-9 items-center justify-center rounded-full border-2 border-slate-600 bg-white text-slate-900 shadow-sm transition hover:bg-slate-100 active:scale-95"
                  >
                    <Plus size={20} strokeWidth={2.8} />
                  </button>
                </div>
                {form.skills.length === 0 ? (
                  <div className="flex items-center justify-center rounded-xl border-2 border-dashed border-slate-300 bg-white p-8">
                    <span className="text-sm font-semibold text-slate-600">ยังไม่ได้ระบุทักษะ</span>
                  </div>
                ) : (
                  <div className="space-y-2">
                    {form.skills.map((skill, index) => (
                      <div key={skill.id ?? index} className="grid grid-cols-[1fr_130px_auto] gap-2 rounded-lg border border-slate-300 bg-white p-2 shadow-sm">
                        <input value={skill.skill_name} onChange={e => updateSkill(index, 'skill_name', e.target.value)} className="rounded border border-slate-300 bg-white px-2 py-1.5 text-sm font-medium text-slate-900 placeholder:text-slate-600 outline-none focus:border-orange-400 focus:ring-2 focus:ring-orange-100" placeholder="ชื่อทักษะ" />
                        <input value={skill.skill_level} onChange={e => updateSkill(index, 'skill_level', e.target.value)} className="rounded border border-slate-300 bg-white px-2 py-1.5 text-sm font-medium text-slate-900 placeholder:text-slate-600 outline-none focus:border-orange-400 focus:ring-2 focus:ring-orange-100" placeholder="ระดับ" />
                        <button onClick={() => setField('skills', form.skills.filter((_, i) => i !== index))} className="px-2 text-xs font-bold text-red-600">ลบ</button>
                      </div>
                    ))}
                  </div>
                )}
              </div>
            </div>

          </div>
        </div>
      ) : (
        <div className="print-portfolio-shell mx-auto max-w-5xl animate-in fade-in zoom-in-95 pb-8 duration-300">
          <div className="no-print mb-6 flex flex-wrap items-center gap-3">
            <button onClick={() => setViewMode('edit')} className="flex items-center gap-2 rounded-full border border-slate-200 bg-white px-4 py-2 font-medium text-slate-500 shadow-sm hover:text-slate-800">
              <ArrowLeft size={18} /> กลับไปหน้าแก้ไข
            </button>
            <button onClick={() => void handleSharePortfolio()} className="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-4 py-2 font-medium text-slate-600 shadow-sm hover:text-slate-900">
              <Share2 size={16} /> แชร์
            </button>
            <button onClick={handleSavePdf} className="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-4 py-2 font-medium text-slate-600 shadow-sm hover:text-slate-900">
              <Download size={16} /> บันทึก PDF
            </button>
          </div>
          <style dangerouslySetInnerHTML={{ __html: `
            @media print {
              @page {
                size: A4;
                margin: 8mm;
              }
              html, body {
                background: #ffffff !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
              }
              body * {
                visibility: hidden !important;
              }
              .print-portfolio-card,
              .print-portfolio-card * {
                visibility: visible !important;
              }
              .print-portfolio-card {
                position: absolute !important;
                left: 0 !important;
                top: 0 !important;
                width: 100% !important;
                max-width: none !important;
                margin: 0 !important;
                border-radius: 0 !important;
                border: 0 !important;
                box-shadow: none !important;
              }
              .print-portfolio-shell {
                padding: 0 !important;
                margin: 0 !important;
                max-width: none !important;
                background: transparent !important;
              }
              .no-print {
                display: none !important;
                visibility: hidden !important;
              }
            }
          ` }} />
          <div ref={portfolioPrintRef} className="print-portfolio-card overflow-hidden rounded-[18px] border border-slate-300 bg-[#ededed] shadow-sm">
            <div className="grid grid-cols-1 md:grid-cols-[300px_1fr]">
              <aside className="bg-[#c86112] p-6 text-white">
                <h2 className="text-4xl font-black tracking-[0.22em]">PERSONAL</h2>
                <h3 className="text-4xl font-black tracking-[0.22em]">DETAILS</h3>
                <p className="mt-1 text-sm">ข้อมูลส่วนตัว</p>
                <div className="mt-5 overflow-hidden border-4 border-[#a84f0f] bg-slate-200">
                  {profileImageSources.some(item => String(item ?? '').trim() !== '') ? (
                    <SmartImage
                      sources={profileImageSources}
                      fallbackBase={assetBaseURL || api.defaults.baseURL}
                      alt="Profile"
                      className="h-[320px] w-full object-cover"
                    />
                  ) : (
                    <div className="flex h-[320px] items-center justify-center text-6xl font-bold text-slate-500">C</div>
                  )}
                </div>
                <div className="mt-6 bg-white px-3 py-2 text-center text-lg font-bold text-[#5f320d]">ช่องทางการติดต่อ</div>
                <div className="mt-4 space-y-2 text-sm">
                  <p><span className="font-bold">ชื่อ:</span> {form.full_name || '-'}</p>
                  <p><span className="font-bold">ชื่อเล่น:</span> {form.nickname || '-'}</p>
                  <p><span className="font-bold">ที่อยู่:</span> {form.address || '-'}</p>
                  <p><span className="font-bold">โทร:</span> {form.phone || '-'}</p>
                  <p><span className="font-bold">อีเมล:</span> {form.email || '-'}</p>
                </div>
              </aside>

              <main className="space-y-7 p-6 md:p-8">
                <section>
                  <div className="mb-4 flex items-center gap-3">
                    <h3 className="text-4xl font-black tracking-[0.12em] text-black">PERSONAL DETAILS</h3>
                    <div className="h-4 w-24 bg-black" />
                  </div>
                  <div className="border-t-4 border-black pt-4 text-[15px] leading-8 text-slate-800">
                    <p className="text-3xl font-bold text-black">ประวัติส่วนตัว</p>
                    <p>ชื่อ : {form.full_name || user?.name || form.title || '-'}</p>
                    <p>ชื่อเล่น : {form.nickname || '-'}</p>
                    <p>วันเกิด : {form.date_of_birth || '-'}</p>
                    <p>อายุ : {form.age || '-'}</p>
                    <p>เชื้อชาติ : {form.ethnicity || '-'}</p>
                    <p>สัญชาติ : {form.nationality || '-'}</p>
                    <p>ศาสนา : {form.religion || '-'}</p>
                  </div>
                </section>

                <section>
                  <p className="text-3xl font-bold text-black">ความสามารถพิเศษ</p>
                  <p className="whitespace-pre-line text-[15px] leading-8 text-slate-800">{form.special_abilities || '-'}</p>
                </section>

                <section>
                  <p className="text-3xl font-bold text-black">ประวัติครอบครัว</p>
                  <div className="mt-2 text-[15px] leading-8 text-slate-800">
                    <p>บิดา : {form.father_name || '-'}</p>
                    <p>เบอร์ : {form.father_phone || '-'}</p>
                    <p>มารดา : {form.mother_name || '-'}</p>
                    <p>เบอร์ : {form.mother_phone || '-'}</p>
                  </div>
                  <p className="mt-2 whitespace-pre-line text-[15px] leading-8 text-slate-800">{form.family_history || '-'}</p>
                </section>

                <section>
                  <div className="mb-3 flex items-center gap-3">
                    <div className="h-5 w-14 bg-[#c86112]" />
                    <h3 className="text-5xl font-black text-black">EDUCATION</h3>
                    <div className="h-5 w-14 bg-[#c86112]" />
                  </div>
                  <p className="text-3xl font-bold text-black">ประวัติการศึกษา</p>
                  <p className="whitespace-pre-line text-[15px] leading-8 text-slate-800">{form.education_history || '-'}</p>
                </section>

                <section>
                  <div className="mb-3 flex items-center justify-between bg-[#c86112] px-4 py-2">
                    <h3 className="text-3xl font-bold text-white">ผลงานและรางวัล</h3>
                  </div>
                  <p className="whitespace-pre-line text-[15px] leading-8 text-slate-800">{form.awards_summary || '-'}</p>
                  {portfolioImages.length > 0 ? (
                    <div className="mt-3 grid grid-cols-3 gap-2">
                      {portfolioImages.slice(0, 6).map(image => {
                        return image.image_path ? (
                          <div key={image.id} className="border border-slate-300 bg-white">
                            <SmartImage
                              sources={[resolvePortfolioFileUrl(image.image_path), image.image_path]}
                              fallbackBase={assetBaseURL || api.defaults.baseURL}
                              alt={image.image_name ?? 'Portfolio image'}
                              className="h-28 w-full object-cover"
                            />
                            <p className="px-2 py-1 text-xs text-slate-700">{(image.description ?? '').trim() || (image.image_name ?? '-')}</p>
                          </div>
                        ) : null;
                      })}
                    </div>
                  ) : null}
                  {form.projects.length > 0 ? (
                    <div className="mt-4 space-y-2">
                      {form.projects.map((project, index) => (
                        <div key={project.id ?? index} className="border border-slate-300 bg-white p-3">
                          <p className="text-lg font-bold text-black">{index + 1}. {project.project_name || '-'}</p>
                          <p className="text-sm text-slate-700">{project.project_type || '-'} | {project.technologies || '-'}</p>
                          <p className="mt-1 whitespace-pre-line text-sm text-slate-700">{project.project_description || '-'}</p>
                          <div className="mt-2 flex gap-4">
                            <a href={project.project_url || '#'} target="_blank" rel="noreferrer" className="inline-flex items-center gap-1 text-sm font-semibold text-blue-600 hover:underline"><Globe size={14} /> ผลงาน</a>
                            <a href={project.github_url || '#'} target="_blank" rel="noreferrer" className="inline-flex items-center gap-1 text-sm font-semibold text-slate-800 hover:underline"><GitBranch size={14} /> GitHub</a>
                          </div>
                        </div>
                      ))}
                    </div>
                  ) : null}
                </section>
              </main>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};
