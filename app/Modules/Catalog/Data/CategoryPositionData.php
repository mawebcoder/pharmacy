<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Data;

/**
 * Where one category sits after a drag and drop in the admin tree.
 */
final readonly class CategoryPositionData
{
    public function __construct(
        public int $categoryId,
        public ?int $parentId,
        public int $sort,
    ) {}

    /**
     * Flattens the nested list the admin tree sends: [['id' => 3, 'children' => [['id' => 7, 'children' => []]]], …].
     * Siblings are numbered from 1 in the order given.
     *
     * @param  array<int, array{id: int|string, children?: array<int, mixed>}>  $nodes
     * @return list<self>
     */
    public static function fromNestedList(array $nodes, ?int $parentId = null): array
    {
        $positions = [];

        foreach (array_values($nodes) as $index => $node) {
            $id = (int) $node['id'];
            $positions[] = new self($id, $parentId, $index + 1);

            /** @var array<int, array{id: int|string, children?: array<int, mixed>}> $children */
            $children = $node['children'] ?? [];
            array_push($positions, ...self::fromNestedList($children, $id));
        }

        return $positions;
    }
}
