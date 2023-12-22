<?php

namespace yii1tech\model\typecast\test;

use ArrayObject;
use DateTime;
use yii1tech\model\typecast\AttributeTypecastBehavior;
use yii1tech\model\typecast\test\data\FormWithTypecast;
use yii1tech\model\typecast\test\data\Item;
use yii1tech\model\typecast\test\data\ItemWithTypecast;

class AttributeTypecastBehaviorTest extends TestCase
{
    public function testTypecast(): void
    {
        $model = new ItemWithTypecast();

        $model->name = 123;
        $model->category_id = '58';
        $model->price = '100.8';
        $model->is_active = 1;
        $model->callback = 'foo';

        $model->typecastAttributes();

        $this->assertSame('123', $model->name);
        $this->assertSame(58, $model->category_id);
        $this->assertSame(100.8, $model->price);
        $this->assertTrue($model->is_active);
        $this->assertSame('callback: foo', $model->callback);
    }

    /**
     * @depends testTypecast
     */
    public function testSkipNull()
    {
        $model = new ItemWithTypecast();
        $model->skipOnNull = true;

        $model->name = null;
        $model->category_id = null;
        $model->price = null;
        $model->is_active = null;
        $model->callback = null;

        $model->typecastAttributes();

        $this->assertNull($model->name);
        $this->assertNull($model->category_id);
        $this->assertNull($model->price);
        $this->assertNull($model->is_active);
        $this->assertNull($model->callback);

        $model->skipOnNull = false;
        $model->typecastAttributes();

        $this->assertSame('', $model->name);
        $this->assertSame(0, $model->category_id);
        $this->assertSame(0.0, $model->price);
        $this->assertFalse($model->is_active);
        $this->assertSame('callback: ', $model->callback);
    }

    /**
     * @depends testTypecast
     */
    public function testAfterFindEvent(): void
    {
        $model = new ItemWithTypecast();

        $model->validate();
        $model->save(false);

        $model->updateAll(['callback' => 'find']);
        $model->refresh();
        $this->assertSame('callback: find', $model->callback);
    }

    /**
     * @depends testTypecast
     */
    public function testAfterValidateEvent(): void
    {
        $model = new ItemWithTypecast();

        $model->callback = 'validate';
        $model->validate();
        $this->assertSame('callback: validate', $model->callback);
    }

    /**
     * @depends testTypecast
     */
    public function testSaveEvents()
    {
        $baseBehavior = new AttributeTypecastBehavior();
        $baseBehavior->attributeTypes = [
            'callback' => function ($value) {
                return 'callback: ' . $value;
            },
        ];

        $model = new Item();
        $behavior = clone $baseBehavior;
        $behavior->typecastBeforeSave = true;
        $behavior->typecastAfterSave = false;
        $model->attachBehavior('typecast', $behavior);
        $model->callback = 'before save';
        $model->save(false);
        $this->assertSame('callback: before save', $model->callback);

        $model = new Item();
        $behavior = clone $baseBehavior;
        $behavior->typecastBeforeSave = false;
        $behavior->typecastAfterSave = true;
        $model->attachBehavior('typecast', $behavior);
        $model->callback = 'after save';
        $model->save(false);
        $this->assertSame('callback: after save', $model->callback);

        $model = new Item();
        $behavior = clone $baseBehavior;
        $behavior->typecastBeforeSave = false;
        $behavior->typecastAfterSave = false;
        $model->attachBehavior('typecast', $behavior);
        $model->callback = 'no typecast';
        $model->save(false);
        $this->assertSame('no typecast', $model->callback);
    }

    /**
     * @depends testSkipNull
     */
    public function testSkipNotSelectedAttribute()
    {
        $model = new ItemWithTypecast();
        $model->name = 'skip-not-selected';
        $model->category_id = '58';
        $model->price = '100.8';
        $model->is_active = 1;
        $model->callback = 'foo';
        $model->save(false);

        /* @var $model ItemWithTypecast */
        $model = ItemWithTypecast::model()->find([
            'select' => ['id', 'name'],
            'condition' => "id = {$model->id}",
        ]);

        $model->typecastAttributes();
        $model->save(false);

        $model->refresh();
        $this->assertSame(58, $model->category_id);
    }

    /**
     * @depends testTypecast
     */
    public function testDateTime(): void
    {
        $createdDateTime = new DateTime('yesterday');

        $model = new ItemWithTypecast();
        $model->created_date = $createdDateTime;
        $model->created_timestamp = $createdDateTime;
        $model->save(false);

        $this->assertSame($createdDateTime, $model->created_date);
        $this->assertSame($createdDateTime, $model->created_timestamp);

        $model = ItemWithTypecast::model()->findByPk($model->id);

        $this->assertSame($createdDateTime->getTimestamp(), $model->created_date->getTimestamp());
        $this->assertSame($createdDateTime->getTimestamp(), $model->created_timestamp->getTimestamp());
    }

    /**
     * @depends testTypecast
     */
    public function testArray(): void
    {
        $array = [
            'foo' => 'bar',
        ];

        $model = new ItemWithTypecast();
        $model->data_array = $array;
        $model->save(false);

        $this->assertSame($array, $model->data_array);

        $model = ItemWithTypecast::model()->findByPk($model->id);

        $this->assertSame($array, $model->data_array);
    }

    /**
     * @depends testTypecast
     */
    public function testArrayObject(): void
    {
        $array = [
            'foo' => 'bar',
        ];
        $arrayObject = new ArrayObject($array);

        $model = new ItemWithTypecast();
        $model->data_array_object = $arrayObject;
        $model->save(false);

        $this->assertSame($arrayObject, $model->data_array_object);

        $model = ItemWithTypecast::model()->findByPk($model->id);

        $this->assertNotSame($arrayObject, $model->data_array_object);
        $this->assertSame($arrayObject->getArrayCopy(), $model->data_array_object->getArrayCopy());

        $model = new ItemWithTypecast();
        $model->data_array_object = $array;
        $model->save(false);

        $this->assertSame($array, $model->data_array_object);

        $model = ItemWithTypecast::model()->findByPk($model->id);
        $this->assertSame($array, $model->data_array_object->getArrayCopy());
    }

    /**
     * @depends testTypecast
     */
    public function testDetectedAttributeTypesFromRules(): void
    {
        $model = new FormWithTypecast();
        $model->name = 123;
        $model->amount = '100';
        $model->price = '5.5';
        $model->isAccepted = '1';

        $this->assertTrue($model->validate());
        $this->assertSame('123', $model->name);
        $this->assertSame(100, $model->amount);
        $this->assertSame(5.5, $model->price);
        $this->assertSame(true, $model->isAccepted);
    }
}