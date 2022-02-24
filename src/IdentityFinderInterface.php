<?php

declare(strict_types=1);

namespace Collecthor\Yii2SessionAuth;

use yii\web\IdentityInterface;

/**
 * This is a sub-interface of Yii's IdentityInterface.
 * The standard IdentityInterface
 * - Has multiple responsibilities
 * - Has defined static functions which actually make it impossible to do clean dependency injection
 */
interface IdentityFinderInterface
{
    /**
     * @param string|int $id
     * @return IdentityInterface|null
     * @see IdentityInterface
     */
    public function findIdentity(string|int $id): IdentityInterface|null;
}
