<?php

namespace App\Events;

use App\Models\Event;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EventUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $event;
    public $updateType;
    public $updateData;

    /**
     * Create a new event instance.
     */
    public function __construct(Event $event, string $updateType, array $updateData = [])
    {
        $this->event = $event;
        $this->updateType = $updateType;
        $this->updateData = $updateData;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('event-updates.' . $this->event->id),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'event.updated';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'event_id' => $this->event->id,
            'update_type' => $this->updateType,
            'data' => $this->updateData,
            'event' => [
                'id' => $this->event->id,
                'title' => $this->event->title,
                'status' => $this->event->status,
                'current_participants' => $this->event->current_participants,
                'max_participants' => $this->event->max_participants,
                'event_date' => $this->event->event_date,
                'sport' => $this->event->sport->name ?? null,
                'host' => $this->event->host->name ?? null,
            ],
            'timestamp' => now()->toISOString(),
        ];
    }
}
