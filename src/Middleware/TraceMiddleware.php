<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace Sett\Tracer\Middleware;

use Hyperf\HttpMessage\Exception\HttpException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Sett\Tracer\SpanStarter;
use Sett\Tracer\SpanTagManager;
use Sett\Tracer\SwitchManager;
use Hyperf\Utils\Coroutine;
use OpenTracing\Span;
use OpenTracing\Tracer;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class TraceMiddleware implements MiddlewareInterface
{
    use SpanStarter;

    /**
     * @var SwitchManager
     */
    protected $switchManager;

    /**
     * @var SpanTagManager
     */
    protected $spanTagManager;

    /**
     * @var Tracer
     */
    private $tracer;

    public function __construct(Tracer $tracer, SwitchManager $switchManager, SpanTagManager $spanTagManager) {
        $this->tracer         = $tracer;
        $this->switchManager  = $switchManager;
        $this->spanTagManager = $spanTagManager;
    }

    /**
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     * @throws \Throwable
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface {
        $span = $this->buildSpan($request);
        Coroutine::defer(function () {
            try {
                $this->tracer->flush();
            } catch (\Throwable $exception) {
            }
        });
        try {
            $response = $handler->handle($request);
            $this->record("response", [], $span);
        } catch (\Throwable $exception) {
            $this->switchManager->isEnable('exception') && $this->appendExceptionToSpan($span, $exception);
            if ($exception instanceof HttpException) {
                $span->setTag("response.status", $exception->getStatusCode());
            }
            throw $exception;
        } finally {
            $span->finish();
        }
        return $response;
    }

    /**
     * @param Span $span
     * @param \Throwable $exception
     */
    protected function appendExceptionToSpan(Span $span, \Throwable $exception): void {
        $this->record("exception",$exception,$span);
    }

    /**
     * @param ServerRequestInterface $request
     * @return Span
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    protected function buildSpan(ServerRequestInterface $request): Span {
        $uri  = $request->getUri();
        $span = $this->startSpan((string)$uri);
        $this->record("request", [], $span);
        return $span;
    }
}
