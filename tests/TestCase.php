<?php

namespace yii1tech\model\typecast\test;

use CConsoleApplication;
use CMap;
use Yii;

class TestCase extends \PHPUnit\Framework\TestCase
{
    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->mockApplication();

        $this->setupTestDbData();
    }

    /**
     * {@inheritdoc}
     */
    protected function tearDown(): void
    {
        $this->destroyApplication();

        parent::tearDown();
    }

    /**
     * Populates Yii::app() with a new application
     * The application will be destroyed on tearDown() automatically.
     * @param array $config The application configuration, if needed
     * @param string $appClass name of the application class to create
     */
    protected function mockApplication($config = [], $appClass = CConsoleApplication::class)
    {
        Yii::setApplication(null);

        new $appClass(CMap::mergeArray([
            'id' => 'testapp',
            'basePath' => __DIR__,
            'components' => [
                'db' => [
                    'class' => \CDbConnection::class,
                    'connectionString' => 'sqlite::memory:',
                ],
                'cache' => [
                    'class' => \CDummyCache::class,
                ],
            ],
        ], $config));
    }

    /**
     * Destroys Yii application by setting it to null.
     */
    protected function destroyApplication()
    {
        Yii::setApplication(null);
    }

    /**
     * Setup tables for test ActiveRecord
     */
    protected function setupTestDbData()
    {
        $db = Yii::app()->getDb();

        // Structure :
        $db->createCommand()
            ->createTable('item', [
                'id' => 'pk',
                'category_id' => 'integer',
                'name' => 'string',
                'is_deleted' => 'boolean DEFAULT 0',
                'created_at' => 'integer',
                'created_date' => 'datetime',
            ]);

        // Data :
        $builder = $db->getCommandBuilder();

        $builder->createMultipleInsertCommand('item', [
            [
                'category_id' => 1,
                'name' => 'item1',
                'is_deleted' => 0,
                'created_at' => time(),
                'created_date' => date('Y-m-d H:i:s'),
            ],
            [
                'category_id' => 2,
                'name' => 'item2',
                'is_deleted' => 1,
                'created_at' => time(),
                'created_date' => date('Y-m-d H:i:s'),
            ],
        ])->execute();
    }
}