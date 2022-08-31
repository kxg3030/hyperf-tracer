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

use Hyperf\Utils\Context;
use OpenTracing\Span;

class SwitchManager
{
    /**
     * @var array
     */
    private $config = [
        'guzzle'    => false,
        'redis'     => false,
        'db'        => false,
        'method'    => false,
        'exception' => false,
    ];

    /**
     * @param array $config
     */
    public function apply(array $config): void {
        $this->config = array_merge($this->config, $config);
    }

    /**
     * @param string $identifier
     * @return bool
     */
    public function isEnable(string $identifier): bool {
        if (!isset($this->config[$identifier])) {
            return false;
        }

        return $this->config[$identifier] && \Hyperf\Context\Context::get('tracer.rootSpan') instanceof Span;
    }
}
