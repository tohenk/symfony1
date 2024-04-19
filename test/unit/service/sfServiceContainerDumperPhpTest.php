<?php

/*
 * This file is part of the symfony package.
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

require_once __DIR__.'/../../bootstrap/unit.php';

$t = new lime_test(5);

$dir = __DIR__.'/fixtures/php';
$normalized_dir = normalize_path(__DIR__);

// ->dump()
$t->diag('->dump()');
$dumper = new sfServiceContainerDumperPhp($container = new sfServiceContainerBuilder());

$t->is(fix_linebreaks($dumper->dump()), fix_linebreaks(file_get_contents($dir.'/services1.php')), '->dump() dumps an empty container as an empty PHP class');
$t->is(fix_linebreaks($dumper->dump(['class' => 'Container', 'base_class' => 'AbstractContainer'])), fix_linebreaks(file_get_contents($dir.'/services1-1.php')), '->dump() takes a class and a base_class options');

$container = new sfServiceContainerBuilder();
$dumper = new sfServiceContainerDumperPhp($container);

// ->addParameters()
$t->diag('->addParameters()');
$container = include __DIR__.'/fixtures/containers/container8.php';
$dumper = new sfServiceContainerDumperPhp($container);
$t->is(fix_linebreaks($dumper->dump()), fix_linebreaks(file_get_contents($dir.'/services8.php')), '->dump() dumps parameters');

// ->addService()
$t->diag('->addService()');
$container = include __DIR__.'/fixtures/containers/container9.php';
$dumper = new sfServiceContainerDumperPhp($container);
$t->is(fix_linebreaks($dumper->dump()), fix_linebreaks(str_replace('%path%', $normalized_dir.'/fixtures/includes', file_get_contents($dir.'/services9.php'))), '->dump() dumps services');

$dumper = new sfServiceContainerDumperPhp($container = new sfServiceContainerBuilder());
$container->register('foo', 'FooClass')->addArgument(new stdClass());

try {
    $dumper->dump();
    $t->fail('->dump() throws a RuntimeException if the container to be dumped has reference to objects or resources');
} catch (RuntimeException $e) {
    $t->pass('->dump() throws a RuntimeException if the container to be dumped has reference to objects or resources');
}
