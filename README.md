#### 介绍
hyperf框架的链路追踪组件，基于官方包改造完成，要求框架版本^2.0，原来的官方包的tag是固定的，并不实用，代码质量堪忧，这个包在原有的基础上做了修改

#### 特点
 - 支持自定义tag，通过闭包完全自定义
 - 删除了冗余文件，增加代码可读性

#### 使用

- 安装组件
```javascript
composer require hyperf/tracer
```

- 安装依赖
> 如果不是使用的是jaeger作为链路，可以忽略这一步
```javascript
composer require jonahgeorge/jaeger-client-php
```

- 发布配置
```javascript
php bin/hyperf.php vendor:publish sett/hyperf-tracer
```
- 添加配置
> 在config/autoload/opentracing.php文件中添加
```php
return [
    # 链路驱动
    'default' => env('TRACER_DRIVER', 'zipkin'),
    # 开启/关闭以下追踪
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
                'sampler'     => [
                    'type'  => SAMPLER_TYPE_CONST,
                    'param' => true,
                ],
                'local_agent' => [
                    'reporting_host' => env('JAEGER_REPORTING_HOST', 'localhost'),
                    'reporting_port' => env('JAEGER_REPORTING_PORT', 5775),
                ],
                'max_buffer_length' => "1024"
            ],
        ],
    ],
    'tags'    => [
        # 请求外部接口记录
        'http_client' => function (Span $span, array $data) {
            $method = $data['keys']['method'] ?? 'null';
            $uri    = $data['keys']['uri'] ?? 'null';
            $span->setTag("http.url", $uri);
            $span->setTag("http.method", $method);
        },
        # redis    
        'redis'       => function (Span $span, array $data) {
            $span->setTag("redis.arguments", Json::encode($data['arguments']));
        },
        # 数据库
        'db'          => function (Span $span, array $data) {
            $span->setTag("db.query", Json::encode($data['arguments'], JSON_UNESCAPED_UNICODE));
        },
        # 异常记录
        'exception'   => function (Span $span, \Throwable $throwable) {
            $span->setTag("exception.class", get_class($throwable));
            $span->setTag("exception.code", $throwable->getCode());
            $span->setTag("exception.error", $throwable->getMessage());
            $span->setTag("exception.trace", $throwable->getTraceAsString());
        },
        # 外部请求接口时记录的值
        'request'     => function (Span $span) {
            $request = Context::get(ServerRequestInterface::class);
            $span->setTag("http.get.params", Json::encode($request->getQueryParams(), JSON_UNESCAPED_UNICODE));
            $span->setTag("http.post.params", Json::encode($request->getParsedBody(), JSON_UNESCAPED_UNICODE));
        },
        # 记录协程ID
        'coroutine'   => function (Span $span, array $data) {
            $span->setTag("coroutine.id", Coroutine::id());
        },
        # 外部请求接口时记录返回值
        'response'    => function (Span $span) {
            /**@var $response ResponseInterface */
            $response = Context::get(ResponseInterface::class);
            $span->setTag("response.status", (string)$response->getStatusCode());
        },
    ],
]
```

- 添加中间件
> 在config/autoload/middlewares.php文件中添加
```javascript
return [
    'http' => [
        \Hyperf\Tracer\Middleware\TraceMiddleware::class,
],
];
```