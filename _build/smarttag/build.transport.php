<?php

/**
 * SmartTag
 *
 * Copyright 2014-2015 by goldsky <goldsky@virtudraft.com>
 *
 * This file is part of SmartTag, a MODX's custom Template Variable for tagging
 *
 * SmartTag is free software; you can redistribute it and/or modify it under the
 * terms of the GNU General Public License as published by the Free Software
 * Foundation version 3.
 *
 * SmartTag is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR
 * A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * SmartTag; if not, write to the Free Software Foundation, Inc., 59 Temple Place,
 * Suite 330, Boston, MA 02111-1307 USA
 *
 * SmartTag build script
 *
 * @package smarttag
 * @subpackage build
 */
$mtime = microtime();
$mtime = explode(" ", $mtime);
$mtime = $mtime[1] + $mtime[0];
$tstart = $mtime;
set_time_limit(0);

/* define names */
define('PKG_NAME', 'SmartTag');
define('PKG_NAME_LOWER', 'smarttag');

/* override with your own defines here (see build.config.sample.php) */
require_once dirname(__FILE__) . '/build.config.php';
require_once realpath(MODX_CORE_PATH) . '/model/modx/modx.class.php';

/* define sources */
$root = dirname(dirname(dirname(__FILE__))) . DIRECTORY_SEPARATOR;
$sources = array(
    'root' => $root,
    'build' => BUILD_PATH,
    'resolvers' => BUILD_PATH . 'resolvers' . DIRECTORY_SEPARATOR,
    'data' => BUILD_PATH . 'data' . DIRECTORY_SEPARATOR,
    'properties' => realpath(BUILD_PATH . 'data/properties/') . DIRECTORY_SEPARATOR,
    'source_core' => realpath(MODX_CORE_PATH . 'components') . DIRECTORY_SEPARATOR . PKG_NAME_LOWER,
    'source_assets' => realpath(MODX_ASSETS_PATH . 'components') . DIRECTORY_SEPARATOR . PKG_NAME_LOWER,
    'docs' => realpath(MODX_CORE_PATH . 'components/' . PKG_NAME_LOWER . '/docs/') . DIRECTORY_SEPARATOR,
    'lexicon' => realpath(MODX_CORE_PATH . 'components/' . PKG_NAME_LOWER . '/lexicon/') . DIRECTORY_SEPARATOR,
);
unset($root);

$modx = new modX();
$modx->initialize('mgr');
$modx->setLogLevel(modX::LOG_LEVEL_INFO);
$modx->setLogTarget(XPDO_CLI_MODE ? 'ECHO' : 'HTML');
echo '<pre>';

$smartTag = $modx->getService('smarttag', 'SmartTag', MODX_CORE_PATH . 'components/smarttag/model/');

if (!($smartTag instanceof SmartTag)) {
    return '';
}

/* define version */
define('PKG_VERSION', SmartTag::VERSION);
define('PKG_RELEASE', SmartTag::RELEASE);

$modx->loadClass('transport.modPackageBuilder', '', false, true);
$builder = new modPackageBuilder($modx);
$builder->createPackage(PKG_NAME_LOWER, PKG_VERSION, PKG_RELEASE);
$builder->registerNamespace(PKG_NAME_LOWER, false, true, '{core_path}components/' . PKG_NAME_LOWER . '/');

/**
 * MENU & ACTION
 */
$menu = include $sources['data'] . 'transport.menu.php';
if (empty($menu)) {
    $modx->log(modX::LOG_LEVEL_ERROR, 'Could not package in menu.');
} else {
    $modx->log(modX::LOG_LEVEL_INFO, 'Packaging in menu...');
    $menuVehicle = $builder->createVehicle($menu, array(
        xPDOTransport::PRESERVE_KEYS => true,
        xPDOTransport::UPDATE_OBJECT => true,
        xPDOTransport::UNIQUE_KEY => 'text',
        xPDOTransport::RELATED_OBJECTS => true,
        xPDOTransport::RELATED_OBJECT_ATTRIBUTES => array(
            'Action' => array(
                xPDOTransport::PRESERVE_KEYS => false,
                xPDOTransport::UPDATE_OBJECT => true,
                xPDOTransport::UNIQUE_KEY => array('namespace', 'controller'),
            ),
        ),
    ));
    $modx->log(modX::LOG_LEVEL_INFO, 'Adding in Menu & Action done.');
    $builder->putVehicle($menuVehicle);
    unset($menuVehicle, $menu);
}

