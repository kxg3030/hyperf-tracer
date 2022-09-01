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

namespace Sett\Tracer;

use Hyperf\Context\Context;
use Hyperf\Utils\ApplicationContext;
use Hyperf\Utils\Codec\Json;
use Hyperf\Utils\Coroutine;
use OpenTracing\Span;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Zipkin\Samplers\BinarySampler;

class SpanTagManager
{
    public $tags = [];

    public function apply(array $tags): void {
        $this->init();
        $this->tags = array_merge($this->tags, $tags);
    }

    public function exist(string $type): bool {
        return isset($this->tags[$type]);
    }

    public function get(string $name) {
        return $this->tags[$name] ?? null;
    }

    private function init() {
        $this->tags = [
            'http_client' => function (Span $span, array $data) {
                $method = $data['keys']['method'] ?? 'null';
                $uri    = $data['keys']['uri'] ?? 'null';
                $span->setTag("http.url", $uri);
                $span->setTag("http.method", $method);
            },
            'redis'       => function (Span $span, array $data) {
                /**@var $request ServerRequestInterface */
                $request = Context::get(ServerRequestInterface::class);
                $span->setTag("http.url", (string)$request->getUri());
                $span->setTag("http.headers", Json::encode($request->getHeaders(),JSON_UNESCAPED_UNICODE));
                $span->setTag("redis.arguments", Json::encode($data['arguments']));
            },
            'db'          => function (Span $span, array $data) {
                /**@var $request ServerRequestInterface */
                $request = Context::get(ServerRequestInterface::class);
                $span->setTag("http.url", (string)$request->getUri());
                $span->setTag("http.headers", Json::encode($request->getHeaders(),JSON_UNESCAPED_UNICODE));
            },
            'exception'   => function (Span $span, \Throwable $throwable) {
                /**@var $request ServerRequestInterface */
                $request = Context::get(ServerRequestInterface::class);
                $span->setTag("http.url", (string)$request->getUri());
                $span->setTag("http.headers", Json::encode($request->getHeaders(),JSON_UNESCAPED_UNICODE));
                $span->setTag("exception.class", get_class($throwable));
                $span->setTag("exception.code", $throwable->getCode());
                $span->setTag("exception.error", $throwable->getMessage());
                $span->setTag("exception.trace", $throwable->getTraceAsString());
            },
            'request'     => function (Span $span) {
                $request = Context::get(ServerRequestInterface::class);
                $span->setTag("http.url", (string)$request->getUri());
                $span->setTag("http.headers", Json::encode($request->getHeaders(),JSON_UNESCAPED_UNICODE));
                $span->setTag("http.get.params", Json::encode($request->getQueryParams(), JSON_UNESCAPED_UNICODE));
                $span->setTag("http.post.params", Json::encode($request->getParsedBody(), JSON_UNESCAPED_UNICODE));
            },
            'coroutine'   => function (Span $span, array $data) {
                $span->setTag("coroutine.id", Coroutine::id());
            },
            'response'    => function (Span $span) {
                /**@var $response ResponseInterface */
                $response = Context::get(ResponseInterface::class);
                $span->setTag("response.status", (string)$response->getStatusCode());
            },
        ];
    }
}
