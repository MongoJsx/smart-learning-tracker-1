# Smart Learning Tracker – Architecture Notes

## Domain Overview

| Entity | Purpose | Key Relations |
| --- | --- | --- |
| `users` | Authenticated learners with profile metadata. | `hasMany subjects`, `hasMany quiz_answers`. |
| `subjects` | User-defined subjects/modules. | `belongsTo users`, `hasMany study_logs`, `hasMany quizzes`. |
| `study_logs` | Daily study entries with notes, mood, duration. | `belongsTo subjects`, `hasMany files`, `hasMany summaries`. |
| `files` | Attachments uploaded per study log (PDF/Word/Audio). | `belongsTo study_logs`. |
| `summaries` | AI-generated summaries for a study log. | `belongsTo study_logs`. |
| `quizzes` | AI-generated quiz bundles per subject. | `belongsTo subjects`, `hasMany quiz_questions`. |
| `quiz_questions` | Question items (MCQ/True-False/Short answer). | `belongsTo quizzes`, `hasMany quiz_answers`. |
| `quiz_answers` | Attempts per user/question with scoring. | `belongsTo quiz_questions`, `belongsTo users`. |

### ER Relationship Synopsis

```
users 1 ───< subjects 1 ───< study_logs 1 ───< files
                                 └──────< summaries
subjects 1 ───< quizzes 1 ───< quiz_questions 1 ───< quiz_answers
                                      └────── users
```

## Backend Highlights

- **Laravel 11 API** with Sanctum authentication (token-based for SPA).
- Modular controllers per bounded context (auth, subjects, study logs, quizzes, AI assistant, dashboard).
- Request validation via `FormRequest` classes to keep controllers lean and reusable.
- API Resources transform domain models into JSON payloads and hide internals.
- `AIService` centralises calls to OpenAI/Gemini/Whisper:
  - Summaries generated via chat completion.
  - Quiz creation returns a structured DTO before persistence.
  - Quiz grading handled locally for deterministic scoring.
  - Audio/doc analysis endpoints wrap calls to respective AI APIs.
- Dedicated migrations create all entities with FK constraints, cascade deletes, and JSON metadata for AI responses.
- Dashboard endpoints aggregate data via SQL (time-series for study minutes & quiz scores).

## Frontend Highlights

- **React + Vite + Tailwind** UX with dark, study-friendly theme.
- Global auth context stores JWT-like token and user profile, persisted in `localStorage`.
- Router guards (`RequireAuth`) protect private routes, redirect unauthenticated users to `/auth/login`.
- Key screens:
  - **Dashboard Overview** – summary cards + trend charts (Recharts).
  - **Subjects** – CRUD + quick navigation to study logs.
  - **Study Log Workspace** – log creation, file uploads, AI summary generation inline.
  - **Quiz Library & Attempt** – generate via AI, perform quizzes, see results immediately.
  - **Calendar View** – left-panel date selector with per-day log list.
  - **AI Assistant** – standalone page for audio/document analysis.
- Axios service centralises API calls with automatic token headers & 401 handling.
- Tailwind utility classes for rapid styling, with reusable `card`/`btn` patterns.

## AI Flow

1. **Study Log Summary**
   - User logs notes → hits `POST /study-logs/{id}/summaries` → `AIService::generateSummary` prompts GPT for bullet summary and metadata.
   - Result stored in `summaries` table for future retrieval.

2. **Quiz Generation**
   - `POST /subjects/{id}/quizzes` sends preferences (difficulty, count, types) → AI returns JSON spec → persisted as `quizzes` + `quiz_questions`.

3. **Quiz Attempt**
   - SPA collects answers → `POST /quizzes/{id}/attempts` → `AIService::gradeQuiz` compares locally and stores `quiz_answers` with timestamp.

4. **Speech-to-Text & Document Extraction**
   - Files uploaded via `/ai/analyze/audio` or `/ai/analyze/document` → AIService forwards to Whisper / GPT multi-modal endpoints → text returned to UI for further usage (e.g., copy into study notes).

## Deployment Considerations

- Use MySQL/PostgreSQL with timezone-aware timestamps; ensure `queue:work` if offloading AI tasks.
- Asset hosting: configure S3 or equivalent for `FILESYSTEM_DISK` when not using local storage.
- Provide API rate limiting (Laravel throttle middleware already included for `/api`).
- Add background jobs + retries for AI calls to avoid request timeouts on large payloads.
- Observability: add request/AI call logging via Laravel logging channels and a frontend analytics provider if needed.

## Suggested Enhancements

- Add API versioning (`/api/v1`) for long-term evolution.
- Integrate websockets (Laravel Echo) for real-time quiz scores or collaborative study sessions.
- Implement reminders (cron) based on study gaps derived from `study_logs`.
- Expand AI prompts to accept uploaded transcripts/doc text to improve summary relevance.
