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

use Elasticsearch\Client;
use Hyperf\Di\Annotation\Aspect;
use Hyperf\Di\Aop\AbstractAspect;
use Hyperf\Di\Aop\ProceedingJoinPoint;
use Hyperf\Di\Exception\Exception;
use Sett\Tracer\SpanStarter;
use Sett\Tracer\SpanTagManager;
use Sett\Tracer\SwitchManager;
use OpenTracing\Tracer;

/**
 * Class ElasticserachAspect
 * @package Hyperf\Tracer\Aspect
 * @Aspect
 */
class ElasticserachAspect extends AbstractAspect
{
    use SpanStarter;

    /**
     * @var array
     */
    public $classes = [
        Client::class . '::bulk',
        Client::class . '::count',
        Client::class . '::create',
        Client::class . '::get',
        Client::class . '::getSource',
        Client::class . '::index',
        Client::class . '::mget',
        Client::class . '::msearch',
        Client::class . '::scroll',
        Client::class . '::search',
        Client::class . '::update',
        Client::class . '::updateByQuery',
        Client::class . '::search',
    ];

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

    public function __construct(Tracer $tracer, SwitchManager $switchManager, SpanTagManager $spanTagManager) {
        $this->tracer         = $tracer;
        $this->switchManager  = $switchManager;
        $this->spanTagManager = $spanTagManager;
    }

    /**
     * @param ProceedingJoinPoint $proceedingJoinPoint
     * @return mixed return the value from process method of ProceedingJoinPoint, or the value that you handled
     * @throws Exception
     * @throws \Throwable
     */
    public function process(ProceedingJoinPoint $proceedingJoinPoint) {
        $key  = $proceedingJoinPoint->className . '::' . $proceedingJoinPoint->methodName;
        $span = $this->startSpan($key);
        try {
            $result = $proceedingJoinPoint->process();
        } catch (\Throwable $e) {
            $span->setTag('exception', true);
            $span->log(['message', $e->getMessage(), 'code' => $e->getCode(), 'stacktrace' => $e->getTraceAsString()]);
            throw $e;
        } finally {
            $span->finish();
        }
        return $result;
    }
}
