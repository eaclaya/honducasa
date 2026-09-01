---
paths:
  - '{app/Notifications/**,app/Models/User.php,lang/es.json}'
---

# Models

## Mail/notifications are forced to Spanish via User::preferredLocale()
`User` implements `Illuminate\Contracts\Translation\HasLocalePreference::preferredLocale()`, hardcoded to return `'es'`. This is what actually guarantees email content is Spanish — there's no per-user locale column (the UI's locale switcher only persists to the session, which a queued job/console command never has), so without this every queued notification would render in whatever `config('app.locale')` happens to resolve to in that process. Laravel's `ChannelManager` reads this automatically on every `$user->notify(...)`/`Mail::to($user)` call and wraps rendering in `App::setLocale('es')`.

This ONLY fixes which locale is active — it does not translate anything by itself. `__()`/`Lang::get()`/`@lang()` calls with no matching `lang/es.json` entry just return the literal English key. Laravel's own built-in notifications (`Illuminate\Auth\Notifications\VerifyEmail`, `ResetPassword`) and the mail markdown chrome ("Hello!", "Regards,", "All rights reserved.", the "trouble clicking the button" line) all route through this same `lang/es.json` file — when adding a new email (custom or by enabling another Fortify feature), check whether its exact `Lang::get()`/`__()` strings already have an es.json entry, and add one if not, or it silently ships in English despite the locale being correctly 'es'. Verify by actually sending it through the local Mailpit container (see compose.yaml) rather than assuming — `Notification::fake()` bypasses the `HasLocalePreference` switch entirely, and `Mail::fake()` doesn't capture notification-based `MailMessage` sends, so neither testing fake proves the real content renders correctly.
