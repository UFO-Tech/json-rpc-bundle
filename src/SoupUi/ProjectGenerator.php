<?php

namespace Ufo\JsonRpcBundle\SoupUi;


use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RouterInterface;
use Ufo\JsonRpcBundle\Controller\ApiController;
use Ufo\JsonRpcBundle\SoupUi\Node\EndpointsNode;
use Ufo\JsonRpcBundle\SoupUi\Node\InterfaceNode;
use Ufo\JsonRpcBundle\SoupUi\Node\Interfaces\ISoupUiNode;
use Ufo\JsonRpcBundle\SoupUi\Node\MethodNode;
use Ufo\JsonRpcBundle\SoupUi\Node\MethodsNode;
use Ufo\JsonRpcBundle\SoupUi\Node\ParameterNode;
use Ufo\JsonRpcBundle\SoupUi\Node\ParametersNode;
use Ufo\JsonRpcBundle\SoupUi\Node\RequestNode;
use Ufo\JsonRpcBundle\SoupUi\Node\ResourceNode;
use Ufo\JsonRpcBundle\SoupUi\Node\RootNode;
use Laminas\Json\Server\Smd\Service;

class ProjectGenerator
{

    /**
     * @var Request
     */
    protected Request $request;

    /**
     * @var array
     */
    protected array $methods = [];

    /**
     * @var \SimpleXMLElement
     */
    protected \SimpleXMLElement $xmlNode;

    /**
     * @var \SimpleXMLElement
     */
    protected \SimpleXMLElement $resourcePost;

    /**
     * @var array
     */
    protected $services = [];

    /**
     * @param RequestStack $requestStack
     * @param RouterInterface $router
     * @param string $env
     * @param string|null $tokenKey
     */
    public function __construct(RequestStack $requestStack, protected RouterInterface $router, protected string $env = 'prod', protected ?string $tokenKey = null)
    {
        $this->request = $requestStack->getCurrentRequest();
    }

    /**
     * @return array
     */
    protected function getSoupUiTemplate(): array
    {
        $interface = new InterfaceNode([], [
            new EndpointsNode($this->request->getSchemeAndHttpHost()),
            new ResourceNode([
                'path' => $this->router->generate(ApiController::API_ROUTE),
            ], [
                new ParametersNode($this->createParameters()),
                new MethodsNode([
                    new MethodNode(
                        [
                            'method' => Request::METHOD_GET,
                            'name' => Request::METHOD_GET,
                        ],
                        [
                            new RequestNode(
                                $this->request->getSchemeAndHttpHost(),
                                ['name' => 'List of procedures']
                            )
                        ]
                    ),
                    new MethodNode(
                        [
                            'method' => Request::METHOD_POST,
                            'name' => Request::METHOD_POST,
                        ],
                        $this->services
                    ),
                ]),
            ]),
        ]);

        return $interface->toArray();
    }

    /**
     * @return array
     */
    protected function createParameters(): array
    {
        $parameters = [];
        if ($this->env == 'dev') {
            $parameters[] = new ParameterNode('Cookie', 'debug=allowDebug');
        }
        if ($this->tokenKey) {
            $parameters[] = new ParameterNode($this->tokenKey, $this->request->query->get('token'));
        }
        return $parameters;
    }

    /**
     * @param Service $procedure
     * @return $this
     */
    public function addService(Service $procedure): static
    {
        $showExamples = $this->request->query->get('show_examples');
        $params = [];
        foreach ($procedure->getParams() as $param) {
            $params[$param['name']] = (!$showExamples || $showExamples != 0) ? $this->paramExampleByType($param['type']) : $param['type'];
        }
        $this->services[] = new RequestNode(
            $this->request->getSchemeAndHttpHost(),
            [
                'name' => $procedure->getName(),
            ],
            $this->createRequestBody(
                $procedure->getName(),
                $params
            )
        );

        return $this;
    }

    /**
     * @param string $type
     * @return mixed
     */
    protected function paramExampleByType(string $type): mixed
    {
        switch ($type) {
            case 'string':
                $example = 'some_string';
                break;
            case 'integer':
                $example = 1;
                break;
            case 'array':
                $example = [];
                break;
            default:
                $example = $type;
        }
        return $example;
    }

    /**
     * @return string
     */
    public function createXml(): string
    {
        $root = new RootNode([
            'name' => $this->request->getHost()
        ]);

        return ArrayToXml::convert(
            ISoupUiNode::SOUPUI_NS . $root->getTag(),
            $this->getSoupUiTemplate(),
            $root->getAttributes()
        );
    }

    /**
     * @param string $method
     * @param array $params
     * @return array
     */
    protected function createRequestBody(string $method, array $params = []): array
    {
        return [
            'id' => null,
            'method' => $method,
            'params' => $params
        ];
    }

}