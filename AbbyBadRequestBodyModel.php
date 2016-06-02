<?php

/**
 * Created by PhpStorm.
 * User: bigdrop
 * Date: 4/11/16
 * Time: 4:59 PM
 */
class AbbyBadRequestBodyModel extends AbbyErrorModel
{
    public $model_state;
    /**
     * @param int $status
     * @param null|string $response
     */
    public function __construct( $status,$response)
    {
        parent::__construct( $status, $response);
    }
}