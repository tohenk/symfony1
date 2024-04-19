<?php

/*
 * This file is part of the symfony package.
 * (c) 2004-2006 Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Cache class that stores cached content in a SQLite database.
 *
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class sfSQLiteCache extends sfCache
{
    protected $dbh;
    protected $database = '';

    /**
     * Initializes this sfCache instance.
     *
     * Available options:
     *
     * * database: File where to put the cache database (or :memory: to store cache in memory)
     *
     * * see sfCache for options available for all drivers
     *
     * @see sfCache
     */
    public function initialize($options = [])
    {
        if (!extension_loaded('SQLite') && !extension_loaded('pdo_SQLite')) {
            throw new sfConfigurationException('sfSQLiteCache class needs "sqlite" or "pdo_sqlite" extension to be loaded.');
        }

        parent::initialize($options);

        if (!$this->getOption('database')) {
            throw new sfInitializationException('You must pass a "database" option to initialize a sfSQLiteCache object.');
        }

        $this->setDatabase($this->getOption('database'));
    }

    /**
     * @see sfCache
     *
     * @return SQLite3
     */
    public function getBackend()
    {
        return $this->dbh;
    }

    /**
     * @see sfCache
     *
     * @param mixed|null $default
     */
    public function get($key, $default = null)
    {
        $data = $this->dbh->querySingle(sprintf("SELECT data FROM cache WHERE key = '%s' AND timeout > %d", $this->dbh->escapeString($key), time()));

        return null === $data ? $default : $data;
    }

    /**
     * @see sfCache
     */
    public function has($key)
    {
        return (int) $this->dbh->querySingle(sprintf("SELECT count(*) FROM cache WHERE key = '%s' AND timeout > %d", $this->dbh->escapeString($key), time()));
    }

    /**
     * @see sfCache
     *
     * @param mixed|null $lifetime
     */
    public function set($key, $data, $lifetime = null)
    {
        if ($this->getOption('automatic_cleaning_factor') > 0 && 1 == mt_rand(1, $this->getOption('automatic_cleaning_factor'))) {
            $this->clean(sfCache::OLD);
        }

        return $this->dbh->exec(sprintf("INSERT OR REPLACE INTO cache (key, data, timeout, last_modified) VALUES ('%s', '%s', %d, %d)", $this->dbh->escapeString($key), $this->dbh->escapeString($data), time() + $this->getLifetime($lifetime), time()));
    }

    /**
     * @see sfCache
     */
    public function remove($key)
    {
        return $this->dbh->exec(sprintf("DELETE FROM cache WHERE key = '%s'", $this->dbh->escapeString($key)));
    }

    /**
     * @see sfCache
     */
    public function removePattern($pattern)
    {
        return $this->dbh->exec(sprintf("DELETE FROM cache WHERE REGEXP('%s', key)", $this->dbh->escapeString(self::patternToRegexp($pattern))));
    }

    /**
     * @see sfCache
     */
    public function clean($mode = sfCache::ALL)
    {
        $res = $this->dbh->exec('DELETE FROM cache'.(sfCache::OLD == $mode ? sprintf(" WHERE timeout < '%s'", time()) : ''));

        if ($res) {
            return (bool) $this->dbh->changes();
        }

        return false;
    }

    /**
     * @see sfCache
     */
    public function getTimeout($key)
    {
        $rs = $this->dbh->querySingle(sprintf("SELECT timeout FROM cache WHERE key = '%s' AND timeout > %d", $this->dbh->escapeString($key), time()));

        return null === $rs ? 0 : $rs;
    }

    /**
     * @see sfCache
     */
    public function getLastModified($key)
    {
        $rs = $this->dbh->querySingle(sprintf("SELECT last_modified FROM cache WHERE key = '%s' AND timeout > %d", $this->dbh->escapeString($key), time()));

        return null === $rs ? 0 : $rs;
    }

    /**
     * Callback used when deleting keys from cache.
     *
     * @param string $regexp
     * @param string $key
     *
     * @return int
     */
    public function removePatternRegexpCallback($regexp, $key)
    {
        return preg_match($regexp, $key);
    }

    /**
     * @see sfCache
     */
    public function getMany($keys)
    {
        $data = [];
        if ($results = $this->dbh->query(sprintf("SELECT key, data FROM cache WHERE key IN ('%s') AND timeout > %d", implode('\', \'', array_map([$this->dbh, 'escapeString'], $keys)), time()))) {
            while ($row = $results->fetchArray()) {
                $data[$row['key']] = $row['data'];
            }
        }

        return $data;
    }

    /**
     * Sets the database name.
     *
     * @param string $database The database name where to store the cache
     *
     * @throws sfCacheException
     */
    protected function setDatabase($database)
    {
        $this->database = $database;

        $new = false;
        if (':memory:' == $database) {
            $new = true;
        } elseif (!is_file($database)) {
            $new = true;

            // create cache dir if needed
            $dir = dirname($database);
            $current_umask = umask(0000);
            if (!is_dir($dir)) {
                @mkdir($dir, 0777, true);
            }

            touch($database);
            umask($current_umask);
        }

        $this->dbh = new SQLite3($this->database);
        if ('not an error' !== $errmsg = $this->dbh->lastErrorMsg()) {
            throw new sfCacheException(sprintf('Unable to connect to SQLite database: %s.', $errmsg));
        }

        $this->dbh->createFunction('regexp', [$this, 'removePatternRegexpCallback'], 2);

        if ($new) {
            $this->createSchema();
        }
    }

    /**
     * Creates the database schema.
     *
     * @throws sfCacheException
     */
    protected function createSchema()
    {
        $statements = [
            'CREATE TABLE [cache] (
                [key] VARCHAR(255),
                [data] LONGVARCHAR,
                [timeout] TIMESTAMP,
                [last_modified] TIMESTAMP
            )',
            'CREATE UNIQUE INDEX [cache_unique] ON [cache] ([key])',
        ];

        foreach ($statements as $statement) {
            if (false === $this->dbh->query($statement)) {
                $message = $this->dbh->lastErrorMsg();

                throw new sfCacheException($message);
            }
        }
    }
}
