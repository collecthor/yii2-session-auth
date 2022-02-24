<?php

declare(strict_types=1);

namespace Collecthor\Yii2SessionAuth;

use Closure;
use yii\filters\auth\AuthInterface;
use yii\web\IdentityInterface;
use yii\web\Request;
use yii\web\Response;
use yii\web\Session;
use yii\web\UnauthorizedHttpException;
use yii\web\User;

class SessionAuth implements AuthInterface
{
    /**
     * @param IdentityFinderInterface $identityFinder
     * @param Session $session
     * @param null|Closure $afterLogin Signature: (User $user, array $sessionSnapshot): void
     */
    public function __construct(
        private readonly IdentityFinderInterface $identityFinder,
        private readonly Session $session,
        private readonly null|Closure $afterLogin = null
    ) {
    }

    /**
     * @param User $user
     * @param Request $request
     * @param Response $response
     * @throws UnauthorizedHttpException
     * @psalm-suppress ImplementedReturnTypeMismatch
     */
    public function authenticate($user, $request, $response): IdentityInterface|null
    {
        if ($this->session->getHasSessionId()) {
            $this->session->open();

            $snapshot = iterator_to_array($this->session->getIterator());
            /**
             * @psalm-suppress MixedAssignment
             */
            $userId = $this->session->get($user->idParam);
            // Abort the session, no need to update it.
            session_abort();

            // Extract the user from the session.
            if (isset($userId) && (is_string($userId) || is_int($userId))) {
                $identity = $this->identityFinder->findIdentity($userId);

                // Check if identity is set and login
                if (isset($identity) && $user->login($identity)) {
                    if (isset($this->afterLogin)) {
                        ($this->afterLogin)($user, $snapshot);
                    }
                    return $identity;
                }
            }

            throw new UnauthorizedHttpException();
        }
        return null;
    }

    /**
     * This authentication has no challenge
     */
    public function challenge($response): void
    {
    }

    public function handleFailure($response): never
    {
        throw new UnauthorizedHttpException();
    }
}
