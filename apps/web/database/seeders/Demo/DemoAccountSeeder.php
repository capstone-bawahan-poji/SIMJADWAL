<?php

namespace Database\Seeders\Demo;

use App\Enums\Account\Role;
use App\Models\MasterData\Faculty;
use App\Models\MasterData\Lecturer;
use App\Models\MasterData\StudyProgram;
use App\Models\User;
use Illuminate\Database\Seeder;

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

        // First lecturer of data/master/informatika.json by NIP. dosen.if01 is their account, so the email-based one skips them.
        $lecturer = Lecturer::query()->where('study_program_id', $informatics->id)->orderBy('nip')->first();

        $this->lecturerAccounts($lecturer?->id);

        $this->account('admin.tpb@example.test', 'Admin TPB', Role::TPB_ADMIN);

        if ($lecturer === null) {
            $this->command?->warn('No Informatika lecturer found, skipping dosen.if01@example.test. Add data/master/informatika.json.');

            return;
        }

        $user = $this->account('dosen.if01@example.test', $lecturer->name, Role::LECTURER);
        $lecturer->update(['user_id' => $user->id]);
    }

    private function lecturerAccounts(?int $skipLecturerId): void
    {
        foreach (MasterDataSeeder::programs() as ['data' => $data]) {
            foreach ($data['lecturers'] as $row) {
                if (empty($row['email'])) {
                    continue;
                }

                $lecturer = Lecturer::query()->where('nip', $row['nip'])->firstOrFail();

                if ($lecturer->id === $skipLecturerId) {
                    continue;
                }

                $user = $this->account($row['email'], $lecturer->name, Role::LECTURER);

                if ($lecturer->user_id === null) {
                    $lecturer->update(['user_id' => $user->id]);
                }
            }
        }
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
