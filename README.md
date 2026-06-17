<<<<<<< HEAD
# Smart Learning Tracker

Web application for tracking self-learning progress with AI-assisted summaries, quiz generation, and analytics. The project ships with a Laravel API backend and a React + Tailwind frontend.

## Features

- Email/password authentication (Laravel Sanctum) with optional Google sign-in hook.
- User profiles: name, avatar, education level.
- Subject management and daily study logs with file attachments (PDF, Word, audio).
- AI assistant:
  - Speech-to-text transcription for audio files (OpenAI Whisper).
  - Document text extraction for PDF/Word uploads.
  - Automated study summaries per log.
  - Quiz generation and grading with GPT-4o / Gemini.
- Quiz library with attempt tracking and scoring.
- Dashboard analytics: counts, study time trends, quiz performance trends.
- Calendar/list views of study activity.

## Project Structure

```
smart-learning-tracker/
├── app/               # Laravel application code (models, controllers, services)
├── bootstrap/
├── config/
├── database/          # Migrations & seeders for core entities
├── frontend/          # React + Vite + Tailwind web client
├── public/
├── resources/
├── routes/
├── storage/
└── docs/              # Architecture notes
```

## Backend Setup (Laravel API)

1. Install dependencies (requires internet access):
   ```bash
   composer install
   ```
2. Copy environment file and set values:
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```
3. Configure database credentials (`DB_*`) for MySQL (or change to PostgreSQL) and create the database `smart_learning_tracker`.
4. Run migrations:
   ```bash
   php artisan migrate
   ```
5. Link public storage for user uploads:
   ```bash
   php artisan storage:link
   ```
6. (Optional) Run the queue worker for async jobs if you later offload AI tasks:
   ```bash
   php artisan queue:work
   ```
7. Start the API server:
   ```bash
   php artisan serve --host=0.0.0.0 --port=8000
   ```

### AI Provider Configuration

- **OpenAI**: set `AI_PROVIDER=openai`, `OPENAI_API_KEY`, `SUMMARIZATION_MODEL`, `QUIZ_MODEL`, `WHISPER_MODEL`.
- **Google Cloud (optional)**: provide `GOOGLE_PROJECT_ID` and `GOOGLE_APPLICATION_CREDENTIALS` (JSON string or file path) if you prefer Google transcription/document services.

## Frontend Setup (React + Vite)

1. Install dependencies:
   ```bash
   cd frontend
   npm install
   ```
2. Create `frontend/.env` (or use `.env.local`) and set the backend base URL if it differs from the Vite proxy target:
   ```
   VITE_API_URL=http://localhost:8000
   ```
   This is used when generating public links to uploaded files.
3. Start the development server:
   ```bash
   npm run dev
   ```
4. Access the app at http://localhost:5173 (proxy routes `/api` to the Laravel backend running on port 8000).

## Testing

- Backend: `php artisan test`
- Frontend: Add tests with your preferred framework (e.g., Vitest/React Testing Library).

## Deployment Notes

- Use a production web server (Nginx/Apache) pointing to `public/index.php`.
- Configure Laravel queue workers for intensive AI operations if you move them off the request cycle.
- Ensure file storage (e.g., S3) is configured for production via `FILESYSTEM_DISK`.
- Protect AI API keys via environment variables or secret stores.

## Next Steps & Enhancements

- Implement background jobs for large file processing and quiz generation.
- Add fine-grained quiz analytics (per question difficulty, history charts).
- Support collaborative subjects (shared between users).
- Add notifications/reminders using Laravel notifications + frontend toast system.
- Expand test coverage (feature tests for API, component/unit tests for React UI).
=======
# smart-learning-tracker
ระบบจัดการการเรียนรู้และติดตามความก้าวหน้าการเรียนด้วย AI
>>>>>>> bfe51c4aae83c0eda72665b3792a78bd1c4ce489
