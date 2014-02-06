<?php

/*
 * This file is part of the Sonata package.
 *
 * (c) Thomas Rabaix <thomas.rabaix@sonata-project.org>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sonata\Cache\Adapter\Cache;

use Sonata\Cache\CacheAdapterInterface;
use Sonata\Cache\CacheElement;

/**
 * Handles APC cache
 */
class ApcCache implements CacheAdapterInterface
{
    /**
     * @var string
     */
    protected $url;

    /**
     * @var string
     */
    protected $prefix;

    /**
     * @var array
     */
    protected $servers;

    /**
     * Constructor
     *
     * @param string $url     A router instance
     * @param string $prefix  A prefix to avoid clash between instances
     * @param array  $servers An array of servers
     */
    public function __construct($url, $prefix, $servers = array())
    {
        $this->url     = $url;
        $this->prefix  = $prefix;
        $this->servers = $servers;
    }

    /**
     *
     */
    protected function getUrl()
    {
        return $this->url;
    }

    /**
     * {@inheritdoc}
     */
    public function flushAll()
    {
        $result = true;

        foreach ($this->servers as $server) {
            if (count(explode('.', $server['ip']) == 3)) {
                $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
            } else {
                $socket = socket_create(AF_INET6, SOCK_STREAM, SOL_TCP);
            }

            // generate the raw http request
            $command = sprintf("GET %s HTTP/1.1\r\n", $this->getUrl());
            $command .= sprintf("Host: %s\r\n", $server['domain']);

            if ($server['basic']) {
                $command .= sprintf("Authorization: Basic %s\r\n", $server['basic']);
            }

            $command .= "Connection: Close\r\n\r\n";

            // setup the default timeout (avoid max execution time)
            socket_set_option($socket, SOL_SOCKET, SO_SNDTIMEO, array('sec' => 2, 'usec' => 0));
            socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, array('sec' => 2, 'usec' => 0));

            socket_connect($socket, $server['ip'], $server['port']);
            socket_write($socket, $command);

            $content = '';

            do {
                $buffer = socket_read($socket, 1024);
                $content .= $buffer;
            } while (!empty($buffer));

            if ($result) {
                $result = substr($content, -2) == 'ok';
            }
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function flush(array $keys = array())
    {
        return $this->flushAll();
    }

    /**
     * {@inheritdoc}
     */
    public function has(array $keys)
    {
        return apc_exists($this->computeCacheKeys($keys));
    }

    /**
     * {@inheritdoc}
     */
    public function set(array $keys, $data, $ttl = 84600, array $contextualKeys = array())
    {
        $cacheElement = new CacheElement($keys, $data, $ttl);

        $result = apc_store(
            $this->computeCacheKeys($keys),
            $cacheElement,
            $cacheElement->getTtl()
        );

        return $cacheElement;
    }

    /**
     * Computes the given cache keys
     *
     * @param CacheElement $cacheElement
     *
     * @return string
     */
    private function computeCacheKeys($keys)
    {
        ksort($keys);

        return md5($this->prefix.serialize($keys));
    }

    /**
     * {@inheritdoc}
     */
    public function get(array $keys)
    {
        return apc_fetch($this->computeCacheKeys($keys));
    }

    /**
     * {@inheritdoc}
     */
    public function isContextual()
    {
        return false;
    }
}
