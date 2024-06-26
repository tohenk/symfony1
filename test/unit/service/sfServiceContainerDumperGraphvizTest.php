<?php

/*
 * This file is part of the symfony package.
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

require_once __DIR__.'/../../bootstrap/unit.php';

$t = new lime_test(4);

$dir = __DIR__.'/fixtures/graphviz';
$normalized_dir = normalize_path(__DIR__);

// ->dump()
$t->diag('->dump()');
$dumper = new sfServiceContainerDumperGraphviz($container = new sfServiceContainerBuilder());

$t->is(fix_linebreaks($dumper->dump()), fix_linebreaks(file_get_contents($dir.'/services1.dot')), '->dump() dumps an empty container as an empty dot file');

$container = new sfServiceContainerBuilder();
$dumper = new sfServiceContainerDumperGraphviz($container);

$container = include __DIR__.'/fixtures/containers/container9.php';
$dumper = new sfServiceContainerDumperGraphviz($container);
$t->is(fix_linebreaks($dumper->dump()), fix_linebreaks(str_replace('%path%', $normalized_dir, file_get_contents($dir.'/services9.dot'))), '->dump() dumps services');

$container = include __DIR__.'/fixtures/containers/container10.php';
$dumper = new sfServiceContainerDumperGraphviz($container);
$t->is(fix_linebreaks($dumper->dump()), fix_linebreaks(str_replace('%path%', $normalized_dir, file_get_contents($dir.'/services10.dot'))), '->dump() dumps services');

$container = include __DIR__.'/fixtures/containers/container10.php';
$dumper = new sfServiceContainerDumperGraphviz($container);
$t->is(fix_linebreaks($dumper->dump([
    'graph' => ['ratio' => 'normal'],
    'node' => ['fontsize' => 13, 'fontname' => 'Verdana', 'shape' => 'square'],
    'edge' => ['fontsize' => 12, 'fontname' => 'Verdana', 'color' => 'white', 'arrowhead' => 'closed', 'arrowsize' => 1],
    'node.instance' => ['fillcolor' => 'green', 'style' => 'empty'],
    'node.definition' => ['fillcolor' => 'grey'],
    'node.missing' => ['fillcolor' => 'red', 'style' => 'empty'],
])), fix_linebreaks(str_replace('%path%', $normalized_dir, file_get_contents($dir.'/services10-1.dot'))), '->dump() dumps services');
