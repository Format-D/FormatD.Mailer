<?php
namespace FormatD\Mailer\Service;


use Neos\Flow\Annotations as Flow;
use Neos\ContentRepository\Core\ContentRepository;
use Neos\ContentRepositoryRegistry\ContentRepositoryRegistry;
use Neos\ContentRepository\Core\Factory\ContentRepositoryId;
use Neos\ContentRepository\Core\Projection\ContentGraph\ContentGraphInterface;
use Neos\ContentRepository\Core\SharedModel\Workspace\WorkspaceName;
use Neos\ContentRepository\Core\Projection\Workspace\Workspace;
use Neos\ContentRepository\Core\Projection\ContentGraph\Node;
use GuzzleHttp\Psr7\Uri;
use Neos\Flow\Mvc\Routing\UriBuilder;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Neos\Flow\Http\ServerRequestAttributes;
use Neos\Flow\Mvc\ActionRequestFactory;
use Neos\Flow\Mvc\Routing\Dto\RouteParameters;
use Neos\Neos\FrontendRouting\NodeAddressFactory;

/**
 * Various helper for CR and nodes
 *
 * @Flow\Scope("singleton")
 */
class ContentRepositoryService {

    #[Flow\InjectConfiguration(package: "Neos.Flow", path: "http.baseUri")]
    protected string $baseUri;

    #[Flow\InjectConfiguration(package: "FormatD.Mailer", path: "site")]
    protected array $siteConfig;

    #[Flow\Inject]
    protected ContentRepositoryRegistry $contentRepositoryRegistry;

    #[Flow\Inject]
    protected ActionRequestFactory $actionRequestFactory;

    #[Flow\Inject]
    protected ServerRequestFactoryInterface $serverRequestFactory;

    protected UriBuilder $uriBuilder;

    public function initializeObject()
    {
        $httpRequest = $this->serverRequestFactory->createServerRequest('GET', new Uri($this->baseUri));

        if (isset($this->baseUri) && is_string($this->baseUri) && !empty($this->baseUri)) {
            /** @var RouteParameters $routingParameters */
            $routingParameters = $httpRequest->getAttribute(ServerRequestAttributes::ROUTING_PARAMETERS) ?? RouteParameters::createEmpty();
            $routingParameters = $routingParameters->withParameter('requestUriHost', $this->baseUri);
            $routingParameters = $routingParameters->withParameter('siteNodeName', $this->siteConfig['siteNodeName']);
            $routingParameters = $routingParameters->withParameter('contentRepositoryId', $this->siteConfig['contentRepositoryId']);

            $httpRequestAttributes = $httpRequest->getAttributes();
            $httpRequestAttributes[ServerRequestAttributes::ROUTING_PARAMETERS] = $routingParameters;
            $reflectedHttpRequest = new \ReflectionObject($httpRequest);
            $reflectedHttpRequestAttributes = $reflectedHttpRequest->getProperty('attributes');
            $reflectedHttpRequestAttributes->setAccessible(true);
            $reflectedHttpRequestAttributes->setValue($httpRequest, $httpRequestAttributes);
        }

        $request = $this->actionRequestFactory->createActionRequest($httpRequest);
        $uriBuilder = new UriBuilder();
        $uriBuilder->setRequest($request);
        $this->uriBuilder = $uriBuilder;
    }

    public function getContentRepository(string $contentRepositoryId = 'default'): ContentRepository
    {
        $contentRepositoryId = ContentRepositoryId::fromString($contentRepositoryId);
        return $this->contentRepositoryRegistry->get($contentRepositoryId);
    }

    public function getWorkspace(ContentRepository $contentRepository, string $workspaceName = 'live'): Workspace
    {
        return $contentRepository->getWorkspaceFinder()->findOneByName(WorkspaceName::fromString($workspaceName));
    }

    public function getContentGraph(ContentRepository $contentRepository): ContentGraphInterface
    {
        return $contentRepository->getContentGraph();
    }

    public function getNodeUri(Node $node, $arguments = [], $absolute = true, $format = 'html')
    {
        $nodeAddressFactory = NodeAddressFactory::create($this->getContentRepository());
        $nodeAddress = $nodeAddressFactory->createFromNode($node);

        return $this->uriBuilder
            ->setArguments($arguments)
            ->setCreateAbsoluteUri($absolute)
            ->setFormat($format)
            ->uriFor('show', ['node' => $nodeAddress], 'Frontend\Node', 'Neos.Neos')
        ;
    }
}
