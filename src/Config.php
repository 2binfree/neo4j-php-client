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

use GraphAware\Neo4j\Client\HttpDriver\Driver;

class Config
{
    protected int $defaultHttpPort = Driver::DEFAULT_HTTP_PORT;

    protected int $defaultTcpPort = 8687;

    public static function create(): Config
    {
        return new self();
    }

    public function withDefaultHttpPort(int $port): static
    {
        $this->defaultHttpPort = (int) $port;

        return $this;
    }

    public function withDefaultTcpPort(int $port): static
    {
        $this->defaultTcpPort = (int) $port;

        return $this;
    }
}
