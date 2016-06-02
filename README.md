# yii-perevedem-api-sdk
perevedem.ru API SDK

## Requirements

PHP 5.3 or above


## Links

1. Rerevedem.ru : (https://perevedem.ru/),

## Getting started

### Installation

Insert in config 
```php
    'components'=>[
        'perevod'=>[
            'class'=>'ext.perevodem.AbbyRestClient',
            'applicationId'=>'######',
            'password'=>'####'
        ],
        ...
```

### Usage
 
 Yii::app()->perevod->getAllOrders()