<?php
namespace Bolt\Extension\Bacboslab\Menueditor\Event;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\HttpFoundation\ParameterBag;
/**
 * Event fired prior to menueditor's additional fields construction.
 *
 * @author Santino Petrovic <santinopetrovic@gmail.com>
 */
class FieldBuilderEvent extends Event
{
    const BUILD = 'menueditor.fields.construction';
    /** @var array */
    protected $fields;

    /**
     * @return array
     */
    public function getFields()
    {
        if(empty($this->fields)) {
            $this->fields = [];
        }        
        return $this->fields;
    }
    public function setFields($fields)
    {
        $this->fields[] = $fields;
    }    
}