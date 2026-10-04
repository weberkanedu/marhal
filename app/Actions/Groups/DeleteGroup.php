<?php

namespace App\Actions\Groups;

use App\Models\Group;
use Illuminate\Support\Facades\DB;

/**
 * Grubu siler; yolcuları turda kalır, sadece grupsuz olur.
 */
class DeleteGroup
{
    public function handle(Group $group): void
    {
        DB::transaction(function () use ($group): void {
            $group->registrations()->update(['group_id' => null]);
            $group->forceDelete();
        });
    }
}
