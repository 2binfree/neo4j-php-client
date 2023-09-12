<?php

/*
 * This file is part of the GraphAware Neo4j Client package.
 *
 * (c) GraphAware Limited <http://graphaware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace GraphAware\Neo4j\Client\Event;

use GraphAware\Common\Cypher\StatementInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;

class PreRunEvent extends EventDispatcher
{
    /**
     * @var StatementInterface[]
     */
    private array $statements;

    /**
     * @param StatementInterface[] $statements
     */
    public function __construct(array $statements)
    {
        parent::__construct();
        $this->statements = $statements;
    }

    /**
     * @return StatementInterface[]
     */
    public function getStatements(): array
    {
        return $this->statements;
    }
}
