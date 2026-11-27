<?php

declare(strict_types=1);

namespace App\Modules\Shared\Contracts;

/**
 * A model whose storefront addresses come from its slug (roadmap S38). When the slug changes, the Seo module sends
 * each old address to the new one at the same position, so both calls must list the addresses in the same order.
 */
interface HasSlugAddresses
{
    /**
     * The site paths (without domain) this model would have with the given slug, e.g. ['/products/eucerin-cream'].
     *
     * @return list<string>
     */
    public function addressesForSlug(string $slug): array;
}
