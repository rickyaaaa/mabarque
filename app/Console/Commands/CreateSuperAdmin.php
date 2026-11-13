<?php

namespace App\Console\Commands;

use App\Services\BetaAccessService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

/**
 * php artisan mabarqueue:create-admin admin@domain.com --name="Admin MabarQueue"
 *
 * Password diketik tersembunyi (tidak masuk riwayat shell). Dengan --generate,
 * password acak dibuat dan ditampilkan SEKALI.
 */
class CreateSuperAdmin extends Command
{
    protected $signature = 'mabarqueue:create-admin
        {email : Email login Super Admin}
        {--name=Admin MabarQueue : Nama tampilan}
        {--generate : Buat password acak alih-alih mengetik}';

    protected $description = 'Buat akun Super Admin (atau naikkan akun yang sudah ada menjadi Super Admin)';

    public function handle(BetaAccessService $service): int
    {
        $email = strtolower(trim((string) $this->argument('email')));

        if (Validator::make(['email' => $email], ['email' => ['required', 'email']])->fails()) {
            $this->error('Email tidak valid.');

            return self::INVALID;
        }

        if ($this->option('generate')) {
            $password = Str::password(16, symbols: false);
        } else {
            $password = (string) $this->secret('Password Super Admin (min 12 karakter, huruf + angka)');
            $confirm = (string) $this->secret('Ulangi password');

            if ($password !== $confirm) {
                $this->error('Konfirmasi password tidak sama.');

                return self::INVALID;
            }
        }

        $validator = Validator::make(['password' => $password], [
            'password' => ['required', Password::min(12)->letters()->numbers()],
        ]);

        if ($validator->fails()) {
            $this->error($validator->errors()->first('password'));

            return self::INVALID;
        }

        $user = $service->createSuperAdmin($email, (string) $this->option('name'), $password);

        $this->info("Super Admin siap: {$user->email} (id {$user->id}).");

        if ($this->option('generate')) {
            $this->warn('Password (hanya ditampilkan sekali, simpan di password manager): '.$password);
        }

        return self::SUCCESS;
    }
}
