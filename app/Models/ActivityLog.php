<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    // Immutable audit trail — there is no updated_at column at all, a log
    // entry is written once and never touched again.
    const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'user_name',
        'action',
        'module',
        'subject_type',
        'subject_id',
        'description',
        'changes',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'changes' => 'array',
        ];
    }

    public function causer()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Write one activity log entry. Call this immediately after a write
     * succeeds (create/update/delete) or right at a login/logout event.
     *
     * $subject, when given, is the model the action was about — its class
     * and id are captured even after $subject->delete() has already been
     * called, since the in-memory object still holds its attributes.
     *
     * $changes is a free-form payload for whatever a reviewer would need to
     * see: ['after' => [...]] for a create, ['before' => [...], 'after' =>
     * [...]] for an update, ['before' => [...]] as a snapshot for a delete.
     */
    public static function record(string $action, string $module, string $description, $subject = null, ?array $changes = null): self
    {
        $user = auth()->user();
        $request = request();

        return static::create([
            'user_id'      => $user?->id,
            'user_name'    => $user ? static::actorLabel($user) : null,
            'action'       => $action,
            'module'       => $module,
            'subject_type' => $subject ? get_class($subject) : null,
            'subject_id'   => $subject?->id,
            'description'  => $description,
            'changes'      => $changes,
            'ip_address'   => $request?->ip(),
            'user_agent'   => $request ? substr((string) $request->userAgent(), 0, 255) : null,
        ]);
    }

    /**
     * "RANK Firstname M. Lastname" — falls back gracefully if fullname or
     * rank haven't been set on older/seed records.
     */
    public static function actorLabel(User $user): string
    {
        $name = $user->fullname ?: trim($user->firstname.' '.$user->lastname);

        return trim(($user->rank ? $user->rank.' ' : '').$name);
    }
}
