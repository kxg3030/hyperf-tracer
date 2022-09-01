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

use Hyperf\Context\Context;
use Hyperf\Utils\ApplicationContext;
use Hyperf\Utils\Codec\Json;
use Hyperf\Utils\Coroutine;
use OpenTracing\Span;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Zipkin\Samplers\BinarySampler;
use const Jaeger\SAMPLER_TYPE_CONST;

return [
    'default' => env('TRACER_DRIVER', 'zipkin'),
    'enable'  => [
        'guzzle'    => env('TRACER_ENABLE_GUZZLE', true),
        'redis'     => env('TRACER_ENABLE_REDIS', true),
        'db'        => env('TRACER_ENABLE_DB', true),
        'method'    => env('TRACER_ENABLE_METHOD', false),
        'exception' => env('TRACER_ENABLE_EXCEPTION', false),
    ],
    'tracer'  => [
        'zipkin' => [
            'driver'  => Sett\Tracer\Adapter\ZipkinTracerFactory::class,
            'app'     => [
                'name' => env('APP_NAME', 'skeleton'),
                'ipv4' => '127.0.0.1',
                'ipv6' => null,
                'port' => 9501,
            ],
            'options' => [
                'endpoint_url' => env('ZIPKIN_ENDPOINT_URL', 'http://localhost:9411/api/v2/spans'),
                'timeout'      => env('ZIPKIN_TIMEOUT', 1),
            ],
            'sampler' => BinarySampler::createAsAlwaysSample(),
        ],
        'jaeger' => [
            'driver'  => Sett\Tracer\Adapter\JaegerTracerFactory::class,
            'name'    => env('APP_NAME', 'skeleton'),
            'options' => [
                'sampler'           => [
                    'type'  => SAMPLER_TYPE_CONST,
                    'param' => true,
                ],
                'local_agent'       => [
                    'reporting_host' => env('JAEGER_REPORTING_HOST', 'localhost'),
                    'reporting_port' => env('JAEGER_REPORTING_PORT', 5775),
                ],
                'max_buffer_length' => "1024"
            ],
        ],
    ],
    'tags'    => [
        'http_client' => function (Span $span, array $data) {
            $method = $data['keys']['method'] ?? 'null';
            $uri    = $data['keys']['uri'] ?? 'null';
            $span->setTag("http.url", $uri);
            $span->setTag("http.method", $method);
        },
        'redis'       => function (Span $span, array $data) {
            $span->setTag("redis.arguments", Json::encode($data['arguments']));
        },
        'db'          => function (Span $span, array $data) {
            /**@var $request ServerRequestInterface */
            $request = Context::get(ServerRequestInterface::class);
            $span->setTag("http.url", (string)$request->getUri());
        },
        'exception'   => function (Span $span, \Throwable $throwable) {
            $span->setTag("exception.class", get_class($throwable));
            $span->setTag("exception.code", $throwable->getCode());
            $span->setTag("exception.error", $throwable->getMessage());
            $span->setTag("exception.trace", $throwable->getTraceAsString());
        },
        'request'     => function (Span $span) {
            $request = Context::get(ServerRequestInterface::class);
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
    ],
];
