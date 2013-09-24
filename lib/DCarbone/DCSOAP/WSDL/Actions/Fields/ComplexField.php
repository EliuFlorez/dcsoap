<?php namespace DCarbone\DCSOAP\WSDL\Actions\Fields;

/**
 * Class ComplexField
 * @package DCarbone\DCSOAP\WSDL\Actions\Fields
 */
class ComplexField extends AbstractField
{
    /**
     * @TODO Implement this!
     *
     * @return mixed|void
     */
    protected function parseField()
    {

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
        if (!($value instanceof \SimpleXMLElement))
            $value = new \SimpleXMLElement($value);

        if ($this->maxOccurs === 1)
            $this->values[0] = $value;
        else if ($this->maxOccurs > 1)
            $this->values[] = $value;

        return $this;
    }

    /**
     * @TODO Not fully implemented yet
     *
     * @param $value
     * @return bool|void
     */
    public function validateValue($value)
    {
        return true;
    }

    /**
     * Get field output for complex types
     *
     * @param \SimpleXMLElement $actionBody
     * @param $nsPrefix
     * @param $nsPath
     */
    public function getFieldOutput(\SimpleXMLElement &$actionBody, $nsPrefix, $nsPath)
    {
        foreach($this->values as $value)
        {
            $fieldBody = $actionBody->addChild(
                "{$nsPrefix}:{$this->name}",
                null,
                $nsPath
            );

            if (is_string($value))
            {
                $fieldBody[0] = $value;
            }
            else if ($value instanceof \SimpleXMLElement)
            {
                foreach($value as $val)
                {
                    /** @var $val \SimpleXMLElement */

                    $child = $fieldBody->addChild(
                        (string)$val->getName(),
                        null,
                        ""
                    );
                    foreach($val->attributes() as $attribute)
                    {
                        /** @var $attribute \SimpleXMLElement */
                        $child[(string)$attribute->getName()] = (string)$attribute;
                    }
                    $this->parseValueChildren($child, $val);
                }
            }
        }
    }

    /**
     * Parse through all of the child elements within this complex field
     *
     * @param \SimpleXMLElement $outputParent
     * @param \SimpleXMLElement $valueChild
     */
    protected function parseValueChildren(\SimpleXMLElement &$outputParent, \SimpleXMLElement $valueChild)
    {
        $babes = $valueChild->children();
        if (count($babes) === 0)
        {
            $outputParent[0] = (string)$valueChild;
        }
        else
        {
            foreach($babes as $babe)
            {
                /** @var $babe \SimpleXMLElement */
                $child = $outputParent->addChild(
                    $babe->getName()
                );
                foreach($babe->attributes() as $attribute)
                {
                    /** @var $attribute \SimpleXMLElement */
                    $child[0][$attribute->getName()] = (string)$attribute;
                }
                $this->parseValueChildren($child, $babe);
            }
        }
    }

}