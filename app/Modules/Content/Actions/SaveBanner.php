<?php

declare(strict_types=1);

namespace App\Modules\Content\Actions;

use App\Modules\Catalog\Queries\CatalogLinks;
use App\Modules\Content\Data\BannerData;
use App\Modules\Content\Enums\LinkType;
use App\Modules\Content\Exceptions\BannerChangeRefused;
use App\Modules\Content\Models\Banner;
use App\Modules\Content\Queries\PageLinks;

/**
 * Creates or updates a banner: a picked catalog item must exist, an external link must be https.
 */
final readonly class SaveBanner
{
    public function __construct(
        private CatalogLinks $catalogLinks,
        private PageLinks $pageLinks,
    ) {}

    /**
     * @throws BannerChangeRefused
     */
    #[\NoDiscard]
    public function __invoke(BannerData $data, ?Banner $banner = null): Banner
    {
        if ($data->linkType->needsItem() && ($data->linkId === null || $this->targetLabel($data->linkType, $data->linkId) === null)) {
            throw BannerChangeRefused::linkItemMissing();
        }

        if ($data->linkType === LinkType::External && ($data->externalUrl === null || ! str_starts_with($data->externalUrl, 'https://') || filter_var($data->externalUrl, FILTER_VALIDATE_URL) === false)) {
            throw BannerChangeRefused::externalUrlInvalid();
        }

        $banner ??= new Banner;
        $banner->setTitle($data->title)
            ->setLinkType($data->linkType)
            ->setLinkId($data->linkId)
            ->setExternalUrl($data->externalUrl)
            ->setIsActive($data->isActive)
            ->setStartsAt($data->startsAt)
            ->setEndsAt($data->endsAt)
            ->save();

        return $banner;
    }

    private function targetLabel(LinkType $type, int $linkId): ?string
    {
        return $type === LinkType::Page ? $this->pageLinks->label($linkId) : $this->catalogLinks->label($type->value, $linkId);
    }
}
