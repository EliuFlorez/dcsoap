<?php namespace DCarbone\DCSOAP\Fault;
use RecursiveIterator;

/**
 * Class FaultBase
 * @package DCarbone\DCSOAP\Fault
 */
class FaultBase implements \Countable, \RecursiveIterator, \SeekableIterator, \ArrayAccess, \Serializable
{
    /**
     * @var \SimpleXMLElement
     */
    protected $sxe;

    /**
     * @var array
     */
    protected $faultData = array();

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
     * @param \SimpleXMLElement $faultElement
     */
    public function __construct(\SimpleXMLElement $faultElement)
    {
        $this->sxe = $faultElement;

        $this->parse();

        $this->positionKeys = array_keys($this->data);
        $this->position = reset($this->positionKeys);
    }

    /**
     * Magic getter method
     *
     * @param $parameter
     * @return string
     */
    public function __get($parameter)
    {
        if (array_key_exists($parameter, $this->faultData))
        {
            if ($this->faultData[$parameter] instanceof \SimpleXMLElement)
                return htmlspecialchars($this->faultData[$parameter]->asXML());
            else
                return $this->faultData[$parameter];
        }
        else
        {
            trigger_error('Undefined property: '.get_class($this).'::$'.$parameter);
        }
    }

    /**
     * Parse the SOAP Fault Element
     *
     * For more information, see here:
     * @link http://www.w3.org/TR/2000/NOTE-SOAP-20000508/#_Toc478383507
     * @link http://technet.microsoft.com/en-us/library/ms189538(v=sql.105).aspx
     * @link http://www.w3schools.com/soap/soap_fault.asp
     *
     * @return void
     */
    protected function parse()
    {
        foreach($this->sxe->children() as $child)
        {
            /** @var $child \SimpleXMLElement */
            $innerData = $child->children();
            if (count($innerData) > 0)
            {
                $this->faultData[$child->getName()] = $child;
            }
            else
            {
                $this->faultData[$child->getName()] = (string)$child;
            }
        }
    }

    /**
     * Get all of the elements defined in the SOAP Fault response
     *
     * @return array
     */
    public function getFaultProperties()
    {
        return $this->faultData;
    }

    /**
     * Get the soap fault element as xml
     *
     * @return string
     */
    public function getFaultXML()
    {
        return htmlspecialchars($this->sxe->asXML());
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Return the current element
     * @link http://php.net/manual/en/iterator.current.php
     * @return mixed Can return any type.
     */
    public function current()
    {
        return $this->faultData[$this->position];
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
        return isset($this->faultData[$this->position]);
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
            return $this->faultData[$offset];
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
        // Don't allow this
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Offset to unset
     * @link http://php.net/manual/en/arrayaccess.offsetunset.php
     * @param mixed $offset <p>
     * The offset to unset.
     * </p>
     * @return void
     */
    public function offsetUnset($offset)
    {
        // Don't allow this
    }

    /**
     * (PHP 5 &gt;= 5.1.0)<br/>
     * Returns if an iterator can be created for the current entry.
     * @link http://php.net/manual/en/recursiveiterator.haschildren.php
     * @return bool true if the current entry can be iterated over, otherwise returns false.
     */
    public function hasChildren()
    {
        return is_array($this->faultData[$this->position]);
    }

    /**
     * (PHP 5 &gt;= 5.1.0)<br/>
     * Returns an iterator for the current entry.
     * @link http://php.net/manual/en/recursiveiterator.getchildren.php
     * @return RecursiveIterator An iterator for the current entry.
     */
    public function getChildren()
    {
        return $this->faultData[$this->position];
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
        return count($this->faultData);
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
     * (PHP 5 &gt;= 5.1.0)<br/>
     * String representation of object
     * @link http://php.net/manual/en/serializable.serialize.php
     * @return string the string representation of the object or null
     */
    public function serialize()
    {
        return serialize($this->faultData);
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
        $this->faultData = unserialize($serialized);
        $this->positionKeys = array_keys($this->faultData);
        $this->position = reset($this->positionKeys);
    }
}