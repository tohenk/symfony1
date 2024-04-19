<?php

/*
 * This file is part of the symfony package.
 * (c) 2004-2006 Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

require_once __DIR__.'/../../bootstrap/unit.php';

require_once __DIR__.'/sfCacheDriverTests.class.php';

$plan = 129;
$t = new lime_test($plan);

if (!extension_loaded('SQLite') && !extension_loaded('pdo_SQLite')) {
    $t->skip('SQLite extension not loaded, skipping tests', $plan);

    return;
}

try {
    new sfSQLiteCache(['database' => ':memory:']);
} catch (sfInitializationException $e) {
    $t->skip($e->getMessage(), $plan);

    return;
}

// ->initialize()
$t->diag('->initialize()');

try {
    $cache = new sfSQLiteCache();
    $t->fail('->initialize() throws an sfInitializationException exception if you don\'t pass a "database" parameter');
} catch (sfInitializationException $e) {
    $t->pass('->initialize() throws an sfInitializationException exception if you don\'t pass a "database" parameter');
}

// database in memory
$cache = new sfSQLiteCache(['database' => ':memory:']);

try {
    sfCacheDriverTests::launch($t, $cache);
} catch (Exception $e) {
    $t->fail($e->getMessage());
}

// database on disk
$database = sys_get_temp_dir().DIRECTORY_SEPARATOR.'sf-sqlite-cache.db';
$cache = new sfSQLiteCache(['database' => $database]);

try {
    sfCacheDriverTests::launch($t, $cache);
} catch (Exception $e) {
    $t->fail($e->getMessage());
}

$cache->getBackend()->close();
unlink($database);
