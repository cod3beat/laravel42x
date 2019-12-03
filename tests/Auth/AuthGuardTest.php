<?php /** @noinspection PhpParamsInspection */

use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Cookie;
use Illuminate\Events\Dispatcher;
use Illuminate\Auth\Guard;
use Illuminate\Auth\UserInterface;
use Illuminate\Cookie\CookieJar;
use Illuminate\Auth\UserProviderInterface;
use Illuminate\Session\Store;
use Symfony\Component\HttpFoundation\Request;

class AuthGuardTest extends \L4\Tests\BackwardCompatibleTestCase {

    /**
     * @var UserProviderInterface|ObjectProphecy
     */
    private $userProvider;
    /**
     * @var Store|ObjectProphecy
     */
    private $session;

    protected function setUp()
    {
        parent::setUp();

        $this->userProvider = $this->prophesize(UserProviderInterface::class);
        $this->session = $this->prophesize(Store::class);
    }

	public function testBasicReturnsNullOnValidAttempt()
	{
	    $guard = new Guard(
	        $this->userProvider->reveal(),
            $this->session->reveal(),
            $request = Request::create('/', 'GET', array(), array(), array(), array('PHP_AUTH_USER' => 'foo@bar.com', 'PHP_AUTH_PW' => 'secret'))
        );
	    $this->userProvider
            ->retrieveByCredentials(['email' => 'foo@bar.com', 'password' => 'secret'])
            ->willReturn($this->prophesize(UserInterface::class)->reveal());
        $this->userProvider
            ->validateCredentials(
                Argument::type(UserInterface::class),
                ['email' => 'foo@bar.com', 'password' => 'secret']
            )->willReturn(true);

        $this->assertNull($guard->basic('email', $request));
    }

    public function testUserIsAuthenticatedWhenBasicAttemptIsValid()
    {
        $guard = new Guard(
            $this->userProvider->reveal(),
            $this->session->reveal(),
            $request = Request::create('/', 'GET', array(), array(), array(), array('PHP_AUTH_USER' => 'foo@bar.com', 'PHP_AUTH_PW' => 'secret'))
        );
        $this->userProvider
            ->retrieveByCredentials(['email' => 'foo@bar.com', 'password' => 'secret'])
            ->willReturn($this->prophesize(UserInterface::class)->reveal());
        $this->userProvider
            ->validateCredentials(
                Argument::type(UserInterface::class),
                ['email' => 'foo@bar.com', 'password' => 'secret']
            )->willReturn(true);

        $guard->basic('email', $request);

        $this->assertTrue($guard->check());
    }

	public function testBasicReturnsNullWhenAlreadyLoggedIn()
	{
        $guard = new Guard(
            $this->userProvider->reveal(),
            $this->session->reveal(),
            $request = Request::create('/', 'GET', array(), array(), array(), array('PHP_AUTH_USER' => 'foo@bar.com', 'PHP_AUTH_PW' => 'secret'))
        );
        $guard->login($this->prophesize(UserInterface::class)->reveal());

		$this->assertNull($guard->basic('email', $request));
	}

	public function testBasicReturnsResponseOnFailure()
	{
        $guard = new Guard(
            $this->userProvider->reveal(),
            $this->session->reveal(),
            $request = Request::create('/', 'GET', array(), array(), array(), array('PHP_AUTH_USER' => 'foo@bar.com', 'PHP_AUTH_PW' => 'secret'))
        );
        $this->userProvider
            ->retrieveByCredentials(['email' => 'foo@bar.com', 'password' => 'secret'])
            ->willReturn($this->prophesize(UserInterface::class)->reveal());
        $this->userProvider
            ->validateCredentials(
                Argument::type(UserInterface::class),
                ['email' => 'foo@bar.com', 'password' => 'secret']
            )->willReturn(false);

        $response = $guard->basic('email', $request);

		$this->assertInstanceOf(Response::class, $response);
	}

    public function testBasicReturnsResponseWithCorrectResponseCodeOnFailure()
    {
        $guard = new Guard(
            $this->userProvider->reveal(),
            $this->session->reveal(),
            $request = Request::create('/', 'GET', array(), array(), array(), array('PHP_AUTH_USER' => 'foo@bar.com', 'PHP_AUTH_PW' => 'secret'))
        );
        $this->userProvider
            ->retrieveByCredentials(['email' => 'foo@bar.com', 'password' => 'secret'])
            ->willReturn($this->prophesize(UserInterface::class)->reveal());
        $this->userProvider
            ->validateCredentials(
                Argument::type(UserInterface::class),
                ['email' => 'foo@bar.com', 'password' => 'secret']
            )->willReturn(false);

        $response = $guard->basic('email', $request);

        $this->assertEquals(401, $response->getStatusCode());
    }

