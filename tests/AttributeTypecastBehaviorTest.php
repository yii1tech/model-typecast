<?php

namespace yii1tech\model\typecast\test;

use yii1tech\model\typecast\AttributeTypecastBehavior;
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
}