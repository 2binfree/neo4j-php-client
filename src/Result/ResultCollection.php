<?php

/*
 * This file is part of the GraphAware Neo4j Client package.
 *
 * (c) GraphAware Limited <http://graphaware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace GraphAware\Neo4j\Client\Result;

use GraphAware\Common\Result\RecordCursorInterface;
use GraphAware\Common\Result\ResultCollection as BaseResultCollection;

class ResultCollection extends BaseResultCollection
{
    /**
     * @var string|null
     */
    protected ?string $tag;

    /**
     * @param string $tag
     */
    public function setTag(string $tag): void
    {
        $this->tag = $tag;
    }

    /**
     * @return null|string
     */
    public function getTag(): ?string
    {
        return $this->tag;
    }

    /**
     * @param RecordCursorInterface $result
     *
     * @return ResultCollection
     */
    public static function withResult(RecordCursorInterface $result): ResultCollection
    {
        $coll = new self();
        $coll->add($result);

        return $coll;
    }
}
