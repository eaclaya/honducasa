<?php

use App\Models\User;
use Illuminate\Support\Facades\Password;
use Laravel\Fortify\Features;

test('login failure message is in spanish by default', function () {
    $user = User::factory()->create();

    $response = $this->from(route('login'))->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $response->assertInvalid([
        'email' => 'Estas credenciales no coinciden con nuestros registros.',
    ]);
});

test('registration validation errors are in spanish by default', function () {
    $response = $this->from(route('register'))->post(route('register.store'), [
        'name' => '',
        'email' => 'not-an-email',
        'password' => 'password',
        'password_confirmation' => 'different',
    ]);

    $response->assertInvalid([
        'name' => 'El campo nombre es obligatorio.',
        'email' => 'El campo correo electrónico debe ser un correo electrónico válido.',
        'password' => 'La confirmación del campo contraseña no coincide.',
    ]);
});

test('forgot password errors and status are in spanish by default', function () {
    $this->from(route('password.request'))
        ->post(route('password.email'), ['email' => 'unknown@example.com'])
        ->assertInvalid([
            'email' => 'No encontramos ningún usuario con ese correo electrónico.',
        ]);

    $user = User::factory()->create();

    $this->from(route('password.request'))
        ->post(route('password.email'), ['email' => $user->email])
        ->assertSessionHas('status', 'Te hemos enviado por correo el enlace para restablecer tu contraseña.');
});

test('reset password with an invalid token fails in spanish by default', function () {
    $user = User::factory()->create();

    $response = $this->post(route('password.update'), [
        'token' => 'invalid-token',
        'email' => $user->email,
        'password' => 'new-Password1!',
        'password_confirmation' => 'new-Password1!',
    ]);

    $response->assertInvalid([
        'email' => 'El token de restablecimiento de contraseña no es válido.',
    ]);
});

test('reset password succeeds with a spanish status message', function () {
    $user = User::factory()->create();
    $token = Password::createToken($user);

    $this->post(route('password.update'), [
        'token' => $token,
        'email' => $user->email,
        'password' => 'new-Password1!',
        'password_confirmation' => 'new-Password1!',
    ])->assertSessionHas('status', 'Tu contraseña ha sido restablecida.');
});

test('password confirmation failure is in spanish by default', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->from(route('password.confirm'))
        ->post(route('password.confirm.store'), [
            'password' => 'wrong-password',
        ]);

    $response->assertInvalid([
        'password' => 'La contraseña proporcionada es incorrecta.',
    ]);
});

test('two factor challenge failure is in spanish by default', function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

    $user = User::factory()->withTwoFactor()->create();

    $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response = $this->from(route('two-factor.login'))
        ->post(route('two-factor.login.store'), ['recovery_code' => 'invalid-code']);

    $response->assertInvalid([
        'recovery_code' => 'El código de recuperación de dos factores proporcionado no es válido.',
    ]);
});

test('auth errors fall back to english when the visitor selects english', function () {
    $user = User::factory()->create();

    $response = $this->withSession(['locale' => 'en'])
        ->from(route('login'))
        ->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

    $response->assertInvalid([
        'email' => 'These credentials do not match our records.',
    ]);
});
