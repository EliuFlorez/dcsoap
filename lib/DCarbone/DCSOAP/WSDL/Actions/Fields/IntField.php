<?php namespace DCarbone\DCSOAP\WSDL\Actions\Fields;

/**
 * Class IntField
 * @package DCarbone\DCSOAP\WSDL\Actions\Fields
 */
class IntField extends AbstractField
{
    /**
     * Validate value for this field
     *
     * @param $value
     * @return bool
     * @throws \InvalidArgumentException
     */
    public function validateValue($value)
    {
        if ($value instanceof \SimpleXMLElement)
            $value = trim((string)$value);

        if ((is_string($value) && ctype_digit($value)) || (is_int($value) || is_float($value) || is_double($value)))
            return true;
        else
            throw new \InvalidArgumentException("Tried to set non-numeric (int|float|double) value to IntField '".$this->name.'"');
    }
}