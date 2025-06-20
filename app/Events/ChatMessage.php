<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChatMessage implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $user;
    public $message;
    public $eventId;
    public $communityId;
    public $messageType;

    /**
     * Create a new event instance.
     */
    public function __construct(
        User $user, 
        string $message, 
        ?int $eventId = null, 
        ?int $communityId = null, 
        string $messageType = 'text'
    ) {
        $this->user = $user;
        $this->message = $message;
        $this->eventId = $eventId;
        $this->communityId = $communityId;
        $this->messageType = $messageType;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        $channels = [];

        if ($this->eventId) {
            $channels[] = new PrivateChannel('event-chat.' . $this->eventId);
        }

        if ($this->communityId) {
            $channels[] = new PrivateChannel('community-chat.' . $this->communityId);
        }

        return $channels;
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'chat.message';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'id' => uniqid(),
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'avatar' => $this->user->userProfile->avatar_url ?? null,
            ],
            'message' => $this->message,
            'message_type' => $this->messageType,
            'event_id' => $this->eventId,
            'community_id' => $this->communityId,
            'timestamp' => now()->toISOString(),
        ];
    }
}
