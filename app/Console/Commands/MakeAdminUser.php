<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

#[Signature('app:make-admin-user {email} {--name=} {--password=}')]
#[Description('Create a new admin user, or grant admin access to an existing account')]
class MakeAdminUser extends Command
{
    public function handle(): int
    {
        $email = $this->argument('email');

        $validator = Validator::make(['email' => $email], ['email' => ['required', 'email']]);

        if ($validator->fails()) {
            $this->components->error($validator->errors()->first('email'));

            return self::FAILURE;
        }

        $user = User::query()->where('email', $email)->first();

        if ($user) {
            $user->update(['is_admin' => true]);
            $this->components->info("Granted admin access to existing user {$user->email}.");

            return self::SUCCESS;
        }

        $name = $this->option('name') ?: $this->ask('Name for the new admin user');
        $password = $this->option('password') ?: Str::password();

        $user = User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'is_admin' => true,
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();

        $this->components->info("Created admin user {$user->email}.");

        if (! $this->option('password')) {
            $this->components->twoColumnDetail('Password', $password);
        }

        return self::SUCCESS;
    }
}
