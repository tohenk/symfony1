<?php

/*
 * This file is part of the symfony package.
 * (c) 2004-2006 Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

require_once __DIR__.'/../../bootstrap/unit.php';

$plan = 73;
$t = new lime_test($plan);

require_once __DIR__.'/sfCacheDriverTests.class.php';

// setup
sfConfig::set('sf_logging_enabled', false);

// ->initialize()
$t->diag('->initialize()');

$redisHost = getenv('REDIS_HOST');
if (!$redisHost) {
    $redisHost = null;
}

try {
    $cache = new sfRedisCache(['host' => $redisHost]);
} catch (sfInitializationException $e) {
    $t->skip('A valid Redis configuration is required to run these tests', $plan);

    return;
}

sfCacheDriverTests::launch($t, $cache);

// ->remove() test for ticket #6220
$t->diag('->remove() test for ticket #6220');
$cache->clean();
$cache->set('test_1', 'abc');
$cache->set('test_2', 'abc');
$cache->remove('test_1');
$cacheInfo = $cache->getCacheInfo();
$t->ok(is_array($cacheInfo), 'Cache info is an array');
$t->is(count($cacheInfo), 1, 'Cache info contains 1 element');
$t->ok(!in_array('test_1', $cacheInfo), 'Cache info no longer contains the removed key');
$t->ok(in_array('test_2', $cacheInfo), 'Cache info still contains the key that was not removed');

// ->removePattern() test for ticket #6220
$t->diag('->removePattern() test for ticket #6220');
$cache->clean();
$cache->set('test_1', 'abc');
$cache->set('test_2', 'abc');
$cache->set('test3', 'abc');
$cache->removePattern('test_*');
$cacheInfo = $cache->getCacheInfo();
$t->ok(is_array($cacheInfo), 'Cache info is an array');
$t->is(count($cacheInfo), 1, 'Cache info contains 1 element');
$t->ok(!in_array('test_1', $cacheInfo), 'Cache info no longer contains the key that matches the pattern (first key)');
$t->ok(!in_array('test_2', $cacheInfo), 'Cache info no longer contains the key that matches the pattern (second key)');
$t->ok(in_array('test3', $cacheInfo), 'Cache info still contains the key that did not match the pattern (third key)');
