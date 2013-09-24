<?php namespace DCarbone\DCSOAP\Client;

use DCarbone\DCSOAP\Query\QueryBase;
use DCarbone\DCSOAP\WSDL\WSDLBase;

/**
 * Class ClientBase
 * @package DCSOAP\Client
 */
class ClientBase
{
    /**
     * @var string
     */
    protected $wsdlPath;

    /**
     * @var string
     */
    protected $serviceUri;

    /**
     * @var WSDLBase
     */
    protected $wsdl;

    /**
     * @var QueryBase
     */
    protected $query;

    /**
     * @var null|string
     */
    protected $user;

    /**
     * @var null|string
     */
    protected $password;

    /**
     * @var string
     */
    protected $authType;

    /**
     * @var bool
     */
    protected $useCachedWSDL;

    /**
     * @var bool
     */
    protected $cacheWSDL;

    /**
     * @var array
     */
    protected $config;

    /**
     * Constructor
     *
     * @param $wsdlPath
     * @param null $user
     * @param null $password
     * @param string $authType
     * @param bool $useCachedWSDL
     * @param bool $cacheWSDL
     * @param array $config
     */
    public function __construct(
        $wsdlPath,
        $user = null,
        $password = null,
        $authType = 'ntlm',
        $useCachedWSDL = true,
        $cacheWSDL = true,
        Array $config = array())
    {
        $this->wsdlPath = $wsdlPath;
        $this->serviceUri = $this->parseServiceUri();
        $this->parseServiceUri();
        $this->user = $user;
        $this->password = $password;
        $this->authType = $authType;
        $this->useCachedWSDL = $useCachedWSDL;
        $this->cacheWSDL = $cacheWSDL;

        $this->parseConfig();

        $this->wsdl = new WSDLBase(
            $this->wsdlPath,
            $this->user,
            $this->password,
            $this->authType,
            $this->useCachedWSDL,
            $this->cacheWSDL);
    }

    /**
     * @return string
     */
    protected function parseServiceUri()
    {
        $parse = parse_url($this->wsdlPath);
        return $parse['scheme'] . '://' .$parse['host'] . $parse['path'];
    }

    /**
     * Get the WSDL class
     *
     * @return WSDLBase
     */
    public function getWSDL()
    {
        return (isset($this->wsdl) ? $this->wsdl : null);
    }

    /**
     * Get the Service Query URI
     *
     * @return string
     */
    public function getServiceUri()
    {
        return (isset($this->serviceUri) ? $this->serviceUri : null);
    }

    /**
     * Get the Query object
     *
     * @return QueryBase|null
     */
    public function getQuery()
    {
        return (isset($this->query) ? $this->query : null);
    }

    /**
     * Call a SOAP service action
     *
     * @param $body
     * @return QueryBase
     */
    public function call($body)
    {
        $bodySXE = new \SimpleXMLElement($body);

        $actionName = $bodySXE->getName();

        return $this->callAction($actionName, $bodySXE->children());
    }

    /**
     * @param $actionName
     * @param $actionBody
     * @return QueryBase
     */
    public function callAction($actionName, $actionBody)
    {
        $this->wsdl->_reset();
        $query = new QueryBase(
            $this->wsdl,
            $this->serviceUri,
            $this->authType,
            $this->user,
            $this->password,
            (isset($this->config['query']) ? $this->config['query'] : array()));

        switch(true)
        {
            case ($actionBody instanceof \SimpleXMLElement) :
                $query->buildQueryFromSXE($actionName, $actionBody);
                break;
            case (is_string($actionBody)) :
                $query->buildQueryFromXMLString($actionName, $actionBody);
        }

        $this->query = $query;
        return $query;
    }

    /**
     * @TODO Implement this
     */
    protected function parseConfig()
    {

    }
}