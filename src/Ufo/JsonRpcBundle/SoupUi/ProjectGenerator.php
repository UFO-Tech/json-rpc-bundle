<?php
/**
 * @author Doctor <doctor.netpeak@gmail.com>
 *
 *
 * Date: 19.05.2017
 * Time: 0:47
 */

namespace Ufo\JsonRpcBundle\SoupUi;


use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Router;
use Ufo\JsonRpcBundle\ApiMethod\Interfaces\IRpcService;
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
    protected $request;

    /**
     * @var array
     */
    protected $methods = [];

    /**
     * @var \SimpleXMLElement
     */
    protected $xmlNode;

    /**
     * @var \SimpleXMLElement
     */
    protected $resourcePost;

    /**
     * @var Router
     */
    protected $router;

    /**
     * @var string
     */
    protected $env;

    /**
     * @var string
     */
    protected $tokenKey;

    /**
     * @var array
     */
    protected $services = [];

    /**
     * ProjectGenerator constructor.
     * @param RequestStack $requestStack
     * @param Router $router
     * @param string $environment
     * @param null $soaTokenKey
     */
    public function __construct(RequestStack $requestStack, Router $router, $environment = 'prod', $soaTokenKey = null)
    {
        $this->request = $requestStack->getCurrentRequest();
        $this->router = $router;
        $this->env = $environment;
        $this->tokenKey = $soaTokenKey;
    }

    /**
     * @return array
     */
    protected function getSoupUiTemplate()
    {
        $interface = new InterfaceNode([], [
            new EndpointsNode($this->request->getSchemeAndHttpHost()),
            new ResourceNode([
                'path' => $this->router->generate('ufo_api_server'),
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
    protected function createParameters()
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
    public function addService(Service $procedure)
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
    protected function paramExampleByType($type)
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
    public function createXml()
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
    protected function createRequestBody($method, $params = [])
    {
        return [
            'id' => null,
            'method' => $method,
            'params' => $params
        ];
    }

}