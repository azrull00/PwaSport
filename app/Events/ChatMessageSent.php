<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\User;

class ChatMessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $message;
    public $user;
    public $channelType;
    public $channelId;

    /**
     * Create a new event instance.
     */
    public function __construct($message, User $user, $channelType, $channelId)
    {
        $this->message = $message;
        $this->user = $user;
        $this->channelType = $channelType; // 'event' atau 'community'
        $this->channelId = $channelId;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        if ($this->channelType === 'event') {
            return [
                new PrivateChannel('event-chat.' . $this->channelId),
            ];
        } elseif ($this->channelType === 'community') {
            return [
                new PrivateChannel('community-chat.' . $this->channelId),
            ];
        }

        return [];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->message['id'] ?? null,
            'message' => $this->message['message'],
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'profile_image' => $this->user->userProfile?->profile_image_url ?? null,
            ],
            'created_at' => $this->message['created_at'] ?? now()->toISOString(),
            'channel_type' => $this->channelType,
            'channel_id' => $this->channelId,
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'chat.message.sent';
    }
}
