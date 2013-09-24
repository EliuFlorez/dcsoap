<?php namespace DCarbone\DCSOAP\Query;

use DCarbone\DCSOAP\Result\ResultBase;
use DCarbone\DCSOAP\WSDL\Actions\ActionBase;
use DCarbone\DCSOAP\WSDL\WSDLBase;

/**
 * Class QueryBase
 * @package DCarbone\DCSOAP\Query
 */
class QueryBase
{
    /**
     * @var WSDLBase
     */
    protected $wsdl;

    /**
     * @var ActionBase
     */
    protected $action;

    /**
     * @var string
     */
    protected $serviceUri;

    /**
     * @var string
     */
    protected $user;

    /**
     * @var string
     */
    protected $password;

    /**
     * @var string
     */
    protected $authType;

    /**
     * @var string
     */
    protected $soapAction;

    /**
     * @var \SimpleXMLElement
     */
    protected $sxe;

    /**
     * @var array
     */
    protected $config;

    /**
     * @var string
     */
    protected $defaultResultClass;

    /**
     * Constructor
     *
     * @param WSDLBase $wsdl
     * @param $serviceUri
     * @param $authType
     * @param $user
     * @param $password
     * @param array $config
     */
    public function __construct(
        WSDLBase $wsdl,
        $serviceUri,
        $authType,
        $user,
        $password,
        Array $config = array())
    {
        $this->wsdl = $wsdl;
        $this->serviceUri = $serviceUri;
        $this->authType = $authType;
        $this->user = $user;
        $this->password = $password;
        $this->config = $this->parseConfig($config);
    }

    /**
     * Parse query configuration array
     *
     * @param array $config
     * @return array
     */
    protected function parseConfig(Array $config)
    {
        $conf = array();
        foreach($config as $param=>$value)
        {
            switch($param)
            {
                case 'defaultResultClass' :
                    if (is_string($value))
                        $this->defaultResultClass = $value;
                    else
                        trigger_error('defaultResultClass value must be a FQ class name string');
                    break;
                case 'customResultClasses' :
                    if (is_array($value))
                    {
                        $conf['customResultClasses'] = array();
                        foreach($value as $k=>$val)
                        {
                            if (is_int($k))
                                trigger_error('customResultClasses must have a string key');
                            else if (!is_string($val))
                                trigger_error('customResultClasses values mst be FQ class name strings');
                            else
                                $conf['customResultClasses'][$k] = $val;
                        }
                    }
                    break;
            }
        }
        return $conf;
    }

    /**
     * Get the SOAP Action being used
     *
     * @return string
     */
    public function getSoapAction()
    {
        return $this->soapAction;
    }

    /**
     * Convenience method
     *
     * @param $actionName
     * @param $xmlString
     * @return void
     */
    public function buildQueryFromXMLString($actionName, $xmlString)
    {
        $actionBody = new \SimpleXMLElement($xmlString);
        $this->buildQueryFromSXE($actionName, $actionBody);
    }

    /**
     * BUILD THAT QUERY
     *
     * @param $actionName
     * @param \SimpleXMLElement $actionSXE
     * @throws \InvalidArgumentException
     */
    public function buildQueryFromSXE($actionName, \SimpleXMLElement $actionSXE)
    {
        // Get the desired action object
        $this->action = $this->wsdl->getAction($actionName);

        if ($this->action === null)
            throw new \InvalidArgumentException("No action with name '{$actionName}' found in WSDL '".$this->wsdl->getWSDLName()."'");

        // First set all of the field values
        foreach($actionSXE as $actionField)
        {
            /** @var $actionField \SimpleXMLElement */
            /** @var $childNodes \SimpleXMLElement */
            $childNodes = $actionField->children();

            $fieldName = $actionField->getName();

            if (count($childNodes) > 0)
                $this->action->setFieldValue($fieldName, $childNodes);
            else
                $this->action->setFieldValue($fieldName, (string)$actionField);
        }

        $xmlString = <<<XML
<?xml version="1.0" encoding="UTF-8"?><SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ns1="{$this->wsdl->getTargetNameSpace()}"><SOAP-ENV:Body/></SOAP-ENV:Envelope>
XML;

        $this->sxe = new \SimpleXMLElement($xmlString);

        /** @var \SimpleXMLElement $soapBody */
        $soapBody = $this->sxe->xpath('SOAP-ENV:Body');

        $this->action->getActionOutput($soapBody[0], 'ns1', $this->wsdl->getTargetNamespace());

        $targetNamespace = $this->wsdl->getTargetNamespace();

        if (substr($targetNamespace, -1) !== '/')
            $this->soapAction = $targetNamespace.'/'.$this->action->getName();
        else
            $this->soapAction = $targetNamespace.$this->action->getName();
    }

    /**
     * Execute the query
     *
     * @return ResultBase
     * @throws \InvalidArgumentException
     */
    public function execute()
    {
        $postBody = $this->sxe->asXML();

        $ch = curl_init($this->serviceUri);

        $headers = array(
            'Content-type: text/xml;charset="utf-8"',
            'Accept: text/xml',
            'Cache-Control: no-cache',
            'Pragma: no-cache',
            'SOAPAction: "'.$this->soapAction.'"',
            'Content-length: '.strlen($postBody),
        );

        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSL_VERIFYPEER => 0,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => $postBody
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

        $result = curl_exec($ch);
        $info = curl_getinfo($ch);
        $error = curl_error($ch);
        curl_close($ch);

        return $this->parseResults($result, $info, $error);
    }

    /**
     * Create and return the result object
     *
     * @param $result
     * @param $info
     * @param $error
     * @return ResultBase
     */
    protected function parseResults($result, $info, $error)
    {
        $actionResponseName = $this->action->getActionResponse()->getName();
        if ($actionResponseName !== null)
        {
            if (isset($this->config['customResultClasses']) &&
                array_key_exists($actionResponseName, $this->config['customResultClasses']))
            {
                $class = $this->config['customResultClasses'][$actionResponseName];
                return new $class($this->action, $result, $info, $error);
            }

            $class = 'DCarbone\DCSOAP\Result\\'.$actionResponseName;
            if (class_exists($class, true))
                return new $class($this->action, $result, $info, $error);
        }

        if (isset($this->defaultResultClass))
            return new $this->defaultResultClass($this->action, $result, $info, $error);
        else
            return new ResultBase($this->action, $result, $info, $error);
    }
}