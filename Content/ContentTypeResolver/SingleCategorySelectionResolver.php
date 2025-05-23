<?php

declare(strict_types=1);

/*
 * This file is part of Sulu.
 *
 * (c) Sulu GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\HeadlessBundle\Content\ContentTypeResolver;

use JMS\Serializer\SerializationContext;
use Sulu\Bundle\CategoryBundle\Category\CategoryManagerInterface;
use Sulu\Bundle\HeadlessBundle\Content\ContentView;
use Sulu\Bundle\HeadlessBundle\Content\Serializer\CategorySerializerInterface;
use Sulu\Component\Content\Compat\PropertyInterface;

class SingleCategorySelectionResolver implements ContentTypeResolverInterface
{
    public static function getContentType(): string
    {
        return 'single_category_selection';
    }

    /**
     * @var CategoryManagerInterface
     */
    private $categoryManager;

    /**
     * @var CategorySerializerInterface
     */
    private $categorySerializer;

    public function __construct(
        CategoryManagerInterface $categoryManager,
        CategorySerializerInterface $categorySerializer
    ) {
        $this->categoryManager = $categoryManager;
        $this->categorySerializer = $categorySerializer;
    }

    public function resolve($data, PropertyInterface $property, string $locale, array $attributes = []): ContentView
    {
        if (!\is_numeric($data)) {
            return new ContentView(null, ['id' => null]);
        }

        $category = $this->categoryManager->findById((int) $data);
        $serializationContext = new SerializationContext();
        $serializationContext->setGroups(['partialCategory']);

        $content = $this->categorySerializer->serialize($category, $locale, $serializationContext);

        return new ContentView($content, ['id' => $data]);
    }
}
