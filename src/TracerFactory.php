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

use Hyperf\Contract\ConfigInterface;
use Sett\Tracer\Adapter\ZipkinTracerFactory;
use Sett\Tracer\Contract\NamedFactoryInterface;
use Sett\Tracer\Exception\InvalidArgumentException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

class TracerFactory
{
    /**
     * @var ConfigInterface
     */
    private $config;

    /**
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     */
    public function __invoke(ContainerInterface $container) {
        $this->config = $container->get(ConfigInterface::class);
        $name         = $this->config->get('opentracing.default');

        // v1.0 has no 'default' config. Fallback to v1.0 mode for backward compatibility.
        if (empty($name)) {
            $factory = $container->get(ZipkinTracerFactory::class);
            return $factory->make('');
        }

        $driver = $this->config->get("opentracing.tracer.{$name}.driver");
        if (empty($driver)) {
            throw new InvalidArgumentException(
                sprintf('The tracing config [%s] doesn\'t contain a valid driver.', $name)
            );
        }

        $factory = $container->get($driver);

        if (!($factory instanceof NamedFactoryInterface)) {
            throw new InvalidArgumentException(
                sprintf('The driver %s is not a valid factory.', $driver)
            );
        }

        return $factory->make($name);
    }
}
