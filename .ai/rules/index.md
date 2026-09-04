# Project Rules Index

Before planning or editing, find the row whose globs match the file's path and read that rule file.

| Applies to | Rule file |
| --- | --- |
| app/Http/Controllers/Admin/** | .ai/rules/admin.md |
| {app/Http/{Responses/**,Controllers/Auth/**},app/Actions/Auth/ResolveGoogleUser.php,database/seeders/{SuperAdminSeeder.php,DemoPropertySeeder.php},app/Providers/FortifyServiceProvider.php} | .ai/rules/auth.md |
| app/Http/Controllers/** | .ai/rules/controllers.md |
| {app/Services/ListingPhotoCompressor.php,app/Http/Controllers/ListingUploadController.php,app/Jobs/EnhanceListingPhoto.php} | .ai/rules/jobs.md |
| {resources/js/app.ts,resources/js/pages/**} | .ai/rules/frontend.md |
| resources/js/pages/listings/** | .ai/rules/listings.md |
| {app/Notifications/**,app/Models/User.php,lang/es.json} | .ai/rules/models.md |
| app/Http/Requests/Admin/*SubscriptionPlan*.php | .ai/rules/requests-admin.md |
| app/Http/Requests/SaveListingRequest.php | .ai/rules/requests.md |
| {tests/**,phpunit.xml,app/Services/OpenAiContentModerator.php} | .ai/rules/services.md |
| app/{Actions/Teams/SubscribeToPlan.php,Http/Controllers/Teams/TeamBillingController.php} | .ai/rules/teams.md |
