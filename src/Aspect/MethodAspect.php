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

use Hyperf\Di\Aop\AbstractAspect;
use Hyperf\Di\Aop\ProceedingJoinPoint;
use Hyperf\Di\Exception\Exception;
use Sett\Tracer\SpanStarter;
use Sett\Tracer\SwitchManager;
use OpenTracing\Tracer;

/**
 * Aspect.
 */
class MethodAspect extends AbstractAspect
{
    use SpanStarter;

    /**
     * @var array
     */
    public $classes = [
        'App*',
    ];

    /**
     * @var Tracer
     */
    private $tracer;

    /**
     * @var SwitchManager
     */
    private $switchManager;

    public function __construct(Tracer $tracer, SwitchManager $switchManager) {
        $this->tracer        = $tracer;
        $this->switchManager = $switchManager;
    }

    /**
     * @param ProceedingJoinPoint $proceedingJoinPoint
     * @return mixed return the value from process method of ProceedingJoinPoint, or the value that you handled
     * @throws Exception
     * @throws \Throwable
     */
    public function process(ProceedingJoinPoint $proceedingJoinPoint) {
        if ($this->switchManager->isEnable('method') === false) {
            return $proceedingJoinPoint->process();
        }

        $key  = $proceedingJoinPoint->className . '::' . $proceedingJoinPoint->methodName;
        $span = $this->startSpan($key);
        try {
            $result = $proceedingJoinPoint->process();
        } catch (\Throwable $e) {
            $span->setTag('error', true);
            $span->log(['message', $e->getMessage(), 'code' => $e->getCode(), 'stacktrace' => $e->getTraceAsString()]);
            throw $e;
        } finally {
            $span->finish();
        }
        return $result;
    }
}
