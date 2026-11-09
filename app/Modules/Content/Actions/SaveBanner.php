<?php

declare(strict_types=1);

namespace App\Modules\Content\Actions;

use App\Modules\Catalog\Queries\CatalogLinks;
use App\Modules\Content\Data\BannerData;
use App\Modules\Content\Enums\BannerLinkType;
use App\Modules\Content\Exceptions\BannerChangeRefused;
use App\Modules\Content\Models\Banner;

/**
 * Creates or updates a banner: a picked catalog item must exist, an external link must be https.
 */
final readonly class SaveBanner
{
    public function __construct(private CatalogLinks $catalogLinks) {}

    /**
     * @throws BannerChangeRefused
     */
    #[\NoDiscard]
    public function __invoke(BannerData $data, ?Banner $banner = null): Banner
    {
        if ($data->linkType->needsItem() && ($data->linkId === null || $this->catalogLinks->label($data->linkType->value, $data->linkId) === null)) {
            throw BannerChangeRefused::linkItemMissing();
        }

        if ($data->linkType === BannerLinkType::External && ($data->externalUrl === null || ! str_starts_with($data->externalUrl, 'https://') || filter_var($data->externalUrl, FILTER_VALIDATE_URL) === false)) {
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
}
