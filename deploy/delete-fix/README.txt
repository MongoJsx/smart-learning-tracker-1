Upload these files to your host (same paths), overwrite existing:
- routes/api.php
- app/Http/Controllers/API/SubjectController.php
- bootstrap/app.php (for require.bearer alias)
Then clear cache (delete bootstrap/cache/routes-*.php and config.php).
