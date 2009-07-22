<?php

require_once dirname(__FILE__).'/../includes/classes.php';

$container = new sfServiceContainerBuilder();
$container->
  register('foo', 'FooClass')->
  setConstructor('getInstance')->
  setArguments(array('foo', new sfServiceReference('foo.baz'), array('%foo%' => 'foo is %foo%'), true, new sfServiceReference('service_container')))->
  setFile(realpath(dirname(__FILE__).'/../includes/foo.php'))->
  setShared(false)->
  addMethodCall('setBar', array('bar'))->
  addMethodCall('initialize')->
  setConfigurator('sc_configure')
;
$container->
  register('bar', 'FooClass')->
  setArguments(array('foo', new sfServiceReference('foo.baz')))->
  setShared(true)->
  setConfigurator(array(new sfServiceReference('foo.baz'), 'configure'))
;
$container->
  register('foo.baz', '%baz_class%')->
  setConfigurator(array('%baz_class%', 'configureStatic1'))
;
$container->register('foo_bar', 'FooClass');
$container->setParameters(array(
  'baz_class' => 'BazClass',
  'foo' => 'bar',
));
$container->setAlias('alias_for_foo', 'foo');

return $container;
