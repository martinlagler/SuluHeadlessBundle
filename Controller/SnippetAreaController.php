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

namespace Sulu\Bundle\HeadlessBundle\Controller;

use JMS\Serializer\SerializationContext;
use JMS\Serializer\SerializerInterface;
use Sulu\Bundle\HeadlessBundle\Content\StructureResolverInterface;
use Sulu\Bundle\HttpCacheBundle\Cache\SuluHttpCache;
use Sulu\Bundle\HttpCacheBundle\CacheLifetime\CacheLifetimeRequestStore;
use Sulu\Bundle\SnippetBundle\Snippet\DefaultSnippetManagerInterface;
use Sulu\Bundle\WebsiteBundle\ReferenceStore\ReferenceStoreInterface;
use Sulu\Component\Content\Mapper\ContentMapperInterface;
use Sulu\Component\Rest\RequestParametersTrait;
use Sulu\Component\Webspace\Analyzer\Attributes\RequestAttributes;
use Sulu\Component\Webspace\Webspace;
use Symfony\Component\DependencyInjection\Exception\ParameterNotFoundException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class SnippetAreaController
{
    use RequestParametersTrait;

    /**
     * @var DefaultSnippetManagerInterface
     */
    private $defaultSnippetManager;

    /**
     * @var ContentMapperInterface
     */
    private $contentMapper;

    /**
     * @var StructureResolverInterface
     */
    private $structureResolver;

    /**
     * @var SerializerInterface
     */
    private $serializer;

    /**
     * @var ReferenceStoreInterface|null
     */
    private $snippetAreaReferenceStore;

    /**
     * @var int
     */
    private $maxAge;

    /**
     * @var int
     */
    private $sharedMaxAge;

    /**
     * @var int
     */
    private $cacheLifetime;

    /**
     * @var CacheLifetimeRequestStore|null
     */
    private $cacheLifetimeRequestStore;

    public function __construct(
        DefaultSnippetManagerInterface $defaultSnippetManager,
        ContentMapperInterface $contentMapper,
        StructureResolverInterface $structureResolver,
        SerializerInterface $serializer,
        ?ReferenceStoreInterface $snippetReferenceStore,
        int $maxAge,
        int $sharedMaxAge,
        int $cacheLifetime,
        ?CacheLifetimeRequestStore $cacheLifetimeRequestStore = null
    ) {
        $this->defaultSnippetManager = $defaultSnippetManager;
        $this->contentMapper = $contentMapper;
        $this->structureResolver = $structureResolver;
        $this->serializer = $serializer;
        $this->snippetAreaReferenceStore = $snippetReferenceStore;
        $this->maxAge = $maxAge;
        $this->sharedMaxAge = $sharedMaxAge;
        $this->cacheLifetime = $cacheLifetime;
        $this->cacheLifetimeRequestStore = $cacheLifetimeRequestStore;

        if (null === $cacheLifetimeRequestStore) {
            @\trigger_error(
                'Instantiating the SnippetAreaController without the $cacheLifetimeRequestStore argument is deprecated!',
                \E_USER_DEPRECATED
            );
        }
    }

    public function getAction(Request $request, string $area): Response
    {
        /** @var RequestAttributes $attributes */
        $attributes = $request->attributes->get('_sulu');

        /** @var Webspace $webspace */
        $webspace = $attributes->getAttribute('webspace');
        $webspaceKey = $webspace->getKey();
        $locale = $request->getLocale();

        $includeExtension = $this->getBooleanRequestParameter($request, 'includeExtension', false, false);

        try {
            $snippetId = $this->defaultSnippetManager->loadIdentifier($webspaceKey, $area);
        } catch (ParameterNotFoundException $e) {
            if ($e->getMessage() !== \sprintf('You have requested a non-existent parameter "%s".', $area)) {
                throw $e;
            }

            throw new NotFoundHttpException(\sprintf('Snippet area "%s" does not exist', $area));
        }

        if (!$snippetId) {
            throw new NotFoundHttpException(\sprintf('No snippet found for snippet area "%s"', $area));
        }

        /** @var string $webspaceKey */
        $webspaceKey = null;
        $snippet = $this->contentMapper->load($snippetId, $webspaceKey, $locale);

        if (!$snippet->getHasTranslation()) {
            throw new NotFoundHttpException(\sprintf('Snippet for snippet area "%s" does not exist in locale "%s"', $area, $locale));
        }

        if ($this->snippetAreaReferenceStore) {
            $this->snippetAreaReferenceStore->add($area);
        }

        $resolvedSnippet = $this->structureResolver->resolve(
            $snippet,
            $locale,
            $includeExtension
        );

        $response = new Response(
            $this->serializer->serialize(
                $resolvedSnippet,
                'json',
                (new SerializationContext())->setSerializeNull(true)
            ),
            200,
            [
                'Content-Type' => 'application/json',
            ]
        );

        $response->setPublic();
        $response->setMaxAge($this->maxAge);
        $response->setSharedMaxAge($this->sharedMaxAge);

        $cacheLifetime = $this->cacheLifetime;
        if (null !== $this->cacheLifetimeRequestStore) {
            $this->cacheLifetimeRequestStore->setCacheLifetime($this->cacheLifetime);
            $cacheLifetime = $this->cacheLifetimeRequestStore->getCacheLifetime();
        }

        $response->headers->set(
            SuluHttpCache::HEADER_REVERSE_PROXY_TTL,
            (string) $cacheLifetime
        );

        return $response;
    }
}
