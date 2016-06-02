<?php
Yii::import('application.components.abby.*');
/**
 * Created by PhpStorm.
 * User: bigdrop
 * Date: 4/8/16
 * Time: 1:34 PM
 */

/**
 * Class AbbyRequest
 * @property  Curl $_client
 */
class AbbyRestClient extends CComponent
{
    const DATA_FORMAT_XML  = 'XML';
    const DATA_FORMAT_JSON = 'JSON';
    const DATA_FORMAT_PDF  = 'PDF';

    protected  $live;
    
    public  $applicationId;
    public  $password;
    public  $apiVersion = 'v0';
    public  $responseFormat = self::DATA_FORMAT_JSON;
    public  $requestFormat  = self::DATA_FORMAT_JSON;
    public  $url = "https://api.perevedem.ru";
    public  $enableParseResult = true;


    private $_url ;
    private $_response;

    private $_client;
    private $_errors;

    function __construct()
    {
        $this->live = false;
        $this->_url .= (substr($this->url, -1) == '/' ? $this->url : $this->url.'/');
        $this->_url .= $this->apiVersion . '/';

    }

    public function init()
    {
        foreach (['applicationId', 'password'] as $attribute) {
            if ($this->$attribute === null) {
                throw new CException(strtr('"{class}::{attribute}" cannot be empty.', [
                    '{class}' => get_class(),
                    '{attribute}' => '$' . $attribute
                ]));
            }
        }


    }

    /**
     * Returns a S3Client instance
     * @return Curl
     */
    public function getClient()
    {
        if ($this->_client === null) {
            $this->_client = new Curl();
            $this->_client->setOption(CURLOPT_USERPWD,sprintf("%s:%s", $this->applicationId, $this->password),true);;//$this->applicationId.':'.$this->password
            $this->setRequestDataFormat();
        }
        return $this->_client;
    }


    public function send($method,$http_method,$params = []){
        $this->_response = null;
        $allowMethod = $this->getHttpMethods();
        if(in_array($http_method,$allowMethod)) {
            $this->_response = $this->getClient()->$http_method($this->buildIrl($method), $params);
        }
        if($this->enableParseResult) {
            $result = $this->parseResponseData($this->_response);
        }else {
            $result =  $this->_response;
        }
      //  $resource = new AbbyBaseResponse($result);
       // $r = $resource->isSuccess();
        return $result;
    }

    private function buildIrl($method){
       return  $this->_url . $method;
    }
    /**
     * Convert to array
     * @param string $res
     * @throws CHttpException
     * @return array
     */
    public function parseResponseData($res){
        $this->errorCurl();
        switch($this->responseFormat){
            case self::DATA_FORMAT_JSON:
                if(function_exists('json_encode')){
                    $res = json_decode($res,true);
                    $this->parseResponseError($res);

                }else{
                    throw new CHttpException(500,Yii::t('base',"This extension requires that PHP support JSON ."));
                };
                break;
            case self::DATA_FORMAT_XML:
                $xml = new SimpleXMLElement('<root/>');
                array_walk_recursive($test_array, array ($xml, 'addChild'));
                $res = $xml->asXML();
                break;
            case self::DATA_FORMAT_PDF:
                header('Content-Description: File Transfer');
                header('Cache-Control: public');
                header('Content-type: application/pdf');
                header('Content-Disposition: attachment; filename="new.pdf"');
                header('Pragma: public');
                header('Content-Length: '.strlen($res));
                break;
        }
        return $res;
    }

    public function errorCurl()
    {
        if($this->getClient()->getError()) {
            throw new CHttpException($this->getClient()->getStatus(),$this->getClient()->getError());
        }
    }

    /**
     * @param $response
     * @throws CHttpException
     */
    protected function parseResponseError($response){
        $this->_errors = isset($response['error_description'])?$response['error_description']:null;
        $message = isset($response['Message'])?$response['Message']:null;
        switch($this->getClient()->getStatus()) {
            case 204:
                break;
            case  400:
                throw new AbbyBadRequestBodyModel($this->getClient()->getStatus(),$response);
                break;
            case  401:
            case  403:
                throw new CHttpException($this->getClient()->getStatus(),$message );
                break;
            case  404:
                throw new CHttpException($this->getClient()->getStatus(),'File not found.' );
                break;
            case  500:
                throw new AbbyErrorModel($this->getClient()->getStatus(),$response);
                break;

        }

    }

