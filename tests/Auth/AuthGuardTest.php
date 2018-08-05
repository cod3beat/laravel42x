<?php /** @noinspection PhpParamsInspection */

use Prophecy\Prophecy\ObjectProphecy;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Cookie;
use Illuminate\Events\Dispatcher;
use Illuminate\Auth\Guard;
use Illuminate\Auth\UserInterface;
use Illuminate\Encryption\Encrypter;
use Illuminate\Cookie\CookieJar;
use Illuminate\Auth\UserProviderInterface;
use Illuminate\Session\Store;
use Mockery as m;
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
    /**
     * @var Request|ObjectProphecy
     */
    private $request;

    protected function setUp()
    {
        parent::setUp();

        $this->userProvider = $this->prophesize(UserProviderInterface::class);
        $this->session = $this->prophesize(Store::class);
        $this->request = $this->prophesize(Request::class);
    }

    public function tearDown()
	{
		m::close();
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
                \Prophecy\Argument::type(UserInterface::class),
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
                \Prophecy\Argument::type(UserInterface::class),
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
                \Prophecy\Argument::type(UserInterface::class),
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
                \Prophecy\Argument::type(UserInterface::class),
                ['email' => 'foo@bar.com', 'password' => 'secret']
            )->willReturn(false);

        $response = $guard->basic('email', $request);

        $this->assertEquals(401, $response->getStatusCode());
    }

	public function testAttemptCallsRetrieveByCredentials()
	{
        $guard = new Guard($this->userProvider->reveal(), $this->session->reveal(), $this->request->reveal());
        $events = $this->prophesize(Dispatcher::class);
		$guard->setDispatcher($events->reveal());

		$events->fire('auth.attempt', [['foo'], false, true])->shouldBeCalledTimes(1);
		$this->userProvider->retrieveByCredentials(['foo'])->shouldBeCalledTimes(1);

		$guard->attempt(['foo']);
	}

	public function testAttemptReturnsFalseIfUserNotGiven()
	{
        $guard = new Guard($this->userProvider->reveal(), $this->session->reveal(), $this->request->reveal());
        $events = $this->prophesize(Dispatcher::class);
        $guard->setDispatcher($events->reveal());

        $this->userProvider->retrieveByCredentials(['foo'])->willReturn(null);

		$this->assertFalse($guard->attempt(array('foo')));
	}

	public function testLoginStoresIdentifierInSession()
	{
        $guard = new Guard($this->userProvider->reveal(), $this->session->reveal(), $this->request->reveal());
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
		list($session, $provider, $request, $cookie) = $this->getMocks();
		$guard = new Illuminate\Auth\Guard($provider, $session, $request);
		$guard->setCookieJar($cookie);
		$foreverCookie = new Symfony\Component\HttpFoundation\Cookie($guard->getRecallerName(), 'foo');
		$cookie->shouldReceive('forever')->once()->with($guard->getRecallerName(), 'foo|recaller')->andReturn($foreverCookie);
		$cookie->shouldReceive('queue')->once()->with($foreverCookie);
		$guard->getSession()->shouldReceive('put')->once()->with($guard->getName(), 'foo');
		$session->shouldReceive('migrate')->once();
		$user = m::mock(UserInterface::class);
		$user->shouldReceive('getAuthIdentifier')->andReturn('foo');
		$user->shouldReceive('getRememberToken')->andReturn('recaller');
		$user->shouldReceive('setRememberToken')->never();
		$provider->shouldReceive('updateRememberToken')->never();
		$guard->login($user, true);
	}


	public function testLoginMethodCreatesRememberTokenIfOneDoesntExist()
	{
		list($session, $provider, $request, $cookie) = $this->getMocks();
		$guard = new Illuminate\Auth\Guard($provider, $session, $request);
		$guard->setCookieJar($cookie);
		$foreverCookie = new Symfony\Component\HttpFoundation\Cookie($guard->getRecallerName(), 'foo');
		$cookie->shouldReceive('forever')->once()->andReturn($foreverCookie);
		$cookie->shouldReceive('queue')->once()->with($foreverCookie);
		$guard->getSession()->shouldReceive('put')->once()->with($guard->getName(), 'foo');
		$session->shouldReceive('migrate')->once();
		$user = m::mock(UserInterface::class);
		$user->shouldReceive('getAuthIdentifier')->andReturn('foo');
		$user->shouldReceive('getRememberToken')->andReturn(null);
		$user->shouldReceive('setRememberToken')->once();
		$provider->shouldReceive('updateRememberToken')->once();
		$guard->login($user, true);
	}


	public function testLoginUsingIdStoresInSessionAndLogsInWithUser()
	{
		list($session, $provider, $request, $cookie) = $this->getMocks();
		$guard = $this->getMock(Guard::class, array('login', 'user'), array($provider, $session, $request));
		$guard->getSession()->shouldReceive('put')->once()->with($guard->getName(), 10);
		$guard->getProvider()->shouldReceive('retrieveById')->once()->with(10)->andReturn($user = m::mock('Illuminate\Auth\UserInterface'));
		$guard->expects($this->once())->method('login')->with($this->equalTo($user), $this->equalTo(false))->will($this->returnValue($user));

		$this->assertEquals($user, $guard->loginUsingId(10));
	}


	public function testUserUsesRememberCookieIfItExists()
	{
		$guard = $this->getGuard();
		list($session, $provider, $request, $cookie) = $this->getMocks();
		$request = Symfony\Component\HttpFoundation\Request::create('/', 'GET', array(), array($guard->getRecallerName() => 'id|recaller'));
		$guard = new Illuminate\Auth\Guard($provider, $session, $request);
		$guard->getSession()->shouldReceive('get')->once()->with($guard->getName())->andReturn(null);
		$user = m::mock(UserInterface::class);
		$guard->getProvider()->shouldReceive('retrieveByToken')->once()->with('id', 'recaller')->andReturn($user);
		$this->assertEquals($user, $guard->user());
		$this->assertTrue($guard->viaRemember());
	}


	protected function getGuard()
	{
		list($session, $provider, $request, $cookie) = $this->getMocks();
		return new Illuminate\Auth\Guard($provider, $session, $request);
	}


	protected function getMocks()
	{
		return array(
			m::mock(Store::class),
			m::mock(UserProviderInterface::class),
			Symfony\Component\HttpFoundation\Request::create('/', 'GET'),
			m::mock(CookieJar::class),
		);
	}


	protected function getCookieJar()
	{
		return new Illuminate\Cookie\CookieJar(Request::create('/foo', 'GET'), m::mock(Encrypter::class), array('domain' => 'foo.com', 'path' => '/', 'secure' => false, 'httpOnly' => false));
	}

}
