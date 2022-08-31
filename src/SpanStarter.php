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
use Hyperf\Rpc;
use Hyperf\Utils\ApplicationContext;
use OpenTracing\Reference;
use OpenTracing\Span;
use OpenTracing\Tracer;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Http\Message\ServerRequestInterface;
use const OpenTracing\Formats\TEXT_MAP;
use const OpenTracing\Tags\SPAN_KIND;
use const OpenTracing\Tags\SPAN_KIND_RPC_SERVER;

/**
 * Trait SpanStarter
 * @package Hyperf\Tracer
 * @property Tracer $tracer
 * @property SpanTagManager $spanTagManager
 */
trait SpanStarter
{
    /**
     * @param string $name
     * @param array $option
     * @param string $kind
     * @return Span
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    protected function startSpan(string $name, array $option = [], string $kind = SPAN_KIND_RPC_SERVER): Span {
        $rootSpan = Context::get('tracer.rootSpan');
        if (!$rootSpan instanceof Span) {
            $container = ApplicationContext::getContainer();
            /** @var ServerRequestInterface $request */
            $request = Context::get(ServerRequestInterface::class);
            if (!$request instanceof ServerRequestInterface) {
                $rootSpan = $this->tracer->startSpan($name, $option);
                $rootSpan->setTag(SPAN_KIND, $kind);
                Context::set('tracer.rootSpan', $rootSpan);
                return $rootSpan;
            }
            $carrier = array_map(function ($header) {
                return $header[0];
            }, $request->getHeaders());

            if ($container->has(Rpc\Context::class) && $rpcContext = $container->get(Rpc\Context::class)) {
                $rpcCarrier = $rpcContext->get('tracer.carrier');
                if (!empty($rpcCarrier)) {
                    $carrier = $rpcCarrier;
                }
            }
            $parentContext = $this->tracer->extract(TEXT_MAP, $carrier);
            if ($parentContext) {
                $option["references"] = [
                    new Reference(Reference::CHILD_OF, $parentContext),
                ];
            }
            $rootSpan = $this->tracer->startSpan($name, $option);
            $rootSpan->setTag(SPAN_KIND, $kind);
            Context::set('tracer.rootSpan', $rootSpan);
            return $rootSpan;
        }
        $option["references"] = [new Reference(Reference::CHILD_OF, $rootSpan->getContext())];
        $child                = $this->tracer->startSpan($name, $option);
        $child->setTag(SPAN_KIND, $kind);
        return $child;
    }

    /**
     * @param string $tagName
     * @param mixed $extra
     * @param Span $span
     */
    public function record(string $tagName, $extra, Span $span) {
        if (!$this->spanTagManager->exist($tagName)) {
            return;
        }
        $callback = $this->spanTagManager->get($tagName);
        if (is_callable($callback)) {
            $callback($span, $extra);
        }
    }
}