    public function getErrors(){
        return $this->_errors;
    }

    /**
     * set Request format
     * @throws CHttpException
     */
    public function setRequestDataFormat(){
        switch($this->requestFormat){
            case self::DATA_FORMAT_JSON:
                if(function_exists('json_encode')){
                    $this->getClient()->addHeader(['Accept'=> 'application/json']);
                }else{
                    throw new CHttpException(500,Yii::t('Payment.message',"This extension requires that PHP support JSON ."));
                };
                break;
            case self::DATA_FORMAT_XML:
                $this->getClient()->addHeader(['Accept'=> 'application/xml']);
                break;
        }
    }

    protected function getHttpMethods(){
        return ['get','post','delete'];
    }

//================ File ================
    public function getFilesFormats()
    {
        return $this->send('file/formats','get');//;
    }

    public function getFileMetadata($id,$token)
    {
        return $this->send("file/{$id}/{$token}/info",'get');
    }

    public function getAllOrders($params = [])
    {
        if(!isset($params['take'],$params['skip'])){
            $params['skip'] = 0;
            $params['take'] = 10;
        }
        return $this->send("order/all",'get',$params);
    }

    public function getFileDownload($id,$token)
    {
        return $this->buildIrl("file/{$id}/{$token}");
    }

    public function getFileDownloadPdf($id,$token,$params = [])
    {

        if(empty($params)){
            $params['resize'] = 'A4';
        }
        $this->enableParseResult  = false;
        $result = $this->send("file/{$id}/{$token}/pdf",'get',$params);
        $this->enableParseResult  = true;
        header('Content-Description: File Transfer');
        header('Cache-Control: public');
        header('Content-type: application/pdf');
        header('Content-Disposition: attachment; filename="new.pdf"');
        header('Pragma: public');
        header('Content-Length: '.strlen($result));
        echo $result;
        exit;
    }

    public function deleteFile($id,$token)
    {
        return $this->send("file/{$id}/{$token}",'delete');
    }

    public function postFile($fileName){
        $fileName = realpath($fileName);
        $data = [
            'file'=>'@'.$fileName
        ];
        return $this->send("file",'post',$data);
    }


    //todo post file

    //================ Order ================

    public function postOrders($params = [])
    {
        if(empty($params)){
            throw new AbbyBadRequestBodyModel(400, Yii::t('base','Request parameters can not be empty'));
        }
//            {
//              "type": "string",
//              "email": "string",
//              "contact_culture": "string",
//              "contact_utc_offset": "string",
//              "label": "string", //max 89
//              "approval_required": true,
//              "cost_type": "Default", //"Default", "SomeDiscounts", "AllDiscounts"
//              "unit_type": "Chars",
//              "currency": "string",
//              "from": "string",
//              "to": [
//                        "string"
//                    ],
//              "files":
//                  [bannedip
//                      {
//                      "id": "string",
//                      "token": "string"
//                      }
//                  ]
//            }

        return $this->send('order','post');
    }

    public function getOrderAll($params = [])
    {
        if(empty($params)){
            $params['skip'] = 0;
            $params['take'] = 10;
        }
        return $this->send('order/all','get',$params);
    }

    public function getOrder($id)
    {
        return $this->send("order/{$id}",'get');
    }

    public function deleteOrder($id)
    {
        return $this->send("order/{$id}",'delete');
    }

    public function postOrderQuotes($params = [])
    {
        if(empty($params)){
            throw new AbbyBadRequestBodyModel(400, Yii::t('base','Request parameters can not be empty'));
        }
//            {
//              "cost_type": "Default", //"Default", "SomeDiscounts", "AllDiscounts"
//              "unit_type": "Chars",  //"Default", "SomeDiscounts", "AllDiscounts"
//              "currency": "string",
//              "from": "string",
//              "to": [
//                        "string"
//                    ],
//              "files":
//                  [
//                      {
//                          "id": "string",
//                          "token": "string"
//                      }
//                  ]
//            }


        return $this->send("order/quotes",'post', $params);
    }
}