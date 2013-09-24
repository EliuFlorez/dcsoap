<?php namespace DCarbone\DCSOAP\WSDL;

use DCarbone\DCSOAP\WSDL\Actions\ActionBase;

/**
 * Class WSDLBase
 * @package DCarbone\DCSOAP\WSDL
 */
class WSDLBase
{
    /**
     * @var string
     */
    protected $wsdlPath;

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
    protected $curlInfo;

    /**
     * @var array
     */
    protected $actions = array();

    /**
     * @var \SimpleXMLElement
     */
    protected $sxe;

    /**
     * @var string
     */
    protected $targetNamespace;

    /**
     * @var string
     */
    protected $wsdlName;

    /**
     * @var array
     */
    protected $namespaces = array();

    /**
     * Constructor
     *
     * @param $wsdlPath
     * @param $user
     * @param $password
     * @param $authType
     * @param $useCachedWSDL
     * @param $cacheWSDL
     */
    public function __construct(
        $wsdlPath,
        $user,
        $password,
        $authType,
        $useCachedWSDL,
        $cacheWSDL)
    {
        $this->wsdlPath = $wsdlPath;
        $this->user = $user;
        $this->password = $password;
        $this->authType = $authType;
        $this->useCachedWSDL = $useCachedWSDL;
        $this->cacheWSDL = $cacheWSDL;

        preg_match('/[^\/]+$/', $wsdlPath, $match);
        if (is_array($match) && count($match) > 0)
            $this->wsdlName = end($match);

        $this->importWSDL();
    }

    /**
     * Get the "name" of this WSDL
     *
     * @return mixed|string
     */
    public function getWSDLName()
    {
        return $this->wsdlName;
    }

    /**
     * Get the base namespace for this WSDL's actions
     *
     * @return string
     */
    public function getTargetNamespace()
    {
        return isset($this->targetNamespace) ? $this->targetNamespace : null;
    }

    /**
     * Get all the Namespaces present
     *
     * @return array
     */
    public function getAllNamespaces()
    {
        return $this->namespaces;
    }

    /**
     * Get the prefixes of the namespaces present in this wsdl
     *
     * @return array
     */
    public function getNamespacePrefixes()
    {
        return array_keys($this->namespaces);
    }

    /**
     * Get all the actions on this WSDL
     *
     * @return array
     */
    public function getActions()
    {
        return $this->actions;
    }

    /**
     * Get a list of all the action names available
     *
     * @return array
     */
    public function getActionList()
    {
        return array_keys($this->actions);
    }

    /**
     * Get a specific action
     *
     * @param $actionName
     * @return ActionBase|null
     */
    public function getAction($actionName)
    {
        return (array_key_exists($actionName, $this->actions) ? $this->actions[$actionName] : null);
    }

    protected function cacheExists()
    {
        return false;
    }

    protected function loadFromCache()
    {
        return false;
    }

    /**
     * Load and parse the WSDL
     *
     * @throws \InvalidArgumentException
     * @throws \Exception
     */
    protected function importWSDL()
    {
        if ($this->cacheExists())
        {

        }
        else
        {
            $ch = curl_init($this->wsdlPath);
            curl_setopt_array($ch, array(
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYPEER => 0,
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_HTTPHEADER => array(
                    'Accept: text/xml',
                    'Cache-Control: no-cache',
                    'Pragma: no-cache',
                ),
            ));

            if (is_string($this->authType) && $this->authType !== "")
            {
                if (stristr($this->authType, 'curlauth') !== false && defined(strtoupper($this->authType)))
                    curl_setopt($ch, CURLOPT_HTTPAUTH, constant(strtoupper($this->authType)));
                else if (defined("CURLAUTH_".strtoupper($this->authType)))
                    curl_setopt($ch, CURLOPT_HTTPAUTH, constant("CURLAUTH_".strtoupper($this->authType)));
                else
                    throw new \InvalidArgumentException('Unknown CURLAUTH value of "'.strtoupper($this->authType).'" requested');
            }

            if (is_string($this->user) && $this->user !== "" && is_string($this->password) && $this->password !== "")
                curl_setopt($ch, CURLOPT_USERPWD, "{$this->user}:{$this->password}");

            $response = curl_exec($ch);
            $error = curl_error($ch);
            $info = curl_getinfo($ch);
            curl_close($ch);

            if ($error !== false && $error !== "")
                throw new \Exception('Error encountered when trying to load WSDL from "'.$this->wsdlPath.'": "'.htmlspecialchars($error).'"');

            $this->curlInfo = $info;

            try {
                $this->sxe = new \SimpleXMLElement($response);
                $this->parseWSDL();
            }
            catch(\Exception $e) {
                echo $e->getMessage();
            }
        }
    }

    /**
     * Parse through the WSDL and get our Action definitions
     *
     * @throws \Exception
     * @return void
     */
    protected function parseWSDL()
    {
        $definitions = $this->sxe->xpath('/*');
        if (!is_array($definitions) ||
            count($definitions) === 0 ||
            !($definitions[0] instanceof \SimpleXMLElement) ||
            $definitions[0]->getName() !== 'definitions')
        {
            throw new \Exception('Definitions element not found in source');
        }

        /** @var $definitions \SimpleXMLElement */
        $definitions = $definitions[0];
        $attributes = $definitions->attributes();
        if (isset($attributes->targetNamespace) && $attributes->targetNamespace instanceof \SimpleXMLElement)
            $this->targetNamespace = (string)$attributes->targetNamespace;

        // Get all of the Action and Response elements
        $elements = $this->sxe->xpath('wsdl:types/s:schema/s:element');
        if (!is_array($elements) ||
            count($elements) === 0 ||
            !($elements[0] instanceof \SimpleXMLElement))
            throw new \Exception('No Action definitions found in source');

        $actions = array();
        foreach($elements as $element)
        {
            /** @var $element \SimpleXMLElement */
            /** @var $attributes \SimpleXMLElement */
            $attributes = $element->attributes();
            $name = (string)$attributes->name;
            if (stristr($name, 'response'))
            {
                $actionName = substr($name, 0, -8);
                if (array_key_exists($actionName, $actions))
                    $actions[$actionName]['response'] = $element;
                else
                    $actions[$actionName] = array('response' => $element);
            }
            else
            {
                if (array_key_exists($name, $actions))
                    $actions[$name]['action'] = $element;
                else
                    $actions[$name] = array('action' => $element);
            }
        }

        // Populate the actions on this WSDL
        foreach($actions as $name=>$data)
        {
            $action = (isset($data['action']) ? $data['action'] : null);
            $response = (isset($data['response']) ? $data['response'] : null);
            $this->actions[$name] = new ActionBase($name, $action, $response);
        }

        $this->namespaces = $this->sxe->getDocNamespaces(true);

        ksort($this->actions, SORT_STRING);
        ksort($this->namespaces, SORT_STRING);
    }

    /**
     * "Reset" this WSDL
     *
     * @return void
     */
    public function _reset()
    {
        foreach($this->actions as $name=>$action)
        {
            /** @var $action ActionBase */
            $action->_reset();
        }
    }
}