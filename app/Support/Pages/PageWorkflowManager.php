<?php

namespace App\Support\Pages;

use App\Models\Page;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class PageWorkflowManager
{
    public const ACTION_SUBMIT_REVIEW = 'submit_review';

    public const ACTION_PUBLISH = 'publish';

    public const ACTION_RESTORE_DRAFT = 'restore_draft';

    public const ACTION_ARCHIVE = 'archive';

    public function allowedStatuses(): array
    {
        return Page::workflowStatuses();
    }

    public function canEditContent(User $user, ?Page $page = null): bool
    {
        if ($page === null) {
            return true;
        }

        if ($user->isSuperAdmin() || $user->isSiteAdmin()) {
            return true;
        }

        return $user->isEditor() && $page->status === Page::STATUS_DRAFT;
    }

    public function workflowActionsFor(User $user, Page $page): array
    {
        $actions = [];

        foreach ([
            self::ACTION_SUBMIT_REVIEW,
            self::ACTION_PUBLISH,
            self::ACTION_RESTORE_DRAFT,
            self::ACTION_ARCHIVE,
        ] as $action) {
            if ($this->canRunAction($user, $page, $action)) {
                $actions[] = $this->actionDefinition($action);
            }
        }

        return $actions;
    }

    public function apply(Page $page, User $user, string $action): string
    {
        $normalizedAction = trim($action);

        if (! in_array($normalizedAction, array_column($this->actionDefinitions(), 'value'), true)) {
            throw ValidationException::withMessages([
                'action' => 'Invalid workflow action.',
            ]);
        }

        if (! $this->roleAllowsAction($user, $normalizedAction)) {
            abort(403, 'You do not have permission to perform this workflow action.');
        }

        if (! $this->statusAllowsAction($page, $normalizedAction)) {
            throw ValidationException::withMessages([
                'action' => 'This workflow transition is not allowed for the current page status.',
            ]);
        }

        return match ($normalizedAction) {
            self::ACTION_SUBMIT_REVIEW => $this->transition($page, Page::STATUS_IN_REVIEW, 'Page submitted for review.'),
            self::ACTION_PUBLISH => $this->transition($page, Page::STATUS_PUBLISHED, 'Page published.'),
            self::ACTION_RESTORE_DRAFT => $this->transition($page, Page::STATUS_DRAFT, 'Page moved back to draft.'),
            self::ACTION_ARCHIVE => $this->transition($page, Page::STATUS_ARCHIVED, 'Page archived.'),
        };
    }

    public function canRunAction(User $user, Page $page, string $action): bool
    {
        return $this->roleAllowsAction($user, $action) && $this->statusAllowsAction($page, $action);
    }

    private function transition(Page $page, string $status, string $message): string
    {
        $attributes = [
            'status' => $status,
            'review_requested_at' => $status === Page::STATUS_IN_REVIEW ? now() : null,
            'published_at' => $status === Page::STATUS_PUBLISHED ? now() : null,
        ];

        $page->forceFill($attributes)->save();

        return $message;
    }

    private function roleAllowsAction(User $user, string $action): bool
    {
        if ($user->isSuperAdmin() || $user->isSiteAdmin()) {
            return true;
        }

        return $user->isEditor() && in_array($action, [
            self::ACTION_SUBMIT_REVIEW,
            self::ACTION_RESTORE_DRAFT,
        ], true);
    }

    private function statusAllowsAction(Page $page, string $action): bool
    {
        return match ($page->status) {
            Page::STATUS_DRAFT => in_array($action, [self::ACTION_SUBMIT_REVIEW, self::ACTION_PUBLISH], true),
            Page::STATUS_IN_REVIEW => in_array($action, [self::ACTION_RESTORE_DRAFT, self::ACTION_PUBLISH, self::ACTION_ARCHIVE], true),
            Page::STATUS_PUBLISHED => in_array($action, [self::ACTION_RESTORE_DRAFT, self::ACTION_ARCHIVE], true),
            Page::STATUS_ARCHIVED => in_array($action, [self::ACTION_RESTORE_DRAFT, self::ACTION_PUBLISH], true),
            default => false,
        };
    }

    private function actionDefinition(string $action): array
    {
        return collect($this->actionDefinitions())
            ->firstWhere('value', $action);
    }

    private function actionDefinitions(): array
    {
        return [
            [
                'value' => self::ACTION_SUBMIT_REVIEW,
                'label' => 'Submit for Review',
                'class' => 'wb-btn wb-btn-secondary',
            ],
            [
                'value' => self::ACTION_PUBLISH,
                'label' => 'Publish',
                'class' => 'wb-btn wb-btn-primary',
            ],
            [
                'value' => self::ACTION_RESTORE_DRAFT,
                'label' => 'Move Back to Draft',
                'class' => 'wb-btn wb-btn-secondary',
            ],
            [
                'value' => self::ACTION_ARCHIVE,
                'label' => 'Archive',
                'class' => 'wb-btn wb-btn-secondary',
            ],
        ];
    }
};
