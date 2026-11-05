<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
| private-changes carries App\Events\Shared\ResourcesChanged (table names and ids only) to every
| signed-in client. Data itself is refetched through the API, where policies apply.
*/

Broadcast::channel('changes', fn (User $user) => $user->is_active);
