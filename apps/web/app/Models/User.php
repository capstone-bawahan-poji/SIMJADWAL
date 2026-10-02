<?php

namespace App\Models;

use App\Enums\Account\Role;
use App\Models\MasterData\Faculty;
use App\Models\MasterData\Lecturer;
use App\Models\MasterData\StudyProgram;
use App\Policies\Account\UserPolicy;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'identity_number', 'password', 'faculty_id', 'study_program_id', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
#[UsePolicy(UserPolicy::class)]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    /**
     * The single role of the user. Each account holds exactly one spatie role.
     *
     * @return Attribute<Role|null, never>
     */
    protected function role(): Attribute
    {
        return Attribute::get(fn () => Role::tryFrom((string) $this->roles->first()?->name));
    }

    /**
     * NIP or NIM. A lecturer account shows the NIP of its lecturer record, so the number lives in one place.
     *
     * @return Attribute<string|null, never>
     */
    protected function identityNumber(): Attribute
    {
        return Attribute::get(fn (?string $value) => $this->lecturer?->nip ?? $value);
    }

    /**
     * Id of the linked lecturer record (dosen accounts only).
     *
     * @return Attribute<int|null, never>
     */
    protected function lecturerId(): Attribute
    {
        return Attribute::get(fn () => $this->lecturer?->id);
    }

    /** @return BelongsTo<Faculty, $this> */
    public function faculty(): BelongsTo
    {
        return $this->belongsTo(Faculty::class);
    }

    /** @return BelongsTo<StudyProgram, $this> */
    public function studyProgram(): BelongsTo
    {
        return $this->belongsTo(StudyProgram::class);
    }

    /** @return HasOne<Lecturer, $this> */
    public function lecturer(): HasOne
    {
        return $this->hasOne(Lecturer::class);
    }
}
