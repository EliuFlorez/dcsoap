<?php namespace DCarbone\DCSOAP\Result;

use DCarbone\DCSOAP\Fault\FaultBase;
use DCarbone\DCSOAP\WSDL\Actions\ActionBase;
use DCarbone\DCSOAP\WSDL\Actions\Response\ActionResponseBase;
use RecursiveIterator;

/**
 * Class BaseResponse
 * @package DCarbone\DCSOAP\Response
 */
class ResultBase implements \Countable, \RecursiveIterator, \SeekableIterator, \ArrayAccess, \Serializable
{
    /**
     * @var \SimpleXMLElement
     */
    protected $sxe;

    /**
     * @var \SimpleXMLElement
     */
    protected $soapBody;

    /**
     * @var string
     */
    protected $curlResponse;

    /**
     * @var array
     */
    protected $curlInfo;

    /**
     * @var string
     */
    protected $curlError;

    /**
     * @var array
     */
    protected $data = array();

    /**
     * @var ActionBase
     */
    protected $action;

    /**
     * @var ActionResponseBase
     */
    protected $actionResponse;

    /**
     * @var FaultBase
     */
    protected $fault;

    /**
     * @var array
     */
    protected $namespaces = array('' => '');

    /**
     *
     * INTERFACE PROPERTIES
     *
     */

    /**
     * Used by Iterators
     * @var mixed
     */
    protected $position;
    protected $positionKeys = array();
    protected $positionKeysPosition = 0;

    /**
     * Constructor
     *
     * @param ActionBase $action
     * @param $curlResponse
     * @param $curlInfo
     * @param $curlError
     * @return \DCarbone\DCSOAP\Result\ResultBase
     */
    public function __construct(ActionBase $action, $curlResponse, $curlInfo, $curlError)
    {
        $this->action = $action;
        $this->actionResponse = $this->action->getActionResponse();
        $this->curlResponse = $curlResponse;
        $this->curlError = $curlError;
        $this->curlInfo = $curlInfo;

        if ($this->curlError === '' || $this->curlError === false)
        {
            $this->sxe = new \SimpleXMLElement($curlResponse);

            $namespaces = $this->sxe->getNamespaces(true);
            foreach($namespaces as $k=>$v)
            {
                $this->namespaces[$k] = $v;
                $this->sxe->registerXPathNamespace($k, $v);
            }

            $this->_parse();
            $this->positionKeys = array_keys($this->data);
            $this->position = reset($this->positionKeys);
        }
    }

    /**
     * Get the error string created by CURL
     *
     * @return string
     */
    public function getCurlError()
    {
        return $this->curlError;
    }

    /**
     * Get the Info array created by CURL
     *
     * @return array
     */
    public function getCurlInfo()
    {
        return $this->curlInfo;
    }

    /**
     * Get the full XML response as string
     *
     * @return string|null
     */
    public function getResponseXML()
    {
        return (isset($this->sxe) ? $this->sxe->asXML() : null);
    }

    /**
     * Get the Action used to generate this result
     *
     * @return ActionBase
     */
    public function getAction()
    {
        return $this->action;
    }

    /**
     * Get the Action Response associate with the action
     *
     * @return ActionResponseBase|null
     */
    public function getActionResponse()
    {
        return $this->actionResponse;
    }

    /**
     * Get the Fault object of the response, if there is one
     *
     * @return FaultBase|null
     */
    public function getSOAPFault()
    {
        return (isset($this->fault) ? $this->fault : null);
    }

    /**
     * Get the response object
     *
     * @return mixed
     */
    public function getResult()
    {
        return $this->data;
    }

    /**
     * Begin parse process
     *
     * This method should always be called, and should probably not
     * be extended by child classes.  It is set to protected just in
     * case the need arises, however.
     *
     * @throws \Exception
     * @return bool|void
     */
    protected function _parse()
    {
        $soapBody = $this->sxe->xpath('/soap:Envelope/soap:Body');

        if (!(is_array($soapBody) && count($soapBody) > 0))
            throw new \Exception('Unable to parse SOAP Response');

        /** @var $soapBody \SimpleXMLElement */
        $this->soapBody = $soapBody[0];
        $fault = $this->soapBody->xpath('soap:Fault');

        if (count($fault) > 0)
            $this->fault = new FaultBase($fault[0]);
        else
            $this->parse();
    }

    /**
     * Kicks off parsing
     *
     * If you were to have a custom parsing class,
     * this is one of the methods you should override
     *
     * @return void
     */
    protected function parse()
    {
        foreach($this->soapBody->children() as $soapChild)
        {
            /** @var $soapChild \SimpleXMLElement */
            $childName = $soapChild->getName();
            $this->parseChildren($soapChild, $this->data);
        }
    }

