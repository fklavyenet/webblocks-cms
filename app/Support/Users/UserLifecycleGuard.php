<?php

namespace App\Support\Users;

use App\Models\User;

class UserLifecycleGuard
{
    public function deletionBlocker(User $target, ?User $actingUser = null): ?string
    {
        if ($actingUser && $actingUser->is($target)) {
            return 'You cannot delete your own account from the Users screen.';
        }

        if ($this->wouldRemoveLastActiveAdmin($target, false, false)) {
            return 'The last active admin user cannot be deleted.';
        }

        return null;
    }

    public function updateBlocker(User $target, bool $nextIsAdmin, bool $nextIsActive): ?string
    {
        if ($this->wouldRemoveLastActiveAdmin($target, $nextIsAdmin, $nextIsActive)) {
            return 'The last active admin user cannot be deactivated or demoted.';
        }

        return null;
    }

    public function selfDeletionBlocker(User $user): ?string
    {
        if ($this->wouldRemoveLastActiveAdmin($user, false, false)) {
            return 'The last active admin user cannot delete their own account.';
        }

        return null;
    }

    public function canDelete(User $target, ?User $actingUser = null): bool
    {
        return $this->deletionBlocker($target, $actingUser) === null;
    }

    public function canUpdate(User $target, bool $nextIsAdmin, bool $nextIsActive): bool
    {
        return $this->updateBlocker($target, $nextIsAdmin, $nextIsActive) === null;
    }

    private function wouldRemoveLastActiveAdmin(User $target, bool $nextIsAdmin, bool $nextIsActive): bool
    {
        if (! $target->is_admin || ! $target->is_active) {
            return false;
        }

        if ($nextIsAdmin && $nextIsActive) {
            return false;
        }

        return User::query()
            ->where('is_admin', true)
            ->where('is_active', true)
            ->count() <= 1;
    }
}
