<?php

namespace App\Services;

use App\Enums\RoleSlug;
use App\Exceptions\BusinessRuleException;
use App\Models\Candidate;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

class AuthService
{
    public function __construct(
        private readonly AuditLogService $auditLog
    ) {}

    /**
     * @return array{user: User, token: string}
     */
    public function registerCandidate(array $data, Request $request): array
    {
        return DB::transaction(function () use ($data, $request) {
            $candidateRole = Role::query()
                ->where('slug', RoleSlug::CANDIDATE->value)
                ->firstOrFail();

            $fullName = trim($data['first_name'].' '.$data['last_name']);
            $email = strtolower(trim($data['email']));

            $user = User::query()->create([
                'role_id' => $candidateRole->id,
                'name' => $fullName,
                'email' => $email,
                'phone' => $data['phone'] ?? null,
                'password' => $data['password'],
                'is_active' => true,
                'last_login_at' => now(),
            ]);

            $existingCandidate = Candidate::query()->where('email', $email)->first();

            if ($existingCandidate && $existingCandidate->user_id === null) {
                $existingCandidate->update([
                    'user_id' => $user->id,
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'],
                    'phone' => $data['phone'] ?? $existingCandidate->phone,
                    'location' => $data['location'] ?? $existingCandidate->location,
                ]);
            } else {
                Candidate::query()->create([
                    'user_id' => $user->id,
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'],
                    'email' => $email,
                    'phone' => $data['phone'] ?? null,
                    'location' => $data['location'] ?? null,
                    'source' => 'careers_portal',
                ]);
            }

            if ($request->hasSession()) {
                Auth::guard('web')->login($user);
                $request->session()->regenerate();
            }

            $token = $user->createToken('candidate-portal')->plainTextToken;

            $this->auditLog->log(
                eventType: 'auth.candidate_registered',
                auditable: $user,
                description: "Candidate account registered for {$user->email}",
                actor: $user,
                newValues: ['email' => $user->email, 'role' => RoleSlug::CANDIDATE->value]
            );

            $user->load(['role.permissions', 'department', 'candidate']);

            return ['user' => $user, 'token' => $token];
        });
    }

    /**
     * @return array{user: User, token: string}
     */
    public function login(array $credentials, Request $request): array
    {
        $email = strtolower(trim($credentials['email']));

        $user = User::query()
            ->with(['role.permissions', 'department', 'candidate'])
            ->where('email', $email)
            ->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw new BusinessRuleException(
                'INVALID_CREDENTIALS',
                'The provided email or password is incorrect.',
                401
            );
        }

        if (! $user->is_active) {
            throw new BusinessRuleException(
                'ACCOUNT_DISABLED',
                'Your account has been deactivated. Please contact your system administrator.',
                403
            );
        }

        $user->update(['last_login_at' => now()]);

        if ($request->hasSession()) {
            Auth::guard('web')->login($user, (bool) ($credentials['remember'] ?? false));
            $request->session()->regenerate();
        }

        $deviceName = $credentials['device_name'] ?? 'talentflow-web';
        $token = $user->createToken($deviceName)->plainTextToken;

        $this->auditLog->log(
            eventType: 'auth.login',
            auditable: $user,
            description: "User {$user->email} signed in",
            actor: $user
        );

        return ['user' => $user, 'token' => $token];
    }

    public function logout(User $user, Request $request): void
    {
        $token = $user->currentAccessToken();
        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        if ($request->hasSession()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        $this->auditLog->log(
            eventType: 'auth.logout',
            auditable: $user,
            description: "User {$user->email} signed out",
            actor: $user
        );
    }

    public function updateOwnProfile(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data) {
            $oldValues = $user->only(['name', 'phone', 'job_title']);

            $payload = [];
            foreach (['name', 'phone', 'job_title'] as $field) {
                if (array_key_exists($field, $data)) {
                    $payload[$field] = $data[$field];
                }
            }

            if (! empty($data['password'])) {
                $payload['password'] = $data['password'];
            }

            if (! empty($payload)) {
                $user->update($payload);
            }

            if ($user->isCandidate() && $user->candidate && isset($data['phone'])) {
                $user->candidate->update(['phone' => $data['phone']]);
            }

            $this->auditLog->log(
                eventType: 'auth.profile_updated',
                auditable: $user,
                description: "User {$user->email} updated their account profile",
                actor: $user,
                oldValues: $oldValues,
                newValues: $user->only(['name', 'phone', 'job_title'])
            );

            return $user->fresh(['role.permissions', 'department', 'candidate']);
        });
    }
}
