<p align="center">
    <a href="https://github.com/yii1tech" target="_blank">
        <img src="https://avatars.githubusercontent.com/u/134691944" height="100px">
    </a>
    <h1 align="center">Model Attributes Typecast Extension for Yii 1</h1>
    <br>
</p>

This extension provides support for Yii1 Model and ActiveRecord attributes typecast.

For license information check the [LICENSE](LICENSE.md)-file.

[![Latest Stable Version](https://img.shields.io/packagist/v/yii1tech/model-typecast.svg)](https://packagist.org/packages/yii1tech/model-typecast)
[![Total Downloads](https://img.shields.io/packagist/dt/yii1tech/model-typecast.svg)](https://packagist.org/packages/yii1tech/model-typecast)
[![Build Status](https://github.com/yii1tech/model-typecast/workflows/build/badge.svg)](https://github.com/yii1tech/model-typecast/actions)


Installation
------------

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```
php composer.phar require --prefer-dist yii1tech/model-typecast
```

or add

```json
"yii1tech/model-typecast": "*"
```

to the "require" section of your composer.json.


Usage
-----

This extension provides support for Yii1 Model and ActiveRecord automatic attributes typecast.
It is performed via usage of `\yii1tech\model\typecast\TypecastBehavior` behavior.
It should be attached to `\CModel` or `\CActiveRecord` descendant.
For example:

```php
<?php

use yii1tech\model\typecast\TypecastBehavior;

class Item extends CActiveRecord
{
    public function behaviors()
    {
        return [
            'typecastBehavior' => [
                'class' => TypecastBehavior::class,
                'attributeTypes' => [
                    'id' => TypecastBehavior::TYPE_INTEGER,
                    'amount' => TypecastBehavior::TYPE_INTEGER,
                    'price' => TypecastBehavior::TYPE_FLOAT,
                    'is_active' => TypecastBehavior::TYPE_BOOLEAN,
                    'created_at' => TypecastBehavior::TYPE_DATETIME,
                    'json_data' => TypecastBehavior::TYPE_ARRAY_OBJECT,
                ],
                'typecastAfterValidate' => true,
                'typecastBeforeSave' => false,
                'typecastAfterSave' => true,
                'typecastAfterFind' => true,
            ],
        ];
    }

    // ...
}
```

> Tip: you may leave `\yii1tech\model\typecast\TypecastBehavior::$attributeTypes` blank - in this case its value will be detected
automatically: for ActiveRecord - based on owner DB table schema, for regular model - based validation rules.

In the above example attribute typecasting will be automatically performed in following cases:

- after model successful validation
- after model successful saving
- after model retrieval from Database

For example:

```php
<?php

$model = new Item();
$model->setAttributes([
    'name' => 'item name',
    'price' => '10.50',
    'amount' => '14',
    'is_active' => '1',
]);

if ($model->validate()) {
    var_dump($model->price); // outputs: float(10.5)
    var_dump($model->amount); // outputs: int(14)
    var_dump($model->is_active); // outputs: bool(true)
}

$model = Item::model()->findByPk($id);
var_dump($model->id); // outputs: int(12345)
var_dump($model->amount); // outputs: int(18)
var_dump($model->is_active); // outputs: bool(true)
```

You can manually trigger attribute typecasting anytime invoking `\yii1tech\model\typecast\TypecastBehavior::typecastAttributes()` method:

```php
<?php

$model = new Item();
$model->price = '38.5';
$model->is_active = 1;
$model->typecastAttributes();

var_dump($model->price); // outputs: float(38.5)
var_dump($model->is_active); // outputs: bool(true)
```


### JSON typecasting <span id="json-typecasting"></span>

This behavior allows automatic conversion of array or traversable objects into JSON string on model saving.
For example:

```php
<?php

$model = new Item();
$model->json_data = [ // will be saved in DB as '{foo: "bar"}'
    'foo' => 'bar',
];
$model->save();
```

> Note: such conversion will take place even, if there is no direct attribute type specification. 

You can typecast JSON column value either to plain `array` or `\ArrayObject` instance.
Plain arrays consume less memory, but writing of its particular internal keys will not work.
`\ArrayObject` allows free operation over internal keys, but you should note that its value always passed by reference.

```php
<?php

// in case mapping in `attributeTypes` is set to `TypecastBehavior::TYPE_ARRAY`
use yii1tech\model\typecast\TypecastBehavior;

class Item extends CActiveRecord
{
    public function behaviors()
    {
        return [
            'typecastBehavior' => [
                'class' => TypecastBehavior::class,
                'attributeTypes' => [
                    'json_data' => TypecastBehavior::TYPE_ARRAY,
                ],
            ],
        ];
    }

    // ...
}

$model = Item::model()->findByPk($id);
var_dump($model->json_data); // outputs: array(1) {...}
var_dump($model->json_data['foo']); // outputs: string(bar)
$model->json_data['foo'] = 'new value'; // PHP E_NOTICE: Indirect modification of overloaded property Item::$json_data has no effect!
$model->json_data = [ // no problem
    'foo' => 'new value',
];
$model->save();

// in case mapping in `attributeTypes` is set to `TypecastBehavior::TYPE_ARRAY_OBJECT`
use yii1tech\model\typecast\TypecastBehavior;

class Item extends CActiveRecord
{
    public function behaviors()
    {
        return [
            'typecastBehavior' => [
                'class' => TypecastBehavior::class,
                'attributeTypes' => [
                    'json_data' => TypecastBehavior::TYPE_ARRAY_OBJECT,
                ],
            ],
        ];
    }

    // ...
}

$model = Item::model()->findByPk($id);
var_dump($model->json_data); // outputs: object(ArrayObject) {...}
var_dump($model->json_data['foo']); // outputs: string(bar)
$model->json_data['foo'] = 'new value'; // no problem
$jsonDataCopy = $model->json_data; // new variable holds the reference to `\ArrayObject` instance!
$jsonDataCopy['foo'] = 'value from copy'; // changes value of `$model->json_data`!
var_dump($model->json_data['foo']); // outputs: string(value from copy)
$model->save();
```


### DateTime typecasting <span id="datetime-typecasting"></span>

This behavior allows automatic conversion of `\DateTime` instances into ISO datetime string on model saving.
For example:

```php
<?php

use yii1tech\model\typecast\TypecastBehavior;

class Item extends CActiveRecord
{
    public function behaviors()
    {
        return [
            'typecastBehavior' => [
                'class' => TypecastBehavior::class,
                'attributeTypes' => [
                    'created_at' => TypecastBehavior::TYPE_DATETIME,
                ],
            ],
        ];
    }

    // ...
}

$model = new Item();
$model->created_at = new DateTime('now'); // will be saved in DB as '2023-12-22 10:14:17'
$model->save();

$model = Item::model()->findByPk($id);
var_dump($model->created_at); // outputs: object(DateTime)
```

In case you store the dates using integer Unix timestamp, you can use `\yii1tech\model\typecast\TypecastBehavior::TYPE_TIMESTAMP` for correct
conversion. For example:

```php
<?php

use yii1tech\model\typecast\TypecastBehavior;

class Item extends CActiveRecord
{
    public function behaviors()
    {
        return [
            'typecastBehavior' => [
                'class' => TypecastBehavior::class,
                'attributeTypes' => [
                    'created_at' => TypecastBehavior::TYPE_TIMESTAMP,
                ],
            ],
        ];
    }

    // ...
}

$model = new Item();
$model->created_at = new DateTime('now'); // will be saved in DB as '1703257478'
$model->save();

$model = Item::model()->findByPk($id);
var_dump($model->created_at); // outputs: object(DateTime)
```

This extension also supports [nesbot/carbon](https://packagist.org/packages/nesbot/carbon) package.
In order to convert dates to `\Carbon\Carbon` you should use following types:

- `\yii1tech\model\typecast\TypecastBehavior::TYPE_DATETIME_CARBON`
- `\yii1tech\model\typecast\TypecastBehavior::TYPE_TIMESTAMP_CARBON`


### Custom typecasting <span id="custom-typecasting"></span>

You may specify any custom typecasting for the attribute using a callable as a type specification at `\yii1tech\model\typecast\TypecastBehavior::$attributeTypes`.
For example:

```php
<?php

use yii1tech\model\typecast\TypecastBehavior;

class Item extends CActiveRecord
{
    public function behaviors()
    {
        return [
            'typecastBehavior' => [
                'class' => TypecastBehavior::class,
                'attributeTypes' => [
                    'heap_data' => function ($value) {
                        if (is_object($value)) {
                            return $value;
                        }
                        
                        $heap = new \SplMaxHeap();
                        foreach (json_decode($value) as $element) {
                            $heap->insert($element);
                        }
                        
                        return $heap;
                    },
                ],
            ],
        ];
    }

    // ...
}

$model = Item::model()->findByPk($id);
var_dump($model->heap_data); // outputs: object(SplMaxHeap)
```
