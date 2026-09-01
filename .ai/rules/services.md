---
paths:
  - '{tests/**,phpunit.xml,app/Services/OpenAiContentModerator.php}'
---

# Services

## OPENAI_MODERATION_ENABLED is pinned off in phpunit.xml, not .env.testing
This project has no `.env.testing` file — test runs fall back to `.env`, so anything that shouldn't be live during tests gets pinned in `phpunit.xml`'s `<php>` block (see `MAIL_MAILER=array`, `QUEUE_CONNECTION=sync`). `OPENAI_MODERATION_ENABLED` is pinned `false` there for the same reason: `.env` has it `true` with a real-looking key for local dev, and `OpenAiContentModerator::isEnabled()` short-circuits before any HTTP call when it's false. Without the pin, any test that saves/edits a listing without explicitly mocking `Http::fake()` for `api.openai.com/v1/moderations` makes a real unmocked call that fails closed ("Content moderation is temporarily unavailable") — this caused 9 failures + 1 error across `ListingManagementTest.php` before the pin was added. A test that actually wants to exercise moderation opts in per-test with `config()->set(['services.openai.moderation_enabled' => true, ...])` + `Http::fake([...])`, same as the existing profanity/OpenAI tests already do — that pattern still works fine against the phpunit.xml default of false.
