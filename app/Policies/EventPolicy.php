namespace App\Policies;

use App\Models\Event;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class EventPolicy
{
    use HandlesAuthorization;

    /**
     * Determine if the user can manage the event.
     */
    public function manage(User $user, Event $event)
    {
        return $user->id === $event->host_id || $user->hasRole('admin');
    }

    /**
     * Determine if the user can view the event.
     */
    public function view(User $user, Event $event)
    {
        return true; // All authenticated users can view events
    }

    /**
     * Determine if the user can join the event.
     */
    public function join(User $user, Event $event)
    {
        return !$event->participants()->where('user_id', $user->id)->exists();
    }

    /**
     * Determine if the user can leave the event.
     */
    public function leave(User $user, Event $event)
    {
        return $event->participants()->where('user_id', $user->id)->exists();
    }

    /**
     * Determine if the user can update the event.
     */
    public function update(User $user, Event $event)
    {
        return $user->id === $event->host_id || $user->hasRole('admin');
    }

    /**
     * Determine if the user can delete the event.
     */
    public function delete(User $user, Event $event)
    {
        return $user->id === $event->host_id || $user->hasRole('admin');
    }

    public function manageGuests(User $user, Event $event)
    {
        return $user->id === $event->host_id || $user->hasRole('admin');
    }
} 