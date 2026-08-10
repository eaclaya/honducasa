<?php

namespace App\Actions\Admin;

use App\Models\AdminActivity;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Write one entry to the moderation audit trail.
 *
 * Every admin action funnels through here rather than logging inline, so an
 * action that forgets to audit is a missing call in one obvious place instead
 * of a silently unlogged write.
 */
class RecordAdminActivity
{
    public function __construct(private readonly Request $request) {}

    /**
     * @param  array<string, mixed>  $changes  What actually changed, as `['field' => ['from' => x, 'to' => y]]`.
     */
    public function handle(
        User $admin,
        string $action,
        ?Model $subject = null,
        ?string $reason = null,
        array $changes = [],
    ): AdminActivity {
        return AdminActivity::query()->create([
            'admin_id' => $admin->getKey(),
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'reason' => $reason,
            'changes' => $changes === [] ? null : $changes,
            'ip_address' => $this->request->ip(),
        ]);
    }
}
