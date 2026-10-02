<?php

namespace Database\Factories;

use App\Enums\Account\Role;
use App\Models\MasterData\Faculty;
use App\Models\MasterData\Lecturer;
use App\Models\MasterData\StudyProgram;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Roles need the spatie roles to exist: seed RoleSeeder (and PermissionSeeder) first.
 *
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password'),
            'is_active' => true,
            'remember_token' => Str::random(10),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function superAdmin(): static
    {
        return $this->withRole(Role::SUPER_ADMIN);
    }

    public function facultyAdmin(?Faculty $faculty = null): static
    {
        return $this->state(fn () => ['faculty_id' => $faculty ?? Faculty::factory()])
            ->withRole(Role::FACULTY_ADMIN);
    }

    public function tpbAdmin(): static
    {
        return $this->withRole(Role::TPB_ADMIN);
    }

    public function studyProgramAdmin(?StudyProgram $studyProgram = null): static
    {
        return $this->state(fn () => ['study_program_id' => $studyProgram ?? StudyProgram::factory()])
            ->withRole(Role::STUDY_PROGRAM_ADMIN);
    }

    public function student(?StudyProgram $studyProgram = null): static
    {
        return $this->state(fn () => ['study_program_id' => $studyProgram ?? StudyProgram::factory()])
            ->withRole(Role::STUDENT);
    }

    public function lecturer(?Lecturer $lecturer = null): static
    {
        return $this->withRole(Role::LECTURER)->afterCreating(function (User $user) use ($lecturer) {
            ($lecturer ?? Lecturer::factory()->create())->update(['user_id' => $user->id]);
        });
    }

    private function withRole(Role $role): static
    {
        return $this->afterCreating(fn (User $user) => $user->assignRole($role->value));
    }
}