/**
 * SYSTEM SETTINGS
 */
$settings = include $sources['data'] . 'transport.settings.php';
if (!is_array($settings)) {
    $modx->log(modX::LOG_LEVEL_ERROR, 'Could not package in settings.');
} else {
    $modx->log(modX::LOG_LEVEL_INFO, 'Packaging in System Settings...');
    $settingAttributes = array(
        xPDOTransport::UNIQUE_KEY => 'key',
        xPDOTransport::PRESERVE_KEYS => true,
        xPDOTransport::UPDATE_OBJECT => false,
    );
    foreach ($settings as $setting) {
        $settingVehicle = $builder->createVehicle($setting, $settingAttributes);
        $builder->putVehicle($settingVehicle);
    }
    $modx->log(modX::LOG_LEVEL_INFO, 'Packaged in ' . count($settings) . ' System Settings done.');
    unset($settingVehicle, $settings, $setting, $settingAttributes);
}

/**
 * CATEGORY
 */
$category = $modx->newObject('modCategory');
$category->set('id', 1);
$category->set('category', 'SmartTag');

/**
 * PLUGINS
 */
$modx->log(modX::LOG_LEVEL_INFO, 'Adding in plugins.');
$plugins = include $sources['data'] . 'transport.plugins.php';
if (is_array($plugins)) {
    $category->addMany($plugins);
    $modx->log(modX::LOG_LEVEL_INFO, 'Adding in ' . count($plugins) . ' plugins done.');
} else {
    $modx->log(modX::LOG_LEVEL_FATAL, 'Adding plugins failed.');
}

/**
 * SNIPPETS
 */
$snippets = include $sources['data'] . 'transport.snippets.php';
if (is_array($snippets)) {
    $modx->log(modX::LOG_LEVEL_INFO, 'Adding in snippets.');
    $category->addMany($snippets);
    $modx->log(modX::LOG_LEVEL_INFO, 'Adding in snippets done.');
    flush();
} else {
    $modx->log(modX::LOG_LEVEL_FATAL, 'Adding snippets failed.');
}

/**
 * CHUNKS
 */
$modx->log(modX::LOG_LEVEL_INFO, 'Adding in chunks...');
flush();
$chunks = include $sources['data'] . 'transport.chunks.php';
if (is_array($chunks)) {
    $category->addMany($chunks);
    $modx->log(modX::LOG_LEVEL_INFO, 'Adding in ' . count($chunks) . ' chunks done.');
} else {
    $modx->log(modX::LOG_LEVEL_FATAL, 'Adding chunks failed.');
}

/**
 * Apply category to the elements
 */
$elementsAttribute = array(
    xPDOTransport::UNIQUE_KEY => 'category',
    xPDOTransport::PRESERVE_KEYS => false,
    xPDOTransport::UPDATE_OBJECT => true,
    xPDOTransport::RELATED_OBJECTS => true,
    xPDOTransport::RELATED_OBJECT_ATTRIBUTES => array(
        'Snippets' => array(
            xPDOTransport::PRESERVE_KEYS => false,
            xPDOTransport::UPDATE_OBJECT => true,
            xPDOTransport::UNIQUE_KEY => 'name',
        ),
        'Chunks' => array(
            xPDOTransport::PRESERVE_KEYS => false,
            xPDOTransport::UPDATE_OBJECT => true,
            xPDOTransport::UNIQUE_KEY => 'name',
        ),
        'Plugins' => array(
            xPDOTransport::PRESERVE_KEYS => false,
            xPDOTransport::UPDATE_OBJECT => true,
            xPDOTransport::UNIQUE_KEY => 'name',
        ),
        'PluginEvents' => array(
            xPDOTransport::PRESERVE_KEYS => true,
            xPDOTransport::UPDATE_OBJECT => false,
            xPDOTransport::UNIQUE_KEY => array('pluginid', 'event'),
        ),
    )
);

