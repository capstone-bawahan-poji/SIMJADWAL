<?php

namespace App\Enums\Account;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum Role: string
{
    case SUPER_ADMIN = 'superadmin';
    case FACULTY_ADMIN = 'admin_fakultas';
    case STUDY_PROGRAM_ADMIN = 'admin_prodi';
    case LECTURER = 'dosen';
    case STUDENT = 'mahasiswa';

    public function label(): string
    {
        return match ($this) {
            self::SUPER_ADMIN => __('Super Admin'),
            self::FACULTY_ADMIN => __('Faculty Admin'),
            self::STUDY_PROGRAM_ADMIN => __('Study Program Admin'),
            self::LECTURER => __('Lecturer'),
            self::STUDENT => __('Student'),
        };
    }

    public function requiresFaculty(): bool
    {
        return $this === self::FACULTY_ADMIN;
    }

    public function requiresStudyProgram(): bool
    {
        return in_array($this, [self::STUDY_PROGRAM_ADMIN, self::STUDENT], true);
    }

    public function requiresLecturer(): bool
    {
        return $this === self::LECTURER;
    }
}
