<?php

namespace Database\Seeders\Demo;

use App\Enums\Account\Role;
use App\Models\MasterData\Faculty;
use App\Models\MasterData\Lecturer;
use App\Models\MasterData\StudyProgram;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Demo accounts for testing and the thesis demo (DATABASE.md §10.8): one admin per faculty,
 * one admin per study program (email from the program prefix in data/master), plus one student and one lecturer.
 */
class DemoAccountSeeder extends Seeder
{
    private const FACULTY_ABBREVIATIONS = [
        'Fakultas Sains dan Teknologi Informasi' => 'fsti',
        'Fakultas Pembangunan Berkelanjutan' => 'fpb',
        'Fakultas Rekayasa dan Teknologi Industri' => 'frti',
    ];

    public function run(): void
    {
        foreach (self::FACULTY_ABBREVIATIONS as $name => $abbreviation) {
            $faculty = Faculty::query()->where('name', $name)->firstOrFail();

            $this->account("admin.{$abbreviation}@example.test", 'Admin '.strtoupper($abbreviation), Role::FACULTY_ADMIN, ['faculty_id' => $faculty->id]);
        }

        foreach (MasterDataSeeder::programs() as ['program' => $program, 'data' => $data]) {
            $prefix = strtolower($data['prefix']);

            $this->account("admin.{$prefix}@example.test", "Admin {$program->name}", Role::STUDY_PROGRAM_ADMIN, ['study_program_id' => $program->id]);
        }

        $informatics = StudyProgram::query()->where('name', 'Informatika')->firstOrFail();
        $this->account('mahasiswa.if@example.test', 'Mahasiswa Informatika', Role::STUDENT, ['study_program_id' => $informatics->id]);

        $lecturer = Lecturer::query()->where('code', 'IF01')->first();

        if ($lecturer === null) {
            $this->command?->warn('Lecturer IF01 not found, skipping dosen.if01@example.test. Add data/master/informatika.json.');

            return;
        }

        $user = $this->account('dosen.if01@example.test', $lecturer->name, Role::LECTURER);
        $lecturer->update(['user_id' => $user->id]);
    }

    /**
     * @param  array<string, int>  $scope
     */
    private function account(string $email, string $name, Role $role, array $scope = []): User
    {
        $user = User::query()->firstOrCreate(
            ['email' => $email],
            ['name' => $name, 'password' => config('seeding.default_password'), ...$scope],
        );

        $user->syncRoles([$role->value]);

        return $user;
    }
}
