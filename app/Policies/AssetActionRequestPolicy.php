<?php

namespace App\Policies;

use App\Models\AssetActionRequest;
use App\Models\User;

class AssetActionRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('asset-actions.view');
    }

    public function view(User $user, AssetActionRequest $assetActionRequest): bool
    {
        return $user->can('asset-actions.view');
    }

    public function create(User $user): bool
    {
        return $user->can('asset-actions.create');
    }

    public function update(User $user, AssetActionRequest $assetActionRequest): bool
    {
        return $user->can('asset-actions.create') && $assetActionRequest->status === 'Draft';
    }

    public function submit(User $user, AssetActionRequest $assetActionRequest): bool
    {
        return $user->can('asset-actions.create') && $assetActionRequest->status === 'Draft';
    }

    public function review(User $user, AssetActionRequest $assetActionRequest): bool
    {
        return $user->can('asset-actions.review') && in_array($assetActionRequest->status, ['Draft', 'Submitted'], true);
    }

    public function approve(User $user, AssetActionRequest $assetActionRequest): bool
    {
        return $user->can('asset-actions.approve') && in_array($assetActionRequest->status, ['Submitted', 'Under Review'], true);
    }

    public function reject(User $user, AssetActionRequest $assetActionRequest): bool
    {
        return $user->can('asset-actions.review') && in_array($assetActionRequest->status, ['Submitted', 'Under Review'], true);
    }

    public function cancel(User $user, AssetActionRequest $assetActionRequest): bool
    {
        return ($user->can('asset-actions.create') || $user->can('asset-actions.review'))
            && $assetActionRequest->isPending();
    }

    public function complete(User $user, AssetActionRequest $assetActionRequest): bool
    {
        return $user->can('asset-actions.complete') && $assetActionRequest->isApproved();
    }

    public function delete(User $user, AssetActionRequest $assetActionRequest): bool
    {
        return false;
    }

    public function forceDelete(User $user, AssetActionRequest $assetActionRequest): bool
    {
        return false;
    }
}
