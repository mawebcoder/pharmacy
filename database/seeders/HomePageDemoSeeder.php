<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Catalog\Actions\AddProductsToCollection;
use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductCollection;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Models\Tag;
use App\Modules\Catalog\Search\ProductSearchIndex;
use App\Modules\Content\Actions\SaveHomeWidget;
use App\Modules\Content\Actions\SetPlaceBanners;
use App\Modules\Content\Data\HomeWidgetData;
use App\Modules\Content\Enums\BannerLinkType;
use App\Modules\Content\Enums\BannerPlace;
use App\Modules\Content\Enums\HomeWidgetType;
use App\Modules\Content\Models\Banner;
use App\Modules\Content\Models\HomeWidget;
use App\Modules\Media\Actions\AttachImage;
use App\Modules\Media\Contracts\HasImagePresets;
use App\Modules\Media\Models\Media;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Imagick;
use ImagickDraw;
use ImagickPixel;
use RuntimeException;

/**
 * Sample content for the home page (S13b), so every widget type has at least 6 items to show: 6 brands, 6
 * categories, 6 featured tags, 12 products in stock, «محصولات محبوب» and a second collection, 6 banners and one
 * widget of each type. Images are generated colour blocks. Local and testing only; it refuses to run twice.
 *
 *   php artisan db:seed --class=HomePageDemoSeeder
 */
final class HomePageDemoSeeder extends Seeder
{
    public const string MARKER_SLUG = 'demo-brand-1';

    private const int ITEMS = 6;

    private const int PRODUCTS = 12;

    /**
     * Item colours, one per item; images of the same kind differ only by colour.
     */
    private const array COLOURS = ['#0f766e', '#2563eb', '#db2777', '#ca8a04', '#7c3aed', '#ea580c', '#0891b2', '#16a34a', '#dc2626', '#4f46e5', '#65a30d', '#be185d'];

    private const array BRANDS = ['نیلوفر', 'آریا درم', 'سپیدار', 'ماهان', 'رویش', 'آوا'];

    private const array CATEGORIES = ['مراقبت پوست', 'مراقبت مو', 'مکمل‌ها', 'آرایشی', 'مادر و کودک', 'بهداشت دهان'];

    private const array TAGS = ['ویتامین C', 'ویتامین D', 'کلاژن', 'امگا ۳', 'آبرسانی', 'ضدآفتاب'];

    private const array PRODUCT_NAMES = [
        'کرم آبرسان صورت', 'سرم ویتامین C', 'شامپو ضدریزش', 'ضدآفتاب SPF50', 'قرص ویتامین D3', 'کپسول امگا ۳',
        'ژل شستشوی صورت', 'ماسک موی ترمیم‌کننده', 'رژ لب مات', 'لوسیون بدن کودک', 'خمیردندان سفیدکننده', 'پودر کلاژن',
    ];