    /**
     * Recursively parses child elements in SOAP response
     *
     * If you were to have a custom parsing class,
     * this is one of the methods you should override
     *
     * @param \SimpleXMLElement $element
     * @param array $parentArray
     * @return void
     */
    protected function parseChildren(\SimpleXMLElement &$element, Array &$parentArray)
    {
        // Create new child array to house ALL YOUR AWESOME DATA.
        $newChild = array();
        $newChildName = $element->getName();

        $elementAttributes = $element->attributes();
        if (count($elementAttributes) > 0)
        {
            $attributes = array();
            foreach($elementAttributes as $elementAttribute)
            {
                /** @var $elementAttribute \SimpleXMLElement */
                $name = $this->cleanupName($elementAttribute->getName());
                $value = $this->cleanupValue((string)$elementAttribute);

                if ($value !== null && $value !== "")
                    $attributes[$name] = $value;
            }

            if (count($attributes) > 0)
                $newChild['_attributes'] = $attributes;
        }

        $nodeValue = $this->cleanupValue((string)$element);
        if ($nodeValue !== null && $nodeValue !== "")
            $newChild['_text_value'] = $nodeValue;

        // Parse through any child elements on this child.
        foreach($this->namespaces as $prefix=>$url)
        {
            $children = $element->children($prefix, true);
            if (count($children) > 0)
            {
                foreach($children as $child)
                {
                    /** @var $child \SimpleXMLElement */
                    $this->parseChildren($child, $newChild);
                }
            }
        }

        // Add the new child
        if (array_key_exists($newChildName, $parentArray))
        {
            if (is_int(key($parentArray[$newChildName])))
            {
                $parentArray[$newChildName][] = $newChild;
            }
            else
            {
                $arr = array(
                    $parentArray[$newChildName],
                    $newChild
                );
                $parentArray[$newChildName] = $arr;
            }
        }
        else
        {
            $parentArray[$newChildName] = $newChild;
        }
    }

    /**
     * Replace the SharePoint character keys with the ascii equivalents
     *
     * @param $value
     * @return mixed
     */
    protected function replaceSpecialCharacters($value)
    {
        $keys = array(
            '_x007e_',
            '_x0021_',
            '_x0040_',
            '_x0023_',
            '_x0024_',
            '_x0025_',
            '_x005e_',
            '_x0026_',
            '_x002a_',
            '_x0028_',
            '_x0029_',
            '_x002b_',
            '_x002d_',
            '_x003d_',
            '_x007b_',
            '_x007d_',
            '_x003a_',
            '_x0022_',
            '_x007c_',
            '_x003b_',
            '_x0027_',
            '_x005c_',
            '_x003c_',
            '_x003e_',
            '_x003f_',
            '_x002c_',
            '_x002e_',
            '_x002f_',
            '_x0060_',
            '_x0020_',
            '::',
        );
        $replace = array(
            '~',
            '!',
            '@',
            '#',
            '$',
            '%',
            '^',
            '&',
            '*',
            '(',
            ')',
            '+',
            '-',
            '=',
            '{',
            '}',
            ':',
            '"',
            '|',
            ';',
            '\'',
            '\\',
            '<',
            '>',
            '?',
            ',',
            '.',
            '/',
            '`',
            ' ',
            '',
        );

        $step1 = str_ireplace($keys, $replace, $value);
        return (substr($step1, 0, 4) === 'ows_' ? substr($step1, 4) : $step1);
    }


    /**
     * Cleanup SharePoint Column Name
     *
     * @param $name
     * @return mixed
     */
    protected function cleanupName($name)
    {
        return $this->replaceSpecialCharacters(trim($name));
//        return preg_replace("#^[a-z0-9!]*_*#", '', $this->replaceSpecialCharacters(trim($name)));
    }

    /**
     * Cleanup Value
     *
     * @param $value
     * @return mixed
     */
    protected function cleanupValue($value)
    {
        $search = array(
            "/(\s*;#\d*;#\s*)/i", // Content Authors
            "/(\d*;#)*/i",
            "/(;#\d*)*/i",
            "/^#/"
        );

        $replace = array(
            "|", // Content Authors
            "" // Everything else
        );

        return preg_replace($search, $replace, trim($value));
    }

    /**
     * echo this object!
     *
     * @return string
     */
    public function __toString()
    {
        return get_class($this);
    }

    /**
     * make this object an array!
     *
     * @return array
     */
    public function __toArray()
    {
        return $this->data;
    }

    /**
     * Updates the internal positionKeys value
     *
     * @return void
     */
    protected function updateKeys()
    {
        $this->positionKeys = array_keys($this->data);
    }

