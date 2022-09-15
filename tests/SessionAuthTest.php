<?php

declare(strict_types=1);

namespace Collecthor\Yii2SessionAuth\Tests;

use Collecthor\Yii2SessionAuth\IdentityFinderInterface;
use Collecthor\Yii2SessionAuth\SessionAuth;
use PHPUnit\Framework\TestCase;
use yii\base\Security;
use yii\web\Cookie;
use yii\web\CookieCollection;
use yii\web\IdentityInterface;
use yii\web\Request;
use yii\web\Response;
use yii\web\Session;
use yii\web\UnauthorizedHttpException;
use yii\web\User;

/**
 * @covers \Collecthor\Yii2SessionAuth\SessionAuth
 */
class SessionAuthTest extends TestCase
{
    private const ID_PARAM = '__id';

    private const CSRF_PARAM = '_csrf';

    private function getResponse(): Response
    {
        return new Response(['charset' => 'utf-8']);
    }

    private function getUser(IdentityInterface|null $identity = null): User
    {
        $user = $this->getMockBuilder(User::class)->getMock();
        $user->idParam = self::ID_PARAM;

        if (! isset($identity)) {
            $user->expects($this->never())->method('login');
        } else {
            $user->expects($this->once())->method('login')->willReturn(true);
        }

        return $user;
    }

    private function createIdentity(int|string $id): IdentityInterface
    {
        $identity = $this->getMockBuilder(IdentityInterface::class)->getMock();
        $identity->expects($this->any())->method('getId')->willReturn($id);
        return $identity;
    }

    private function getIdentityFinder(IdentityInterface|null $identity = null): IdentityFinderInterface
    {
        $identityFinder = $this->getMockBuilder(IdentityFinderInterface::class)->getMock();
        if (isset($identity)) {
            $identityFinder
                ->expects($this->atLeastOnce())
                ->method('findIdentity')
                ->with($identity->getId())
                ->willReturn($identity);
        } else {
            $identityFinder->expects($this->never())->method('findIdentity');
        }

        return $identityFinder;
    }

    private function getRequest(string|null $csrfTokenHeader = null, string|null $csrfCookie = null, ?bool $enableCsrfCookie = null, string $method = 'GET'): Request
    {
        $security = new Security();
        $request = $this->getMockBuilder(Request::class)->getMock();
        $request->csrfParam = self::CSRF_PARAM;

        $request->expects($this->any())->method('getMethod')->willReturn($method);

        if (isset($csrfTokenHeader)) {
            $maskedToken = $security->maskToken($csrfTokenHeader);
            $request->expects($this->atLeastOnce())->method('getCsrfTokenFromHeader')->willReturn($maskedToken);
        }

        $request->enableCsrfCookie = $enableCsrfCookie ?? isset($csrfCookie);

        if (isset($csrfCookie)) {
            $cookie = new Cookie();
            $cookie->name = self::CSRF_PARAM;
            $cookie->value = $csrfCookie;
            $cookieCollection = new CookieCollection();
            $cookieCollection->add($cookie);
            $request->expects($request->enableCsrfCookie ? $this->atLeastOnce() : $this->never())->method('getCookies')->willReturn($cookieCollection);
        }

        return $request;
    }

    private function getSession(string|int|null $userId = null, string|null $csrfToken = null): Session
    {
        $session = $this->getMockBuilder(Session::class)->getMock();
        $session->expects($this->once())->method('getHasSessionId')->willReturn(isset($userId) || isset($csrfToken));
        $data = [];
        if (isset($userId)) {
            $data[self::ID_PARAM] = $userId;
        }

        if (isset($csrfToken)) {
            $data[self::CSRF_PARAM] = $csrfToken;
        }

        $session->expects(empty($data) ? $this->never() : $this->once())
            ->method('getIterator')
            ->willReturn(new \ArrayIterator($data));

        return $session;
    }

    public function testNoSession(): void
    {
        $security = new Security();

        $session = $this->getSession();

        $identityFinder = $this->getMockBuilder(IdentityFinderInterface::class)->getMock();
        $subject = new SessionAuth($identityFinder, $security, $session);

        $user = $this->getUser();
        self::assertNull($subject->authenticate($user, $this->getRequest(), $this->getResponse()));
    }

    public function testNoCsrfHeader(): void
    {
        $security = new Security();
        $user = $this->getUser();

        $session = $this->getSession(15);

        $identityFinder = $this->getIdentityFinder();

        $subject = new SessionAuth($identityFinder, $security, $session);

        self::assertSame(null, $subject->authenticate($user, $this->getRequest(), $this->getResponse()));
    }

    public function testRandomCsrfHeader(): void
    {
        $security = new Security();
        $user = $this->getUser();

        $session = $this->getSession(15);

        $identityFinder = $this->getIdentityFinder();

        $subject = new SessionAuth($identityFinder, $security, $session);

        $request = $this->getRequest('abc');

        $this->expectException(UnauthorizedHttpException::class);
        $subject->authenticate($user, $request, $this->getResponse());
    }

    /**
     * @dataProvider useCookieAndMethodProvider
     */
    public function testValidCsrfHeader(bool $useCookies, string $method): void
    {
        $security = new Security();
        $identity = $this->createIdentity(15);

        $user = $this->getUser($identity);

        $csrfToken = 'abc';
        $request = $this->getRequest($csrfToken, $csrfToken, $useCookies, $method);

        $session = $this->getSession(15, ! $useCookies ? $csrfToken : null);

        $identityFinder = $this->getIdentityFinder($identity);

        $subject = new SessionAuth($identityFinder, $security, $session);

        self::assertSame($identity, $subject->authenticate($user, $request, $this->getResponse()));
    }

    public function testNoIdInSession(): void
    {
        $security = new Security();
        $user = $this->getMockBuilder(User::class)->getMock();

        $request = $this->getRequest();

        $session = $this->getSession(null, 'abc');

        $identityFinder = $this->getIdentityFinder();

        $subject = new SessionAuth($identityFinder, $security, $session);

        self::assertSame(null, $subject->authenticate($user, $request, $this->getResponse()));
    }

    public function useCookieAndMethodProvider(): iterable
    {
        $useCookiesOptions = [true, false];
        $methods = ['GET', 'HEAD', 'OPTIONS', 'PUT', 'POST', 'DELETE'];
        foreach ($useCookiesOptions as $useCookiesOption) {
            foreach ($methods as $method) {
                yield [$useCookiesOption, $method];
            }
        }
    }

    /**
     * @dataProvider useCookieAndMethodProvider
     */
    public function testAfterLogin(bool $useCookies, string $method): void
    {
        $callback = $this->getMockBuilder(\stdClass::class)
            ->addMethods(['__invoke'])
            ->getMock();
        $callback->expects($this->once())->method('__invoke');

        $security = new Security();
        $csrfToken = 'abc';
        $request = $this->getRequest($csrfToken, $useCookies ? $csrfToken : null);

        $identity = $this->createIdentity(153);
        $user = $this->getUser($identity);
        $identityFinder = $this->getIdentityFinder($identity);

        $session = $this->getSession(153, $csrfToken);

        $subject = new SessionAuth($identityFinder, $security, $session, \Closure::fromCallable($callback));
        self::assertNotNull($subject->authenticate($user, $request, $this->getResponse()));
    }
}
