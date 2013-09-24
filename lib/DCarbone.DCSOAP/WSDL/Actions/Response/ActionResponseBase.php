<?php namespace DCarbone\DCSOAP\WSDL\Actions\Response;

use DCarbone\DCSOAP\WSDL\Actions\Fields\AbstractField;
use DCarbone\DCSOAP\WSDL\Actions\Fields\CustomField;
use DCarbone\DCSOAP\WSDL\Actions\Fields\IntField;
use DCarbone\DCSOAP\WSDL\Actions\Fields\StringField;
use DCarbone\DCSOAP\WSDL\Actions\Fields\ComplexField;

/**
 * Class ActionResponseBase
 * @package DCarbone\DCSOAP\WSDL\Actions\Response
 */
class ActionResponseBase
{
    /**
     * @var \SimpleXMLElement
     */
    protected $sxe;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var array
     */
    protected $fields = array();

    /**
     * Constructor
     *
     * @param \SimpleXMLElement $response
     */
    public function __construct(\SimpleXMLElement $response = null)
    {
        if ($response !== null)
        {
            $this->sxe = $response;
            $this->parseResponse();
        }
    }

    /**
     * Parse response
     *
     * @return void
     */
    protected function parseResponse()
    {
        $attributes = $this->sxe->attributes();
        $this->name = (string)$attributes->name;

        $fieldElements = $this->sxe->xpath('s:complexType/s:sequence/s:element');

        if (is_array($fieldElements) && count($fieldElements) > 0)
        {
            foreach($fieldElements as $fieldElement)
            {
                /** @var $fieldElement \SimpleXMLElement */
                /** @var $attributes \SimpleXMLElement */
                $attributes = $fieldElement->attributes();

                $fieldName = (string)$attributes->name;
                $fieldType = (isset($attributes->type) ? (string)$attributes->type : null);
                $children = $fieldElement->children();

                switch($fieldType)
                {
                    case 's:string' :
                        $this->fields[$fieldName] = new StringField($fieldElement);
                        break;
                    case 's:int' :
                        $this->fields[$fieldName] = new IntField($fieldElement);
                        break;
                    default :
                        if (count($children) > 0)
                            $this->fields[$fieldName] = new ComplexField($fieldElement);
                        else
                            $this->fields[$fieldName] = new CustomField($fieldElement);

                        break;
                }
            }
        }

        ksort($this->fields, SORT_STRING);
    }

    /**
     * Get the name of this action
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Get all the fields on this action
     *
     * @return array
     */
    public function getFields()
    {
        return $this->fields;
    }

    /**
     * Get a list of all the field names on this action
     *
     * @return array
     */
    public function getFieldList()
    {
        return array_keys($this->fields);
    }

    /**
     * Get a specific action field on this action
     *
     * @param $fieldName
     * @return AbstractField|null
     */
    public function getField($fieldName)
    {
        return (array_key_exists($fieldName, $this->fields) ? $this->fields[$fieldName] : null);
    }
}