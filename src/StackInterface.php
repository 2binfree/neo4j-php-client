<?php

/*
 * This file is part of the GraphAware Neo4j Client package.
 *
 * (c) GraphAware Limited <http://graphaware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace GraphAware\Neo4j\Client;

use GraphAware\Common\Cypher\Statement;

/**
 * Interface StackInterface.
 */
interface StackInterface
{
    public static function create(string $tag = null, string $connectionAlias = null): Stack;

    public function push(string $query, array $parameters = null, array $tag = null);

    public function pushWrite(string $query, array $parameters = null, array $tag = null);

    public function addPreflight(string $query, array $parameters = null, array $tag = null);

    public function hasPreflights(): bool;

    /**
     * @return Statement[]
     */
    public function getPreflights(): array;

    public function size(): ?int;

    /**
     * @return Statement[]
     */
    public function statements(): array;

    /**
     * @return null|string
     */
    public function getTag(): ?string;

    public function getConnectionAlias(): ?string;

    public function hasWrites(): bool;
}
