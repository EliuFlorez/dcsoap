<?php namespace DCarbone\DCSOAP\Result;

/**
 * Class GetListItemsResponse
 * @package DCarbone\DCSOAP\Response
 */
class GetListItemsResponse extends ResultBase
{
    /**
     * @var int
     */
    protected $itemCount = 0;

    protected function parse()
    {
        try {
            /** @var $data \SimpleXMLElement */
            $data = $this->soapBody->GetListItemsResponse->GetListItemsResult->listitems->children('rs', true);
        }
        catch(\Exception $e) {
            parent::parse();
            return null;
        }

        // Get the ItemCount value
        $this->itemCount = (int)$data->attributes()->ItemCount;

        // Parse through the ListItems
        foreach($data->children('z', true) as $child)
        {
            /** @var $child \SimpleXMLElement */
            $childArray = array();
            foreach($child->attributes() as $attribute)
            {
                /** @var $attribute \SimpleXMLElement */
                $name = $this->cleanupName($attribute->getName());
                $value = $this->cleanupValue((string)$attribute);
                if ($name !== null && $name !== "" && $value !== null && $value !== '')
                    $childArray[$name] = $value;
            }
            $this->data[] = $childArray;
        }
    }
}