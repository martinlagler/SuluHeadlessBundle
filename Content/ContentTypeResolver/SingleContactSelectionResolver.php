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
use Sulu\Bundle\ContactBundle\Contact\ContactManager;
use Sulu\Bundle\HeadlessBundle\Content\ContentView;
use Sulu\Bundle\HeadlessBundle\Content\Serializer\ContactSerializerInterface;
use Sulu\Component\Content\Compat\PropertyInterface;

class SingleContactSelectionResolver implements ContentTypeResolverInterface
{
    public static function getContentType(): string
    {
        return 'single_contact_selection';
    }

    /**
     * @var ContactManager
     */
    private $contactManager;

    /**
     * @var ContactSerializerInterface
     */
    private $contactSerializer;

    public function __construct(
        ContactManager $contactManager,
        ContactSerializerInterface $contactSerializer
    ) {
        $this->contactManager = $contactManager;
        $this->contactSerializer = $contactSerializer;
    }

    public function resolve($data, PropertyInterface $property, string $locale, array $attributes = []): ContentView
    {
        if (!\is_numeric($data)) {
            return new ContentView(null, ['id' => null]);
        }

        $contact = $this->contactManager->getById((int) $data, $locale);
        $serializationContext = new SerializationContext();
        $serializationContext->setGroups(['partialContact']);

        $content = $this->contactSerializer->serialize($contact->getEntity(), $locale, $serializationContext);

        return new ContentView($content, ['id' => $data]);
    }
}
