<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class MakeAdmin extends Command
{
    protected $signature = 'app:make-admin {email} {--password=} {--name=}';

    protected $description = 'Jadikan user (dibuat jika belum ada) sebagai admin global';

    public function handle(): int
    {
        $email = $this->argument('email');

        $validator = Validator::make(['email' => $email], ['email' => ['required', 'email']]);
        if ($validator->fails()) {
            $this->error("Email tidak valid: {$email}");
            return self::FAILURE;
        }

        $user = User::firstOrNew(['email' => $email]);
        $isNew = !$user->exists;

        $password = $this->option('password') ?: ($isNew ? $this->secret('Password untuk akun baru') : null);

        if ($isNew) {
            if (!$password) {
                $this->error('Password wajib diisi untuk akun baru.');
                return self::FAILURE;
            }
            $user->name = $this->option('name') ?: 'Admin';
            $user->email_verified_at = now();
            $user->active = true;
        }

        if ($password) {
            $user->password = Hash::make($password);
        }

        $user->is_admin = true;
        $user->save();

        $this->info(($isNew ? 'Akun admin baru dibuat: ' : 'User dijadikan admin: ') . $user->email);
        return self::SUCCESS;
    }
}
