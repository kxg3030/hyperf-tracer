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

namespace Sett\Tracer\Annotation;

use Attribute;
use Doctrine\Common\Annotations\Annotation\Target;
use Hyperf\Di\Annotation\AbstractAnnotation;

/**
 * @Annotation
 * @Target({"CLASS", "METHOD"})
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class Trace extends AbstractAnnotation
{
    /**
     * @var string
     */
    public $name = '';

    /**
     * @var array|string
     */
    public $tag = 'source';
}
