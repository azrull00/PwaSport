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

class RealTimeNotification implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $user;
    public $notificationType;
    public $title;
    public $message;
    public $data;
    public $priority;

    /**
     * Create a new event instance.
     */
    public function __construct(
        User $user,
        string $notificationType,
        string $title,
        string $message,
        array $data = [],
        string $priority = 'normal'
    ) {
        $this->user = $user;
        $this->notificationType = $notificationType;
        $this->title = $title;
        $this->message = $message;
        $this->data = $data;
        $this->priority = $priority;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.' . $this->user->id),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'notification.received';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'id' => uniqid(),
            'type' => $this->notificationType,
            'title' => $this->title,
            'message' => $this->message,
            'data' => $this->data,
            'priority' => $this->priority,
            'user_id' => $this->user->id,
            'timestamp' => now()->toISOString(),
            'read_at' => null,
        ];
    }
}
