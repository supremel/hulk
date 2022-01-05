<?php
/**
 * Created by PhpStorm.
 * User: hexuefei
 * Date: 2019-07-30
 * Time: 13:36
 */

namespace App\Events;


class ProdureEvent
{

    public $eventType;
    public $relationId;

    public function __construct($eventType, $relationId)
    {
        $this->eventType = $eventType;
        $this->relationId = $relationId;
    }
}