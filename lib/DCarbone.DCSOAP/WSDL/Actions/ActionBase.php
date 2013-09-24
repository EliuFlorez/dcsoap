<?php namespace DCarbone\DCSOAP\WSDL\Actions;

use DCarbone\DCSOAP\WSDL\Actions\Fields\AbstractField;
use DCarbone\DCSOAP\WSDL\Actions\Fields\IntField;
use DCarbone\DCSOAP\WSDL\Actions\Fields\SharePoint\QueryField;
use DCarbone\DCSOAP\WSDL\Actions\Fields\SharePoint\QueryOptionsField;
use DCarbone\DCSOAP\WSDL\Actions\Fields\SharePoint\ViewFieldsField;
use DCarbone\DCSOAP\WSDL\Actions\Fields\StringField;
use DCarbone\DCSOAP\WSDL\Actions\Fields\CustomField;
use DCarbone\DCSOAP\WSDL\Actions\Fields\ComplexField;
use DCarbone\DCSOAP\WSDL\Actions\Response\ActionResponseBase;

/**
 * Class ActionBase
 * @package DCarbone\DCSOAP\WSDL\Actions
 */
class ActionBase
{
    /**
     * @var string
     */
    protected $name;

    /**
     * @var array
     */
    protected $fields = array();

    /**
     * @var ActionResponseBase
     */
    protected $actionResponse;

    /**
     * @var \SimpleXMLElement
     */
    protected $sxe;

    /**
     * Constructor
     *
     * @param $name
     * @param \SimpleXMLElement $action
     * @param \SimpleXMLElement $response
     */
    public function __construct(
        $name,
        \SimpleXMLElement $action = null,
        \SimpleXMLElement $response = null)
    {
        $this->name = $name;
        if ($action !== null)
        {
            $this->sxe = $action;
            $this->parseAction();
        }

        $this->actionResponse = new ActionResponseBase($response);
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

    /**
     * Get the Action Response for this Action
     *
     * @return ActionResponseBase
     */
    public function getActionResponse()
    {
        return $this->actionResponse;
    }

    /**
     * Set a value on a field on this action
     *
     * @param $fieldName
     * @param $value
     * @throws \InvalidArgumentException
     */
    public function setFieldValue($fieldName, $value)
    {
        $field = $this->getField($fieldName);

        if ($field === null)
            throw new \InvalidArgumentException("No field with name '{$fieldName}' found on action '".$this->name."'");

        $this->fields[$fieldName]->addValue($value);
    }

    /**
     * Parse the action
     *
     * @return void
     */
    protected function parseAction()
    {
        $attributes = $this->sxe->attributes();
        $this->name = (string)$attributes->name;

        // Get an array of SimpleXMLElement objects representing the fields
        // on this action
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

                if ($fieldType !== null)
                {
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
                    }
                }
                else
                {
                    switch($fieldName)
                    {
                        case 'query' :
                            $this->fields[$fieldName] = new QueryField($fieldElement);
                            break;
                        case 'queryOptions' :
                            $this->fields[$fieldName] = new QueryOptionsField($fieldElement);
                            break;
                        case 'viewFields' :
                            $this->fields[$fieldName] = new ViewFieldsField($fieldElement);
                            break;
                        default :
                            if (count($children) > 0)
                                $this->fields[$fieldName] = new ComplexField($fieldElement);
                            else
                                $this->fields[$fieldName] = new CustomField($fieldElement);
                    }
                }
            }
        }

        ksort($this->fields, SORT_STRING);
    }

    /**
     * Get the request output
     *
     * @param \SimpleXMLElement $soapBody
     * @param $nsPrefix
     * @param $nsPath
     * @return void
     */
    public function getActionOutput(\SimpleXMLElement &$soapBody, $nsPrefix, $nsPath)
    {
        $actionBody = $soapBody->addChild(
            "{$nsPrefix}:{$this->name}",
            null,
            $nsPath
        );

        foreach($this->fields as $fName=>$field)
        {
            /** @var $field AbstractField */
            $field->getFieldOutput($actionBody, $nsPrefix, $nsPath);
        }
    }

    /**
     * Reset this action's fields to empty
     *
     * @return void
     */
    public function _reset()
    {
        foreach($this->fields as $name=>$field)
        {
            /** @var $field AbstractField */
            $field->_reset();
        }
    }
}