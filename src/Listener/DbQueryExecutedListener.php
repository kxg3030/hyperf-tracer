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

namespace Sett\Tracer\Listener;

use Hyperf\Database\Events\QueryExecuted;
use Hyperf\Event\Annotation\Listener;
use Hyperf\Event\Contract\ListenerInterface;
use Sett\Tracer\SpanStarter;
use Sett\Tracer\SpanTagManager;
use Sett\Tracer\SwitchManager;
use Hyperf\Utils\Arr;
use Hyperf\Utils\Str;
use OpenTracing\Tracer;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

class DbQueryExecutedListener implements ListenerInterface
{
    use SpanStarter;

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

    public function listen(): array {
        return [
            QueryExecuted::class,
        ];
    }

    /**
     * @param object $event
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function process(object $event) {
        if ($this->switchManager->isEnable('db') === false) {
            return;
        }
        $sql = $event->sql;
        if (!Arr::isAssoc($event->bindings)) {
            foreach ($event->bindings as $value) {
                $sql = Str::replaceFirst('?', "'{$value}'", $sql);
            }
        }
        $endTime = microtime(true);
        $span    = $this->startSpan("db.query", [
            'start_time' => (int)(($endTime - $event->time / 1000) * 1000 * 1000),
        ]);
        $span->setTag("db.statement", $sql);
        $span->setTag("db.query_time", $event->time . ' ms');
        $span->finish((int)($endTime * 1000 * 1000));
    }
}