    public function run(AttachImage $attachImage, AddProductsToCollection $addProducts, SetPlaceBanners $setPlaceBanners, SaveHomeWidget $saveWidget): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('HomePageDemoSeeder adds sample data and does not run in production.');
        }

        if (Brand::query()->withTrashed()->where(Brand::COLUMN_SLUG, self::MARKER_SLUG)->exists()) {
            $this->command?->warn('Home page sample data is already there; nothing added.');

            return;
        }

        $brands = [];
        $categories = [];
        $tags = [];

        foreach (range(0, self::ITEMS - 1) as $index) {
            $number = $index + 1;

            $brands[] = $brand = Brand::factory()->create([
                Brand::COLUMN_NAME_FA => self::BRANDS[$index],
                Brand::COLUMN_NAME_EN => "Demo Brand {$number}",
                Brand::COLUMN_SLUG => "demo-brand-{$number}",
            ]);
            $this->attach($attachImage, $brand, Brand::IMAGE_LOGO, 800, 400, $index, 'png');

            $categories[] = $category = Category::factory()->create([
                Category::COLUMN_NAME_FA => self::CATEGORIES[$index],
                Category::COLUMN_NAME_EN => "Demo Category {$number}",
                Category::COLUMN_SLUG => "demo-category-{$number}",
                Category::COLUMN_SORT => $number,
            ]);
            $this->attach($attachImage, $category, Category::IMAGE_ICON, 256, 256, $index, 'png');

            $tags[] = $tag = Tag::factory()->featured($number)->create([
                Tag::COLUMN_NAME_FA => self::TAGS[$index],
                Tag::COLUMN_NAME_EN => "Demo Tag {$number}",
                Tag::COLUMN_SLUG => "demo-tag-{$number}",
            ]);
            $this->attach($attachImage, $tag, Tag::IMAGE_ICON, 256, 256, $index, 'png');
        }

        $productIds = [];

        foreach (range(0, self::PRODUCTS - 1) as $index) {
            $number = $index + 1;
            $price = (20 + 15 * $index) * 10_000;

            $product = Product::factory()->active()->create([
                Product::COLUMN_NAME_FA => self::PRODUCT_NAMES[$index],
                Product::COLUMN_NAME_EN => "Demo Product {$number}",
                Product::COLUMN_SLUG => "demo-product-{$number}",
                Product::COLUMN_BRAND_ID => $brands[$index % self::ITEMS]->getId(),
                Product::COLUMN_PRIMARY_CATEGORY_ID => $categories[$index % self::ITEMS]->getId(),
            ]);
            $product->tags()->attach($tags[$index % self::ITEMS]->getId());

            $variant = ProductVariant::factory()->for($product)->create([
                ProductVariant::COLUMN_SKU => sprintf('DEMO-%04d-01', $number),
                ProductVariant::COLUMN_OFFICIAL_PRICE => $price,
                ProductVariant::COLUMN_PRICE => $price,
                // Every third product is on sale, so the cards show a discount.
                ProductVariant::COLUMN_SALE_PRICE => $index % 3 === 0 ? (int) ($price * 0.85) : null,
            ]);
            $product->setDefaultVariantId($variant->getId())->save();

            $this->attach($attachImage, $product, Product::IMAGE_GALLERY, 1200, 1200, $index, 'jpg');
            ProductSearchIndex::refresh($product->fresh() ?? $product);
            $productIds[] = $product->getId();
        }

        $popular = ProductCollection::query()->where(ProductCollection::COLUMN_SLUG, ProductCollection::POPULAR_SLUG)->firstOrFail();
        $addProducts($popular, array_slice($productIds, 0, 8));

        $special = ProductCollection::factory()->create([
            ProductCollection::COLUMN_NAME_FA => 'پیشنهاد ویژه',
            ProductCollection::COLUMN_NAME_EN => 'Demo Special Offers',
            ProductCollection::COLUMN_SLUG => 'demo-special-offers',
            ProductCollection::COLUMN_SORT => 1,
        ]);
        $addProducts($special, array_slice($productIds, 4, 8));

        $bannerTargets = [
            [BannerLinkType::Collection, $special->getId()],
            [BannerLinkType::Category, $categories[0]->getId()],
            [BannerLinkType::Brand, $brands[1]->getId()],
            [BannerLinkType::Collection, $popular->getId()],
            [BannerLinkType::Tag, $tags[0]->getId()],
            [BannerLinkType::BrandsList, null],
        ];
        $bannerIds = [];

        foreach ($bannerTargets as $index => [$type, $id]) {
            $banner = Banner::factory()->linkingTo($type, $id)->create([Banner::COLUMN_TITLE => 'بنر نمونه '.($index + 1)]);
            $this->attach($attachImage, $banner, Banner::IMAGE_DESKTOP, 1920, 600, $index, 'jpg');
            $this->attach($attachImage, $banner, Banner::IMAGE_MOBILE, 800, 1000, $index, 'jpg');
            $bannerIds[] = $banner->getId();
        }

        $widget = fn (array $data): HomeWidget => $saveWidget(HomeWidgetData::fromArray($data));

        $slider = $widget([HomeWidget::COLUMN_TYPE => HomeWidgetType::Slider]);
        $setPlaceBanners(BannerPlace::HomeWidget, $slider->getId(), $bannerIds);
        $widget([HomeWidget::COLUMN_TYPE => HomeWidgetType::Categories, HomeWidget::COLUMN_TITLE_FA => 'دسته‌بندی‌ها', 'category_ids' => array_map(fn (Category $category): int => $category->getId(), $categories)]);
        $widget([HomeWidget::COLUMN_TYPE => HomeWidgetType::Collection, 'collection_id' => $popular->getId()]);
        $widget([HomeWidget::COLUMN_TYPE => HomeWidgetType::Brands, HomeWidget::COLUMN_TITLE_FA => 'برندها', 'brand_ids' => array_map(fn (Brand $brand): int => $brand->getId(), $brands)]);
        $widget([HomeWidget::COLUMN_TYPE => HomeWidgetType::Collection, 'collection_id' => $special->getId()]);
        $widget([HomeWidget::COLUMN_TYPE => HomeWidgetType::Tags, HomeWidget::COLUMN_TITLE_FA => 'خرید بر اساس نیاز', 'tag_ids' => array_map(fn (Tag $tag): int => $tag->getId(), $tags)]);
        $widget([HomeWidget::COLUMN_TYPE => HomeWidgetType::Trust]);

        $this->command?->info('Home page sample data added: 6 brands, 6 categories, 6 tags, 12 products, 2 collections, 6 banners, 7 widgets.');
    }

    /**
     * Attaches a generated image: a solid colour with a lighter block in the middle.
     */
    private function attach(AttachImage $attachImage, HasImagePresets $owner, string $collection, int $width, int $height, int $index, string $format): Media
    {
        $image = new Imagick;
        $image->newImage($width, $height, new ImagickPixel(self::COLOURS[$index % count(self::COLOURS)]));
        $draw = new ImagickDraw;
        $draw->setFillColor(new ImagickPixel('rgba(255, 255, 255, 0.35)'));
        $draw->roundRectangle(intdiv($width, 4), intdiv($height, 4), intdiv($width * 3, 4), intdiv($height * 3, 4), 24, 24);
        $image->drawImage($draw);
        $image->setImageFormat($format);

        $path = tempnam(sys_get_temp_dir(), 'demo').'.'.$format;
        file_put_contents($path, $image->getImagesBlob());

        return $attachImage($owner, $collection, new UploadedFile($path, "demo.{$format}", $format === 'jpg' ? 'image/jpeg' : 'image/png', null, true));
    }
}
