<?php

declare(strict_types=1);

namespace Collecthor\Yii2SessionAuth;

use yii\web\IdentityInterface;

class IdentityInterfaceIdentityFinder implements IdentityFinderInterface
{
    /**
     * @param class-string<IdentityInterface> $class
     */
    public function __construct(
        private readonly string $class
    ) {
    }

    public function findIdentity(int|string $id): IdentityInterface|null
    {
        return $this->class::findIdentity($id);
    }
}
