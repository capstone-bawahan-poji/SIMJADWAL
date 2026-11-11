<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('schedule:integration-token {email : Account the token belongs to} {--name=integration : Token name, e.g. the client system}')]
#[Description('Issue a read-only token for the published schedule API')]
class IssueIntegrationToken extends Command
{
    public function handle(): int
    {
        $user = User::query()->where('email', $this->argument('email'))->first();
        if ($user === null) {
            $this->error('No account with that email.');

            return self::FAILURE;
        }

        $token = $user->createToken((string) $this->option('name'), ['schedule:read'])->plainTextToken;
        $this->info('Token (shown once):');
        $this->line($token);

        return self::SUCCESS;
    }
}
