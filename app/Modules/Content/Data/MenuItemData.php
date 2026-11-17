<?php

declare(strict_types=1);

namespace App\Modules\Content\Data;

use App\Modules\Content\Enums\LinkType;
use App\Modules\Content\Enums\MenuIcon;
use App\Modules\Content\Models\MenuItem;
use App\Modules\Shared\Support\PersianNormalizer;

/**
 * Input for adding or changing a menu item: the item id is kept only for item targets, the address only for an
 * external link; an empty icon is none.
 */
final readonly class MenuItemData
{
    public function __construct(
        public int $menuId,
        public string $title,
        public ?MenuIcon $icon,
        public LinkType $linkType,
        public ?int $linkId,
        public ?string $externalUrl,
        public bool $isActive,
    ) {}

    /**
     * @param  array<string, mixed>  $data  keys are the menu_items columns (MenuItem::COLUMN_*)
     */
    public static function fromArray(array $data): self
    {
        $type = $data[MenuItem::COLUMN_LINK_TYPE] ?? LinkType::External;
        $type = $type instanceof LinkType ? $type : LinkType::from((string) $type);
        $icon = $data[MenuItem::COLUMN_ICON] ?? null;
        $url = trim((string) ($data[MenuItem::COLUMN_EXTERNAL_URL] ?? ''));

        return new self(
            menuId: (int) ($data[MenuItem::COLUMN_MENU_ID] ?? 0),
            title: PersianNormalizer::normalize(trim(strip_tags((string) ($data[MenuItem::COLUMN_TITLE] ?? '')))),
            icon: $icon instanceof MenuIcon ? $icon : MenuIcon::tryFrom((string) $icon),
            linkType: $type,
            linkId: $type->needsItem() && filled($data[MenuItem::COLUMN_LINK_ID] ?? null) ? (int) $data[MenuItem::COLUMN_LINK_ID] : null,
            externalUrl: $type === LinkType::External && $url !== '' ? $url : null,
            isActive: (bool) ($data[MenuItem::COLUMN_IS_ACTIVE] ?? true),
        );
    }
}