/**
 * ACCESS POLICY
 */
$attributes = array(
    xPDOTransport::PRESERVE_KEYS => false,
    xPDOTransport::UNIQUE_KEY => array('name'),
    xPDOTransport::UPDATE_OBJECT => true,
);
$policies = include $sources['data'] . 'transport.policies.php';
if (!is_array($policies)) {
    $modx->log(modX::LOG_LEVEL_FATAL, 'Adding policies failed.');
}
foreach ($policies as $policy) {
    $vehicle = $builder->createVehicle($policy, $attributes);
    $builder->putVehicle($vehicle);
}
$modx->log(modX::LOG_LEVEL_INFO, 'Packaged in ' . count($policies) . ' Access Policies.');
flush();
unset($policies, $policy, $attributes);

/**
 * ACCESS POLICY TEMPLATE
 */
$templates = include dirname(__FILE__) . '/data/transport.policytemplates.php';
$attributes = array(
    xPDOTransport::PRESERVE_KEYS => false,
    xPDOTransport::UNIQUE_KEY => array('name'),
    xPDOTransport::UPDATE_OBJECT => true,
    xPDOTransport::RELATED_OBJECTS => true,
    xPDOTransport::RELATED_OBJECT_ATTRIBUTES => array(
        'Permissions' => array(
            xPDOTransport::PRESERVE_KEYS => false,
            xPDOTransport::UPDATE_OBJECT => true,
            xPDOTransport::UNIQUE_KEY => array('template', 'name'),
        ),
    )
);
if (is_array($templates)) {
    foreach ($templates as $template) {
        $vehicle = $builder->createVehicle($template, $attributes);
        $builder->putVehicle($vehicle);
    }
    $modx->log(modX::LOG_LEVEL_INFO, 'Packaged in ' . count($templates) . ' Access Policy Templates.');
    flush();
} else {
    $modx->log(modX::LOG_LEVEL_ERROR, 'Could not package in Access Policy Templates.');
}
unset($templates, $template, $idx, $ct, $attributes);

$elementsVehicle = $builder->createVehicle($category, $elementsAttribute);

/**
 * FILE RESOLVERS
 */
$modx->log(modX::LOG_LEVEL_INFO, 'Adding in files...');
$elementsVehicle->resolve('file', array(
    'source' => $sources['source_core'],
    'target' => "return MODX_CORE_PATH . 'components/';",
));
$elementsVehicle->resolve('file', array(
    'source' => $sources['source_assets'],
    'target' => "return MODX_ASSETS_PATH . 'components/';",
));
$modx->log(modX::LOG_LEVEL_INFO, 'Adding in files done.');

/**
 * RESOLVERS
 */
$modx->log(modX::LOG_LEVEL_INFO, 'Adding in PHP resolvers...');
flush();
$elementsVehicle->resolve('php', array(
    'source' => $sources['resolvers'] . 'tables.resolver.php',
));
$elementsVehicle->resolve('php',array(
    'source' => $sources['resolvers'] . 'policy.resolver.php',
));
$modx->log(modX::LOG_LEVEL_INFO, 'Adding in PHP resolvers done.');

$builder->putVehicle($elementsVehicle);
unset($elementsVehicle);
$modx->log(modX::LOG_LEVEL_INFO, 'Packaged in Elements done.');
flush();

/**
 * license file, readme and setup options
 */
$builder->setPackageAttributes(array(
    'license' => file_get_contents($sources['docs'] . 'license.txt'),
    'readme' => file_get_contents($sources['docs'] . 'readme.txt'),
    'changelog' => file_get_contents($sources['docs'] . 'changelog.txt'),
));

$builder->pack();

$mtime = microtime();
$mtime = explode(" ", $mtime);
$mtime = $mtime[1] + $mtime[0];
$tend = $mtime;
$totalTime = ($tend - $tstart);
$totalTime = sprintf("%2.4f s", $totalTime);

$modx->log(modX::LOG_LEVEL_INFO, "\n<br />" . PKG_NAME . " package built.<br />\nExecution time: {$totalTime}\n");

exit();
