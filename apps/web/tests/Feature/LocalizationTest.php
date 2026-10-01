<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * The app is Indonesian only. These tests catch English text leaking to users.
 */
class LocalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_translation_key_in_app_has_an_indonesian_entry(): void
    {
        $translations = json_decode(file_get_contents(lang_path('id.json')), true);
        $missing = [];

        foreach (Finder::create()->files()->in(app_path())->name('*.php') as $file) {
            preg_match_all("/(?:__|Lang::get|trans)\(\s*'((?:[^'\\\\]|\\\\.)*)'/", $file->getContents(), $matches);

            foreach ($matches[1] as $key) {
                // Group keys such as auth.failed live in lang/id/*.php, not id.json.
                if (preg_match('/^[a-z_]+\.[a-z_.]+$/', $key)) {
                    continue;
                }

                if (! array_key_exists(stripslashes($key), $translations)) {
                    $missing[] = $file->getRelativePathname().': '.$key;
                }
            }
        }

        $this->assertSame([], $missing, 'Add these keys to lang/id.json.');
    }

    public function test_reset_password_email_is_in_indonesian(): void
    {
        $mail = (new ResetPassword('token'))->toMail(User::factory()->create());

        $this->assertSame('Atur ulang kata sandi Anda', $mail->subject);
        $this->assertSame('Atur Ulang Kata Sandi', $mail->actionText);
    }

    public function test_validation_messages_are_in_indonesian(): void
    {
        $this->post('/forgot-password', ['email' => 'bukan-email'])
            ->assertSessionHasErrors(['email' => 'Email harus berupa alamat email yang valid.']);
    }
}
