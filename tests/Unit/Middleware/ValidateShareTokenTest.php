<?php

namespace Tests\Unit\Middleware;

use App\Enums\ShareTokenMode;
use App\Http\Middleware\ValidateShareToken;
use App\Models\ShoppingList;
use App\Services\ShareTokenService;
use App\Support\ShareTokenContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Tests\TestCase;

class ValidateShareTokenTest extends TestCase
{
    use DatabaseTransactions;

    private ValidateShareToken $middleware;

    private ShareTokenService $service;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ShareTokenService::class);
        $this->middleware = new ValidateShareToken($this->service);
    }

    private function requestWithToken(string $raw, string $method = 'GET'): Request
    {
        $request = Request::create('/api/shared/'.$raw, $method);
        $route = (new Route([$method], '/api/shared/{tokenParam}', []))
            ->bind($request);
        $route->setParameter('tokenParam', $raw);
        $request->setRouteResolver(fn () => $route);

        return $request;
    }

    public function test_returns_410_when_token_missing_from_route(): void
    {
        $request = Request::create('/api/shared/', 'GET');
        $route = (new Route(['GET'], '/api/shared/{tokenParam}', []))
            ->bind($request);
        $request->setRouteResolver(fn () => $route);

        $response = $this->middleware->handle($request, fn () => response('ok'));

        $this->assertEquals(410, $response->getStatusCode());
    }

    public function test_returns_410_on_malformed_token(): void
    {
        $request = $this->requestWithToken('nothinghere');

        $response = $this->middleware->handle($request, fn () => response('ok'));

        $this->assertEquals(410, $response->getStatusCode());
    }

    public function test_returns_410_on_invalid_signature(): void
    {
        $request = $this->requestWithToken('fake-id.fake-sig');

        $response = $this->middleware->handle($request, fn () => response('ok'));

        $this->assertEquals(410, $response->getStatusCode());
    }

    public function test_returns_410_on_revoked_token(): void
    {
        $list = ShoppingList::factory()->createOne();
        $token = $this->service->generate($list, ShareTokenMode::Edit);
        $raw = substr($this->service->urlFor($token), strrpos($this->service->urlFor($token), '/') + 1);
        $this->service->revoke($token);

        $request = $this->requestWithToken($raw);

        $response = $this->middleware->handle($request, fn () => response('ok'));

        $this->assertEquals(410, $response->getStatusCode());
    }

    public function test_attaches_context_on_valid_token(): void
    {
        $list = ShoppingList::factory()->createOne();
        $token = $this->service->generate($list, ShareTokenMode::Edit);
        $raw = substr($this->service->urlFor($token), strrpos($this->service->urlFor($token), '/') + 1);

        $request = $this->requestWithToken($raw);

        $called = false;
        $this->middleware->handle($request, function (Request $req) use (&$called) {
            $called = true;
            $this->assertInstanceOf(ShareTokenContext::class, $req->attributes->get('shareTokenContext'));

            return response('ok');
        });

        $this->assertTrue($called);
    }

    public function test_returns_403_when_read_only_hits_write_route(): void
    {
        $list = ShoppingList::factory()->createOne();
        $token = $this->service->generate($list, ShareTokenMode::ReadOnly);
        $raw = substr($this->service->urlFor($token), strrpos($this->service->urlFor($token), '/') + 1);

        $request = $this->requestWithToken($raw, 'POST');

        $response = $this->middleware->handle($request, fn () => response('ok'), 'write');

        $this->assertEquals(403, $response->getStatusCode());
    }

    public function test_allows_edit_on_write_route(): void
    {
        $list = ShoppingList::factory()->createOne();
        $token = $this->service->generate($list, ShareTokenMode::Edit);
        $raw = substr($this->service->urlFor($token), strrpos($this->service->urlFor($token), '/') + 1);

        $request = $this->requestWithToken($raw, 'POST');

        $response = $this->middleware->handle($request, fn () => response('ok'), 'write');

        $this->assertEquals(200, $response->getStatusCode());
    }
}
