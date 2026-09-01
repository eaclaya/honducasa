<?php

test('Google OAuth callback uses the canonical application URL', function () {
    expect(config('services.google.redirect'))->toBe(
        rtrim((string) config('app.url'), '/').'/auth/google/callback',
    );
});
