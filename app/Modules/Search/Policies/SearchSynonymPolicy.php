<?php

declare(strict_types=1);

namespace App\Modules\Search\Policies;

use App\Modules\Search\Models\SearchSynonym;
use Illuminate\Contracts\Auth\Access\Authorizable;

/**
 * Search synonyms (roadmap D21.7): managed by content editors; changes are copied into the search index.
 */
final class SearchSynonymPolicy
{
    public function viewAny(Authorizable $admin): bool
    {
        return $admin->can('ViewAny:SearchSynonym');
    }

    public function view(Authorizable $admin, SearchSynonym $synonym): bool
    {
        return $admin->can('View:SearchSynonym');
    }

    public function create(Authorizable $admin): bool
    {
        return $admin->can('Create:SearchSynonym');
    }

    public function update(Authorizable $admin, SearchSynonym $synonym): bool
    {
        return $admin->can('Update:SearchSynonym');
    }

    public function delete(Authorizable $admin, SearchSynonym $synonym): bool
    {
        return $admin->can('Delete:SearchSynonym');
    }
}
