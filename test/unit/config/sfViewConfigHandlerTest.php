<?php

/*
 * This file is part of the symfony package.
 * (c) 2004-2006 Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

require_once __DIR__.'/../../bootstrap/unit.php';

$t = new lime_test(22);

class myViewConfigHandler extends sfViewConfigHandler
{
    public function setConfiguration($config)
    {
        $this->yamlConfig = self::mergeConfig($config);
    }

    public function addHtmlAsset($viewName = '')
    {
        return parent::addHtmlAsset($viewName);
    }
}

$handler = new myViewConfigHandler();

// addHtmlAsset() basic asset addition
$t->diag('addHtmlAsset() basic asset addition');

$handler->setConfiguration([
    'myView' => [
        'stylesheets' => ['foobar'],
    ],
]);
$content = <<<'EOF'
$response->addStylesheet('foobar', '', []);

EOF;
$t->is(fix_linebreaks($handler->addHtmlAsset('myView')), fix_linebreaks($content), 'addHtmlAsset() adds stylesheets to the response');

$handler->setConfiguration([
    'myView' => [
        'stylesheets' => [['foobar' => ['position' => 'last']]],
    ],
]);
$content = <<<'EOF'
$response->addStylesheet('foobar', 'last', []);

EOF;
$t->is(fix_linebreaks($handler->addHtmlAsset('myView')), fix_linebreaks($content), 'addHtmlAsset() adds stylesheets to the response');

$handler->setConfiguration([
    'myView' => [
        'javascripts' => ['foobar'],
    ],
]);
$content = <<<'EOF'
$response->addJavascript('foobar', '', []);

EOF;
$t->is(fix_linebreaks($handler->addHtmlAsset('myView')), fix_linebreaks($content), 'addHtmlAsset() adds JavaScript to the response');

$handler->setConfiguration([
    'myView' => [
        'javascripts' => [['foobar' => ['position' => 'last']]],
    ],
]);
$content = <<<'EOF'
$response->addJavascript('foobar', 'last', []);

EOF;
$t->is(fix_linebreaks($handler->addHtmlAsset('myView')), fix_linebreaks($content), 'addHtmlAsset() adds JavaScript to the response');

// Insertion order for stylesheets
$t->diag('addHtmlAsset() insertion order for stylesheets');

$handler->setConfiguration([
    'myView' => [
        'stylesheets' => ['foobar'],
    ],
    'all' => [
        'stylesheets' => ['all_foobar'],
    ],
]);
$content = <<<'EOF'
$response->addStylesheet('all_foobar', '', []);
$response->addStylesheet('foobar', '', []);

EOF;
$t->is(fix_linebreaks($handler->addHtmlAsset('myView')), fix_linebreaks($content), 'addHtmlAsset() adds view-specific stylesheets after application-wide assets');

$handler->setConfiguration([
    'all' => [
        'stylesheets' => ['all_foobar'],
    ],
    'myView' => [
        'stylesheets' => ['foobar'],
    ],
]);
$content = <<<'EOF'
$response->addStylesheet('all_foobar', '', []);
$response->addStylesheet('foobar', '', []);

EOF;
$t->is(fix_linebreaks($handler->addHtmlAsset('myView')), fix_linebreaks($content), 'addHtmlAsset() adds view-specific stylesheets after application-wide assets');

$handler->setConfiguration([
    'myView' => [
        'stylesheets' => ['foobar'],
    ],
    'default' => [
        'stylesheets' => ['default_foobar'],
    ],
]);
$content = <<<'EOF'
$response->addStylesheet('default_foobar', '', []);
$response->addStylesheet('foobar', '', []);

EOF;
$t->is(fix_linebreaks($handler->addHtmlAsset('myView')), fix_linebreaks($content), 'addHtmlAsset() adds view-specific stylesheets after default assets');

$handler->setConfiguration([
    'default' => [
        'stylesheets' => ['default_foobar'],
    ],
    'myView' => [
        'stylesheets' => ['foobar'],
    ],
]);
$content = <<<'EOF'
$response->addStylesheet('default_foobar', '', []);
$response->addStylesheet('foobar', '', []);

EOF;
$t->is(fix_linebreaks($handler->addHtmlAsset('myView')), fix_linebreaks($content), 'addHtmlAsset() adds view-specific stylesheets after default assets');

$handler->setConfiguration([
    'default' => [
        'stylesheets' => ['default_foobar'],
    ],
    'all' => [
        'stylesheets' => ['all_foobar'],
    ],
]);
$content = <<<'EOF'
$response->addStylesheet('default_foobar', '', []);
$response->addStylesheet('all_foobar', '', []);

EOF;
$t->is(fix_linebreaks($handler->addHtmlAsset('myView')), fix_linebreaks($content), 'addHtmlAsset() adds application-specific stylesheets after default assets');

$handler->setConfiguration([
    'all' => [
        'stylesheets' => ['all_foobar'],
    ],
    'default' => [
        'stylesheets' => ['default_foobar'],
    ],
]);
$content = <<<'EOF'
$response->addStylesheet('default_foobar', '', []);
$response->addStylesheet('all_foobar', '', []);

EOF;
$t->is(fix_linebreaks($handler->addHtmlAsset('myView')), fix_linebreaks($content), 'addHtmlAsset() adds application-specific stylesheets after default assets');

// Insertion order for javascripts
$t->diag('addHtmlAsset() insertion order for javascripts');

$handler->setConfiguration([
    'myView' => [
        'javascripts' => ['foobar'],
    ],
    'all' => [
        'javascripts' => ['all_foobar'],
    ],
]);
$content = <<<'EOF'
$response->addJavascript('all_foobar', '', []);
$response->addJavascript('foobar', '', []);

EOF;
$t->is(fix_linebreaks($handler->addHtmlAsset('myView')), fix_linebreaks($content), 'addHtmlAsset() adds view-specific javascripts after application-wide assets');

$handler->setConfiguration([
    'all' => [
        'javascripts' => ['all_foobar'],
    ],
    'myView' => [
        'javascripts' => ['foobar'],
    ],
]);
$content = <<<'EOF'
$response->addJavascript('all_foobar', '', []);
$response->addJavascript('foobar', '', []);

EOF;
$t->is(fix_linebreaks($handler->addHtmlAsset('myView')), fix_linebreaks($content), 'addHtmlAsset() adds view-specific javascripts after application-wide assets');

$handler->setConfiguration([
    'myView' => [
        'javascripts' => ['foobar'],
    ],
    'default' => [
        'javascripts' => ['default_foobar'],
    ],
]);
$content = <<<'EOF'
$response->addJavascript('default_foobar', '', []);
$response->addJavascript('foobar', '', []);

EOF;
$t->is(fix_linebreaks($handler->addHtmlAsset('myView')), fix_linebreaks($content), 'addHtmlAsset() adds view-specific javascripts after default assets');

$handler->setConfiguration([
    'default' => [
        'javascripts' => ['default_foobar'],
    ],
    'myView' => [
        'javascripts' => ['foobar'],
    ],
]);
$content = <<<'EOF'
$response->addJavascript('default_foobar', '', []);
$response->addJavascript('foobar', '', []);

EOF;
$t->is(fix_linebreaks($handler->addHtmlAsset('myView')), fix_linebreaks($content), 'addHtmlAsset() adds view-specific javascripts after default assets');

$handler->setConfiguration([
    'default' => [
        'javascripts' => ['default_foobar'],
    ],
    'all' => [
        'javascripts' => ['all_foobar'],
    ],
]);
$content = <<<'EOF'
$response->addJavascript('default_foobar', '', []);
$response->addJavascript('all_foobar', '', []);

EOF;
$t->is(fix_linebreaks($handler->addHtmlAsset('myView')), fix_linebreaks($content), 'addHtmlAsset() adds application-specific javascripts after default assets');

$handler->setConfiguration([
    'all' => [
        'javascripts' => ['all_foobar'],
    ],
    'default' => [
        'javascripts' => ['default_foobar'],
    ],
]);
$content = <<<'EOF'
$response->addJavascript('default_foobar', '', []);
$response->addJavascript('all_foobar', '', []);

EOF;
$t->is(fix_linebreaks($handler->addHtmlAsset('myView')), fix_linebreaks($content), 'addHtmlAsset() adds application-specific javascripts after default assets');

// removal of assets
$t->diag('addHtmlAsset() removal of assets');

$handler->setConfiguration([
    'all' => [
        'stylesheets' => ['all_foo', 'all_bar'],
    ],
    'myView' => [
        'stylesheets' => ['foobar', '-all_bar'],
    ],
]);
$content = <<<'EOF'
$response->addStylesheet('all_foo', '', []);
$response->addStylesheet('foobar', '', []);

EOF;
$t->is(fix_linebreaks($handler->addHtmlAsset('myView')), fix_linebreaks($content), 'addHtmlAsset() supports the - option to remove one stylesheet previously added');

$handler->setConfiguration([
    'all' => [
        'javascripts' => ['all_foo', 'all_bar'],
    ],
    'myView' => [
        'javascripts' => ['foobar', '-all_bar'],
    ],
]);
$content = <<<'EOF'
$response->addJavascript('all_foo', '', []);
$response->addJavascript('foobar', '', []);

EOF;
$t->is(fix_linebreaks($handler->addHtmlAsset('myView')), fix_linebreaks($content), 'addHtmlAsset() supports the - option to remove one javascript previously added');

$handler->setConfiguration([
    'all' => [
        'stylesheets' => ['foo', 'bar', '-*', 'baz'],
    ],
]);
$content = <<<'EOF'
$response->addStylesheet('baz', '', []);

EOF;
$t->is(fix_linebreaks($handler->addHtmlAsset('myView')), fix_linebreaks($content), 'addHtmlAsset() supports the -* option to remove all stylesheets previously added');

$handler->setConfiguration([
    'all' => [
        'javascripts' => ['foo', 'bar', '-*', 'baz'],
    ],
]);
$content = <<<'EOF'
$response->addJavascript('baz', '', []);

EOF;
$t->is(fix_linebreaks($handler->addHtmlAsset('myView')), fix_linebreaks($content), 'addHtmlAsset() supports the -* option to remove all javascripts previously added');

$handler->setConfiguration([
    'all' => [
        'stylesheets' => ['-*', 'foobar'],
    ],
    'default' => [
        'stylesheets' => ['default_foo', 'default_bar'],
    ],
]);
$content = <<<'EOF'
$response->addStylesheet('foobar', '', []);

EOF;
$t->is(fix_linebreaks($handler->addHtmlAsset('myView')), fix_linebreaks($content), 'addHtmlAsset() supports the -* option to remove all assets previously added');

$handler->setConfiguration([
    'myView' => [
        'stylesheets' => ['foobar', '-*', 'bar'],
        'javascripts' => ['foobar', '-*', 'bar'],
    ],
    'all' => [
        'stylesheets' => ['all_foo', 'all_foofoo', 'all_barbar'],
        'javascripts' => ['all_foo', 'all_foofoo', 'all_barbar'],
    ],
    'default' => [
        'stylesheets' => ['default_foo', 'default_foofoo', 'default_barbar'],
        'javascripts' => ['default_foo', 'default_foofoo', 'default_barbar'],
    ],
]);
$content = <<<'EOF'
$response->addStylesheet('bar', '', []);
$response->addJavascript('bar', '', []);

EOF;
$t->is(fix_linebreaks($handler->addHtmlAsset('myView')), fix_linebreaks($content), 'addHtmlAsset() supports the -* option to remove all assets previously added');
