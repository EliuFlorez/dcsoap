<?php namespace DCarbone\DCSOAP\WSDL\Actions\Fields;

abstract class AbstractField
{
    /**
     * @var int
     */
    protected $maxOccurs = 1;

    /**
     * @var int
     */
    protected $minOccurs = 0;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var array
     */
    protected $values = array();

    /**
     * @var \SimpleXMLElement
     */
    protected $sxe;

    /**
     * Constructor
     *
     * @param \SimpleXMLElement $sxe
     */
    public function __construct(\SimpleXMLElement $sxe)
    {
        $this->sxe = $sxe;
        $attributes = $sxe->attributes();
        if ($attributes instanceof \SimpleXMLElement)
        {
            if (isset($attributes->maxOccurs))
                $this->maxOccurs = (int)$attributes->maxOccurs;

            if (isset($attributes->minOccurs))
                $this->minOccurs = (int)$attributes->minOccurs;

            if (isset($attributes->name))
                $this->name = (string)$attributes->name;
        }

        $this->parseField();
    }

    /**
     * Parse the field element
     *
     * @return mixed
     */
    protected function parseField()
    {
        // In most cases, no additional parsing is needed
    }

    /**
     * Set the value of this field
     *
     * For use with MaxOccur of 1
     *
     * @param $value
     * @return mixed
     */
    public function addValue($value)
    {
        if (is_string($value))
            $value = trim($value);

        $this->validateValue($value);

        if ($this->maxOccurs === 1)
            $this->values[0] = $value;
        else if ($this->maxOccurs > 1)
            $this->values[] = $value;

        return $this;
    }

    /**
     * Validate the value the user passes in
     *
     * @param $value
     * @return bool
     */
    abstract public function validateValue($value);

    /**
     * "Reset" this field
     *
     * @return void
     */
    public function _reset()
    {
        $this->values = array();
    }

    /**
     * Get the value of this field
     *
     * @return mixed
     */
    public function getValues()
    {
        return $this->values;
    }

    /**
     * Get the maximum number of times this field can exist in a query
     *
     * @return int
     */
    public function getMaxOccurs()
    {
        return $this->maxOccurs;
    }

    /**
     * Get the minimum number of times this field can exist in a query
     *
     * @return int
     */
    public function getMinOccurs()
    {
        return $this->minOccurs;
    }

    /**
     * @param \SimpleXMLElement $actionBody
     * @param $nsPrefix
     * @param $nsPath
     */
    public function getFieldOutput(\SimpleXMLElement &$actionBody, $nsPrefix, $nsPath)
    {
        foreach($this->values as $value)
        {
            $actionBody->addChild(
                "{$nsPrefix}:{$this->name}",
                $value,
                $nsPath
            );
        }
    }
}