	public function testAttemptCallsRetrieveByCredentials()
	{
        $guard = new Guard($this->userProvider->reveal(), $this->session->reveal(), new Request());
        $guard->setDispatcher(($events = $this->prophesize(Dispatcher::class))->reveal());

		$events->fire('auth.attempt', [['foo'], false, true])->shouldBeCalledTimes(1);
		$this->userProvider->retrieveByCredentials(['foo'])->shouldBeCalledTimes(1);

		$guard->attempt(['foo']);
	}

	public function testAttemptReturnsFalseIfUserNotGiven()
	{
        $guard = new Guard($this->userProvider->reveal(), $this->session->reveal(), new Request());
        $guard->setDispatcher(($events = $this->prophesize(Dispatcher::class))->reveal());

        $this->userProvider->retrieveByCredentials(['foo'])->willReturn(null);

		$this->assertFalse($guard->attempt(array('foo')));
	}

	public function testLoginStoresIdentifierInSession()
	{
        $guard = new Guard($this->userProvider->reveal(), $this->session->reveal(), new Request());
		$user = $this->prophesize(UserInterface::class);
		$user->getAuthIdentifier()->willReturn('foo');

		$this->session->migrate(true)->shouldBeCalledTimes(1);
		$this->session->put('login_82e5d2c56bdd0811318f0cf078b78bfc', 'foo')->shouldBeCalledTimes(1);

		$guard->login($user->reveal());
	}

	public function testLoginFiresLoginEvent()
	{
        $guard = new Guard($this->userProvider->reveal(), $this->session->reveal(), new Request());
        $guard->setDispatcher(($events = $this->prophesize(Dispatcher::class))->reveal());
        $user = $this->prophesize(UserInterface::class);
        $user->getAuthIdentifier()->willReturn('foo');

        $events->fire('auth.login', [$user, false])->shouldBeCalledTimes(1);

		$guard->login($user->reveal());
	}

	public function testIsAuthedReturnsTrueWhenUserIsNotNull()
	{
        $guard = new Guard($this->userProvider->reveal(), $this->session->reveal(), new Request());

        $guard->setUser(($user = $this->prophesize(UserInterface::class))->reveal());

        $this->assertTrue($guard->check());
	}

    public function testIsNotGuessWhenUserIsNotNull()
    {
        $guard = new Guard($this->userProvider->reveal(), $this->session->reveal(), new Request());

        $guard->setUser(($user = $this->prophesize(UserInterface::class))->reveal());

        $this->assertFalse($guard->guest());
    }

	public function testIsAuthedReturnsFalseWhenUserIsNull()
	{
        $guard = new Guard($this->userProvider->reveal(), $this->session->reveal(), new Request());

        $this->assertFalse($guard->check());
	}

	public function testUserMethodReturnsCachedUser()
	{
        $guard = new Guard($this->userProvider->reveal(), $this->session->reveal(), new Request());

        $guard->setUser(($user = $this->prophesize(UserInterface::class))->reveal());

		$this->assertEquals($user->reveal(), $guard->user());
	}

	public function testNullIsReturnedForUserIfNoUserFound()
	{
        $guard = new Guard($this->userProvider->reveal(), $this->session->reveal(), new Request());

        $this->session->get('login_82e5d2c56bdd0811318f0cf078b78bfc')->willReturn(null);

		$this->assertNull($guard->user());
	}


	public function testUserIsSetToRetrievedUser()
	{
        $guard = new Guard($this->userProvider->reveal(), $this->session->reveal(), new Request());
        $guard->setUser(($user = $this->prophesize(UserInterface::class))->reveal());

        $this->session->get('login_82e5d2c56bdd0811318f0cf078b78bfc')->willReturn(111);
        $this->userProvider->retrieveById(111)->willReturn($user->reveal());

        $this->assertEquals($user->reveal(), $guard->user());
        $this->assertEquals($user->reveal(), $guard->getUser());
	}