    /**
     *
     *
     *
     * INTERFACE IMPLEMENTATIONS
     *
     *
     *
     */

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Return the current element
     * @link http://php.net/manual/en/iterator.current.php
     * @return mixed Can return any type.
     */
    public function current()
    {
        return $this->data[$this->position];
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Move forward to next element
     * @link http://php.net/manual/en/iterator.next.php
     * @return void Any returned value is ignored.
     */
    public function next()
    {
        $this->positionKeysPosition++;
        $this->position = $this->positionKeys[$this->positionKeysPosition];
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Return the key of the current element
     * @link http://php.net/manual/en/iterator.key.php
     * @return mixed scalar on success, or null on failure.
     */
    public function key()
    {
        return $this->position;
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Checks if current position is valid
     * @link http://php.net/manual/en/iterator.valid.php
     * @return boolean The return value will be casted to boolean and then evaluated.
     * Returns true on success or false on failure.
     */
    public function valid()
    {
        return isset($this->data[$this->position]);
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Rewind the Iterator to the first element
     * @link http://php.net/manual/en/iterator.rewind.php
     * @return void Any returned value is ignored.
     */
    public function rewind()
    {
        $this->positionKeysPosition = 0;
        $this->position = $this->positionKeys[$this->positionKeysPosition];
    }

    /**
     * (PHP 5 &gt;= 5.1.0)<br/>
     * Returns if an iterator can be created for the current entry.
     * @link http://php.net/manual/en/recursiveiterator.haschildren.php
     * @return bool true if the current entry can be iterated over, otherwise returns false.
     */
    public function hasChildren()
    {
        return is_array($this->data[$this->position]);
    }

    /**
     * (PHP 5 &gt;= 5.1.0)<br/>
     * Returns an iterator for the current entry.
     * @link http://php.net/manual/en/recursiveiterator.getchildren.php
     * @return RecursiveIterator An iterator for the current entry.
     */
    public function getChildren()
    {
        return $this->data[$this->position];
    }

    /**
     * (PHP 5 &gt;= 5.1.0)<br/>
     * Seeks to a position
     * @link http://php.net/manual/en/seekableiterator.seek.php
     * @param int $position <p>
     * The position to seek to.
     * </p>
     * @throws \OutOfBoundsException
     * @return void
     */
    public function seek($position)
    {
        if (!isset($this->positionKeys[$position]))
            throw new \OutOfBoundsException('Invalid seek position ('.$position.')');

        $this->positionKeysPosition = $position;
        $this->position = $this->positionKeys[$this->positionKeysPosition];
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Whether a offset exists
     * @link http://php.net/manual/en/arrayaccess.offsetexists.php
     * @param mixed $offset <p>
     * An offset to check for.
     * </p>
     * @return boolean true on success or false on failure.
     * </p>
     * <p>
     * The return value will be casted to boolean if non-boolean was returned.
     */
    public function offsetExists($offset)
    {
        return (array_search($offset, $this->positionKeys, true) !== false);
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Offset to retrieve
     * @link http://php.net/manual/en/arrayaccess.offsetget.php
     * @param mixed $offset <p>
     * The offset to retrieve.
     * </p>
     * @return mixed Can return all value types.
     */
    public function offsetGet($offset)
    {
        if ($this->offsetExists($offset))
            return $this->data[$offset];
        else
            return null;
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Offset to set
     * @link http://php.net/manual/en/arrayaccess.offsetset.php
     * @param mixed $offset <p>
     * The offset to assign the value to.
     * </p>
     * @param mixed $value <p>
     * The value to set.
     * </p>
     * @return void
     */
    public function offsetSet($offset, $value)
    {
        if ($offset === null)
            $this->data[] = $value;
        else
            $this->data[$offset] = $value;

        $this->updateKeys();
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Offset to unset
     * @link http://php.net/manual/en/arrayaccess.offsetunset.php
     * @param mixed $offset <p>
     * The offset to unset.
     * </p>
     * @throws \OutOfBoundsException
     * @return void
     */
    public function offsetUnset($offset)
    {
        if ($this->offsetExists($offset))
            unset($this->data[$offset]);
        else
            throw new \OutOfBoundsException('Tried to unset undefined offset ('.$offset.')');

        $this->updateKeys();
    }

    /**
     * (PHP 5 &gt;= 5.1.0)<br/>
     * Count elements of an object
     * @link http://php.net/manual/en/countable.count.php
     * @return int The custom count as an integer.
     * </p>
     * <p>
     * The return value is cast to an integer.
     */
    public function count()
    {
        return count($this->data);
    }

    /**
     * (PHP 5 &gt;= 5.1.0)<br/>
     * String representation of object
     * @link http://php.net/manual/en/serializable.serialize.php
     * @return string the string representation of the object or null
     */
    public function serialize()
    {
        $saved = array(
            'data' => $this->data,
            'fault' => $this->fault
        );
        return serialize($saved);
    }

    /**
     * (PHP 5 &gt;= 5.1.0)<br/>
     * Constructs the object
     * @link http://php.net/manual/en/serializable.unserialize.php
     * @param string $serialized <p>
     * The string representation of the object.
     * </p>
     * @return void
     */
    public function unserialize($serialized)
    {
        $saved = unserialize($serialized);
        $this->data = $saved['data'];
        $this->fault = $saved['fault'];
        $this->positionKeys = array_keys($this->data);
        $this->position = reset($this->positionKeys);
    }
}