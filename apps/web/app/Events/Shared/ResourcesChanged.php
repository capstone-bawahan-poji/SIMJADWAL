<?php

namespace App\Events\Shared;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/**
 * Broadcast as "resources.changed" on private-changes, e.g. {"resources": {"courses": [12], "course_lecturers": null}}.
 * Carries only table names and ids: clients refetch through the REST API, where policies apply.
 */
class ResourcesChanged implements ShouldBroadcastNow
{
    /**
     * @param  array<string, list<int|string>|null>  $resources  table => ids, null = many/unknown
     */
    public function __construct(public readonly array $resources) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('changes');
    }

    public function broadcastAs(): string
    {
        return 'resources.changed';
    }

    /**
     * @return array{resources: array<string, list<int|string>|null>}
     */
    public function broadcastWith(): array
    {
        return ['resources' => $this->resources];
    }
}