	public function testLogoutRemovesSessionTokenAndRememberMeCookie()
	{
        $guard = new Guard($this->userProvider->reveal(), $this->session->reveal(), new Request());
        $guard->setUser(($user = $this->prophesize(UserInterface::class))->reveal());
        $guard->setCookieJar(($cookieJar = $this->prophesize(CookieJar::class))->reveal());

        $this->session->forget('login_82e5d2c56bdd0811318f0cf078b78bfc')->shouldBeCalledTimes(1);
        $cookieJar->forget('remember_82e5d2c56bdd0811318f0cf078b78bfc')
            ->shouldBeCalledTimes(1)
            ->willReturn($cookie = $this->prophesize(Cookie::class)->reveal());
        $cookieJar->queue($cookie)->shouldBeCalledTimes(1);

        $guard->logout();
	}

    public function testLogoutWillNullifyTheUser()
    {
        $guard = new Guard($this->userProvider->reveal(), $this->session->reveal(), new Request());
        $guard->setUser(($user = $this->prophesize(UserInterface::class))->reveal());
        $guard->setCookieJar(($cookieJar = $this->prophesize(CookieJar::class))->reveal());

        $guard->logout();

        $this->assertNull($guard->user());
    }

	public function testLogoutFiresLogoutEvent()
	{
        $guard = new Guard($this->userProvider->reveal(), $this->session->reveal(), new Request());
        $guard->setUser(($user = $this->prophesize(UserInterface::class))->reveal());
        $guard->setCookieJar(($cookieJar = $this->prophesize(CookieJar::class))->reveal());
        $guard->setDispatcher(($event = $this->prophesize(Dispatcher::class))->reveal());

        $event->fire('auth.logout', [$user])->shouldBeCalledTimes(1);

        $guard->logout();
	}

	public function testLoginMethodQueuesCookieWhenRemembering()
	{
        $guard = new Guard($this->userProvider->reveal(), $this->session->reveal(), new Request());
        $guard->setCookieJar(($cookieJar = $this->prophesize(CookieJar::class))->reveal());

        $user = $this->prophesize(UserInterface::class);
        $user->getAuthIdentifier()->willReturn('foo');
        $user->getRememberToken()->willReturn('recaller');

        $foreverCookie = new Symfony\Component\HttpFoundation\Cookie($guard->getRecallerName(), 'foo');
        $cookieJar->forever($guard->getRecallerName(), 'foo|recaller')->willReturn($foreverCookie);
        $cookieJar->queue($foreverCookie)->shouldBeCalledTimes(1);

        $guard->login($user->reveal(), true);
	}

	public function testLoginMethodCreatesRememberTokenIfOneDoesntExist()
	{
        $guard = new Guard($this->userProvider->reveal(), $this->session->reveal(), new Request());
        $guard->setCookieJar(($cookieJar = $this->prophesize(CookieJar::class))->reveal());
        $user = $this->prophesize(UserInterface::class);
        $user->getAuthIdentifier()->willReturn('foo');
        $user->getRememberToken()->willReturn(null);

        $user->setRememberToken(Argument::type('string'))->shouldBeCalledTimes(1);
        $this->userProvider->updateRememberToken($user->reveal(), Argument::type('string'))->shouldBeCalledTimes(1);

        $guard->login($user->reveal(), true);
	}


	public function testLoginUsingIdStoresInSessionAndLogsInWithUser()
	{
        $guard = new Guard($this->userProvider->reveal(), $this->session->reveal(), new Request());
        $this->userProvider
            ->retrieveById(10)
            ->willReturn(($user = $this->prophesize(UserInterface::class))->reveal());
        $user->getAuthIdentifier()->willReturn(10);

        $this->session->put('login_82e5d2c56bdd0811318f0cf078b78bfc', 10)->shouldBeCalledTimes(2);
        $this->session->migrate(true)->shouldBeCalledTimes(1);

		$guard->loginUsingId(10);
	}

    public function testLoginUsingIdReturnsLoggedInUser()
    {
        $guard = new Guard($this->userProvider->reveal(), $this->session->reveal(), new Request());

        $this->userProvider
            ->retrieveById(10)
            ->willReturn(($user = $this->prophesize(UserInterface::class))->reveal());

        $this->assertEquals($user->reveal(), $guard->loginUsingId(10));
    }

	public function testUserUsesRememberCookieIfItExists()
	{
        $guard = new Guard(
            $this->userProvider->reveal(),
            $this->session->reveal(),
            Request::create('/', 'GET', array(), array('remember_82e5d2c56bdd0811318f0cf078b78bfc' => 'id|recaller'))
        );

        $this->userProvider
            ->retrieveByToken('id', 'recaller')
            ->willReturn(($user = $this->prophesize(UserInterface::class))->reveal());

		$this->assertEquals($user->reveal(), $guard->user());
		$this->assertTrue($guard->viaRemember());
	}
}
