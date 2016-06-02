<?php

/**
 * Created by PhpStorm.
 * User: bigdrop
 * Date: 4/11/16
 * Time: 4:58 PM
 */
class AbbyErrorModel extends CHttpException
{
    public $request_id;
    public $error;
    public $error_description;

    /**
     * @param int $status
     * @param null|string $response
     */
    public function __construct( $status, $response )
    {

        if(is_object($response)){
            $response = (array)$response;
        }

        if(is_array($response))
        {
            foreach($response as $key=>$value)
                $this->$key=$value;
        }

        parent::__construct($status, $this->error_description );
    }
}