<?php

namespace FormatD\Mailer\Service;

use GuzzleHttp\Psr7\Uri;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\ContentRepository\Domain\Service\ContextFactoryInterface;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Http\ServerRequestAttributes;
use Neos\Flow\Mvc\ActionRequest;
use Neos\Flow\Mvc\ActionRequestFactory;
use Neos\Flow\Mvc\ActionResponse;
use Neos\Flow\Mvc\Controller\Arguments;
use Neos\Flow\Mvc\Controller\ControllerContext;
use Neos\Flow\Mvc\Routing\Dto\RouteParameters;
use Neos\Flow\Mvc\Routing\UriBuilder;
use Neos\Neos\Domain\Repository\SiteRepository;
use Neos\Neos\Domain\Service\ContentContext;
use Neos\Neos\Service\LinkingService;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\UriInterface;

/**
 * Various helper for CR and nodes
 *
 * Email template nodes are read from the live workspace in the default dimensions, hidden and access-restricted nodes
 * included, of the site configured in FormatD.Mailer.site.siteNodeName (or the default site if none is configured).
 * Their URIs are built against Neos.Flow.http.baseUri, so they also resolve on the command line.
 *
 * @Flow\Scope("singleton")
 */
class ContentRepositoryService {

    #[Flow\InjectConfiguration(package: "Neos.Flow", path: "http.baseUri")]
    protected ?string $baseUri = null;

    #[Flow\InjectConfiguration(package: "FormatD.Mailer", path: "site")]
    protected array $siteConfig;

    #[Flow\Inject]
    protected ContextFactoryInterface $contextFactory;

    #[Flow\Inject]
    protected SiteRepository $siteRepository;

    #[Flow\Inject]
    protected LinkingService $linkingService;

    #[Flow\Inject]
    protected ActionRequestFactory $actionRequestFactory;

    #[Flow\Inject]
    protected ServerRequestFactoryInterface $serverRequestFactory;

    public function getContext(string $workspaceName = 'live'): ContentContext
    {
        $siteNodeName = $this->siteConfig['siteNodeName'] ?? '';
        $site = $siteNodeName ? $this->siteRepository->findOneByNodeName($siteNodeName) : $this->siteRepository->findDefault();

        return $this->contextFactory->create([
            'workspaceName' => $workspaceName,
            'currentSite' => $site,
            'invisibleContentShown' => true,
            'inaccessibleContentShown' => true,
        ]);
    }

    public function uriForNode(NodeInterface $node, ?ActionRequest $actionRequest = null, $absolute = true, $format = 'html'): UriInterface
    {
        $request = $actionRequest ?? $this->createActionRequest();
        $uriBuilder = new UriBuilder();
        $uriBuilder->setRequest($request);
        $controllerContext = new ControllerContext($request, new ActionResponse(), new Arguments([]), $uriBuilder);

        return new Uri($this->linkingService->createNodeUri($controllerContext, $node, null, $format, $absolute));
    }

    protected function createActionRequest(): ActionRequest
    {
        $httpRequest = $this->serverRequestFactory->createServerRequest('GET', new Uri((string)$this->baseUri));
        // Neos resolves the current site from this parameter, which a web request gets from a routing middleware
        $routeParameters = RouteParameters::createEmpty()->withParameter('requestUriHost', $httpRequest->getUri()->getHost());
        $httpRequest = $httpRequest->withAttribute(ServerRequestAttributes::ROUTING_PARAMETERS, $routeParameters);
        return $this->actionRequestFactory->createActionRequest($httpRequest);
    }
}
