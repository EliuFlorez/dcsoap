<?php namespace DCarbone\DCSOAP\WSDL\Actions\Fields;

/**
 * Class StringField
 * @package DCarbone\DCSOAP\WSDL\Actions\Fields
 */
class StringField extends AbstractField
{
    /**
     * @return mixed|void
     */
    protected function parseField()
    {

    }

    /**
     * Is this field valid?
     *
     * @return bool
     */
    public function valid()
    {
        return is_string($this->values);
    }

    /**
     * Validate value for this field
     *
     * @param string $value
     * @throws \InvalidArgumentException
     * @return bool
     */
    public function validateValue($value)
    {
        if ($value instanceof \SimpleXMLElement)
            $value = trim((string)$value);

        if (is_string($value) && $value !== '')
            return true;
        else
            throw new \InvalidArgumentException("Tried to set non-string or empty string value to StringField '".$this->name.'"');
    }
}