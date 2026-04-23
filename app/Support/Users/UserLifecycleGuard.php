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

        if ($this->wouldRemoveLastActiveSuperAdmin($target, User::ROLE_EDITOR, false)) {
            return 'The last active super admin cannot be deleted.';
        }

        return null;
    }

    public function updateBlocker(User $target, string $nextRole, bool $nextIsActive): ?string
    {
        if ($this->wouldRemoveLastActiveSuperAdmin($target, $nextRole, $nextIsActive)) {
            return 'The last active super admin cannot be deactivated or demoted.';
        }

        return null;
    }

    public function selfDeletionBlocker(User $user): ?string
    {
        if ($this->wouldRemoveLastActiveSuperAdmin($user, User::ROLE_EDITOR, false)) {
            return 'The last active super admin cannot delete their own account.';
        }

        return null;
    }

    public function canDelete(User $target, ?User $actingUser = null): bool
    {
        return $this->deletionBlocker($target, $actingUser) === null;
    }

    public function canUpdate(User $target, string $nextRole, bool $nextIsActive): bool
    {
        return $this->updateBlocker($target, $nextRole, $nextIsActive) === null;
    }

    private function wouldRemoveLastActiveSuperAdmin(User $target, string $nextRole, bool $nextIsActive): bool
    {
        if (! $target->isSuperAdmin() || ! $target->is_active) {
            return false;
        }

        if ($nextRole === User::ROLE_SUPER_ADMIN && $nextIsActive) {
            return false;
        }

        return User::query()
            ->where('role', User::ROLE_SUPER_ADMIN)
            ->where('is_active', true)
            ->count() <= 1;
    }
}
