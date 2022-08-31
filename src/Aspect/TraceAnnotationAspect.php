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

use Hyperf\Di\Annotation\Aspect;
use Hyperf\Di\Aop\AroundInterface;
use Hyperf\Di\Aop\ProceedingJoinPoint;
use Hyperf\Di\Exception\Exception;
use Sett\Tracer\Annotation\Trace;
use Sett\Tracer\SpanStarter;
use OpenTracing\Tracer;

/**
 * @Aspect
 */
class TraceAnnotationAspect implements AroundInterface
{
    use SpanStarter;

    public $classes = [];

    public $annotations = [
        Trace::class,
    ];

    /**
     * @var Tracer
     */
    private $tracer;

    public function __construct(Tracer $tracer) {
        $this->tracer = $tracer;
    }

    /**
     * @param ProceedingJoinPoint $proceedingJoinPoint
     * @return mixed return the value from process method of ProceedingJoinPoint, or the value that you handled
     * @throws Exception
     * @throws \Throwable
     */
    public function process(ProceedingJoinPoint $proceedingJoinPoint) {
        $source   = $proceedingJoinPoint->className . '::' . $proceedingJoinPoint->methodName;
        $metadata = $proceedingJoinPoint->getAnnotationMetadata();
        /** @var Trace $annotation */
        if ($annotation = $metadata->method[Trace::class] ?? null) {
            $name = $annotation->name;
            $tag  = $annotation->tag;
        } else {
            $name = $source;
            $tag  = 'source';
        }
        $span = $this->startSpan($name);
        $span->setTag($tag, $source);
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
