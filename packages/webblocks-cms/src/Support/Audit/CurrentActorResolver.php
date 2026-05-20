<?php

namespace WebBlocks\Cms\Support\Audit;

use Illuminate\Support\Facades\Auth;

class CurrentActorResolver
{
    public function resolve(mixed $user = null, ?string $preferredSource = null): array
    {
        $resolvedUser = $user;

        if (! $resolvedUser && Auth::check()) {
            $resolvedUser = Auth::user();
        }

        return [
            'user' => $resolvedUser,
            'user_id' => $resolvedUser?->id,
            'source' => $preferredSource ?: $this->defaultSource($resolvedUser),
        ];
    }

    private function defaultSource(mixed $user): string
    {
        if ($user) {
            return 'admin';
        }

        if (app()->runningInConsole()) {
            return 'console';
        }

        return 'system';
    }
}
