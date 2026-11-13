<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\BetaAccessApproved;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Pengelolaan akses private alpha / closed beta oleh Super Admin:
 * menyetujui (active), menunda (pending), atau menonaktifkan (inactive) akun streamer,
 * serta membuat akun Super Admin.
 */
class BetaAccessService
{
    public const STATUSES = [User::BETA_PENDING, User::BETA_ACTIVE, User::BETA_INACTIVE];

    /**
     * Ubah status beta streamer. Saat diaktifkan, streamer dikabari lewat email.
     * Saat dinonaktifkan, semua sesi login-nya dicabut supaya langsung keluar.
     *
     * @return bool true bila status benar-benar berubah
     */
    public function setStatus(User $user, string $status): bool
    {
        if (! in_array($status, self::STATUSES, true)) {
            throw new InvalidArgumentException('Status beta harus pending, active, atau inactive.');
        }

        if ($user->isSuperAdmin()) {
            throw new InvalidArgumentException('Status beta Super Admin tidak bisa diubah.');
        }

        if ($user->beta_status === $status) {
            return false;
        }

        $user->beta_status = $status;

        if ($status === User::BETA_INACTIVE) {
            $user->remember_token = Str::random(60); // cabut cookie "ingat saya"
        }

        $user->save();

        if ($status === User::BETA_INACTIVE) {
            $this->revokeSessions($user);
        }

        if ($status === User::BETA_ACTIVE) {
            $user->notify(new BetaAccessApproved());
        }

        return true;
    }

    /** Daftar streamer (terbaru dulu), opsional difilter status beta dan dicari nama/email/slug. */
    public function listStreamers(?string $status = null, ?string $search = null, int $perPage = 25): LengthAwarePaginator
    {
        return User::query()
            ->streamers()
            ->when($status, fn ($query) => $query->where('beta_status', $status))
            ->when($search, function ($query, string $search) {
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%';
                $query->where(fn ($q) => $q->where('email', 'like', $like)
                    ->orWhere('channel_name', 'like', $like)
                    ->orWhere('slug', 'like', $like));
            })
            ->latest()
            ->paginate($perPage);
    }

    /** Membuat (atau menaikkan) akun Super Admin. */
    public function createSuperAdmin(string $email, string $name, string $password): User
    {
        $email = strtolower(trim($email));

        $user = User::firstWhere('email', $email) ?? new User([
            'email' => $email,
            'channel_name' => Str::limit($name, 40, ''),
            'slug' => 'sa-'.Str::lower(Str::random(10)),
        ]);

        $user->name = $name;
        $user->password = $password; // di-hash oleh cast "hashed"
        $user->role = User::ROLE_SUPER_ADMIN;
        $user->beta_status = User::BETA_ACTIVE;
        $user->email_verified_at ??= now();
        $user->save();

        return $user;
    }

    /** Data streamer untuk panel admin — tanpa password/token/secret. */
    public function present(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'channel_name' => $user->channel_name,
            'slug' => $user->slug,
            'beta_status' => $user->beta_status,
            'registered_at' => $user->created_at,
        ];
    }

    private function revokeSessions(User $user): void
    {
        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))->where('user_id', $user->getKey())->delete();
        }
    }
}
