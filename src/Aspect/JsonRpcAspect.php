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

namespace Sett\Tracer\Aspect;

use Hyperf\Di\Aop\AroundInterface;
use Hyperf\Di\Aop\ProceedingJoinPoint;
use Hyperf\Di\Exception\Exception;
use Hyperf\Rpc\Context;
use Hyperf\RpcClient\AbstractServiceClient;
use Hyperf\RpcClient\Client;
use Sett\Tracer\SpanStarter;
use Sett\Tracer\SpanTagManager;
use Sett\Tracer\SwitchManager;
use OpenTracing\Tracer;
use Psr\Container\ContainerInterface;
use Zipkin\Span;
use const OpenTracing\Formats\TEXT_MAP;

class JsonRpcAspect implements AroundInterface
{
    use SpanStarter;

    public $classes = [
        AbstractServiceClient::class . '::__generateRpcPath',
        Client::class . '::send',
    ];

    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * @var Tracer
     */
    private $tracer;

    /**
     * @var SwitchManager
     */
    private $switchManager;

    /**
     * @var SpanTagManager
     */
    private $spanTagManager;

    /**
     * @var Context
     */
    private $context;

    public function __construct(ContainerInterface $container) {
        $this->container      = $container;
        $this->tracer         = $container->get(Tracer::class);
        $this->switchManager  = $container->get(SwitchManager::class);
        $this->spanTagManager = $container->get(SpanTagManager::class);
        $this->context        = $container->get(Context::class);
    }

    /**
     * @throws Exception
     * @throws \Throwable
     */
    public function process(ProceedingJoinPoint $proceedingJoinPoint) {
        if ($proceedingJoinPoint->methodName === '__generateRpcPath') {
            $path = $proceedingJoinPoint->process();
            $key  = "rpc:$path";
            $span = $this->startSpan($key);
            $this->record("rpc", ["path" => $path],$span);
            $carrier = [];
            $this->tracer->inject($span->getContext(), TEXT_MAP, $carrier);
            $this->context->set('tracer.carrier', $carrier);
            \Hyperf\Context\Context::set('tracer.span.' . static::class, $span);
            return $path;
        }

        if ($proceedingJoinPoint->methodName === 'send') {
            try {
                $result = $proceedingJoinPoint->process();
            } catch (\Throwable $e) {
                if ($span = \Hyperf\Context\Context::get('tracer.span.' . static::class)) {
                    $span->setTag('exception', true);
                    $span->log(['message', $e->getMessage(), 'code' => $e->getCode(), 'stacktrace' => $e->getTraceAsString()]);
                    \Hyperf\Context\Context::set('tracer.span.' . static::class, $span);
                }
                throw $e;
            } finally {
                /** @var Span $span */
                if ($span = \Hyperf\Context\Context::get('tracer.span.' . static::class)) {
                    $span->setTag("rpc.status", isset($result['result']) ? 'success' : 'fail');
                    $span->finish();
                }
            }

            return $result;
        }
        return $proceedingJoinPoint->process();
    }
}
