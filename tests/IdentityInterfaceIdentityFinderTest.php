<?php

declare(strict_types=1);

namespace Collecthor\Yii2SessionAuth\Tests;

use Collecthor\Yii2SessionAuth\IdentityInterfaceIdentityFinder;
use PHPUnit\Framework\TestCase;
use yii\web\IdentityInterface;

/**
 * @covers \Collecthor\Yii2SessionAuth\IdentityInterfaceIdentityFinder
 */
class IdentityInterfaceIdentityFinderTest extends TestCase
{
    public function testFindIdentity(): void
    {
        $class = new class() implements IdentityInterface {
            public static array $calls = [];

            public static function findIdentity($id)
            {
                self::$calls[] = $id;
            }

            public static function findIdentityByAccessToken($token, $type = null)
            {
                throw new \Exception();
            }

            public function getId()
            {
                throw new \Exception();
            }

            public function getAuthKey()
            {
                throw new \Exception();
            }

            public function validateAuthKey($authKey)
            {
                throw new \Exception();
            }
        };
        $finder = new IdentityInterfaceIdentityFinder(get_class($class));

        $finder->findIdentity(15);
        self::assertSame([15], $class::$calls);
    }
}
