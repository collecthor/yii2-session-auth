<?php

declare(strict_types=1);

namespace Collecthor\Yii2SessionAuth;

use Closure;
use yii\base\Security;
use yii\filters\auth\AuthInterface;
use yii\web\BadRequestHttpException;
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
        private readonly Security $security,
        private readonly Session $session,
        private readonly null|Closure $afterLogin = null
    ) {
    }

    /**
     * This is a re-implementation of Yii's default implementation.
     * It explicitly prevents any regeneration.
     * @return bool whether to continue with authorization
     */
    private function validateCSRFToken(Request $request, array $sessionData, Response $response): bool
    {
        $clientSuppliedToken = $request->getCsrfTokenFromHeader();

        if ($request->getIsGet() || $request->getIsHead() || $request->getIsOptions()) {
            return true;
        }
        /**
         * @psalm-suppress DocblockTypeContradiction
         */
        if (! isset($clientSuppliedToken)) {
            return false;
        }

        /** @var string|null $token */
        $token = $request->enableCsrfCookie
            ? $request->getCookies()->getValue($request->csrfParam)
            : $sessionData[$request->csrfParam] ?? null;

        if (isset($token) && hash_equals($token, $this->security->unmaskToken($clientSuppliedToken))) {
            return true;
        }
        $this->handleFailure($response);
    }

    /**
     * @param User $user
     * @param Request $request
     * @param Response $response
     * @throws UnauthorizedHttpException
     * @throws BadRequestHttpException
     * @psalm-suppress ImplementedReturnTypeMismatch
     */
    public function authenticate($user, $request, $response): IdentityInterface|null
    {
        if ($this->session->getHasSessionId()) {
            $this->session->open();
            $snapshot = iterator_to_array($this->session->getIterator());
            // Abort the session, no need to update it.
            session_abort();

            if (! isset($snapshot[$user->idParam]) || ! (is_string($snapshot[$user->idParam]) || is_int($snapshot[$user->idParam]))) {
                // No user id in the session so this authorization method is not valid applicable.
                return null;
            }

            if (! $this->validateCSRFToken($request, $snapshot, $response)) {
                return null;
            }

            /**
             * @psalm-suppress MixedAssignment
             */
            $userId = $snapshot[$user->idParam];

            // Extract the user from the session.
            $identity = $this->identityFinder->findIdentity($userId);
            // Check if identity is set and login
            if (isset($identity) && $user->login($identity)) {
                // Remove (csrf) cookies that might be set by the login flow
                $response->getCookies()->removeAll();

                if (isset($this->afterLogin)) {
                    ($this->afterLogin)($user, $snapshot);
                }
                return $identity;
            }
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
        throw new UnauthorizedHttpException("You must supply a valid CSRF token");
    }
}
