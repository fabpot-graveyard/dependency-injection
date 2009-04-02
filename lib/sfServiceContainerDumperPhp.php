<?php

/*
 * This file is part of the symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * sfServiceContainerDumperPhp dumps a service container as a PHP class.
 *
 * @package    symfony
 * @subpackage service
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 * @version    SVN: $Id$
 */
class sfServiceContainerDumperPhp extends sfServiceContainerDumper
{
  /**
   * Dumps the service container as a PHP class.
   *
   * Available options:
   *
   *  * class: The class name
   *
   * @param  array  $options An array of options
   *
   * @return string A PHP class representing of the service container
   */
  public function dump(array $options = array())
  {
    $class = isset($options['class']) ? $options['class'] : 'ProjectServiceContainer';

    return $this->startClass($class).$this->addServices().$this->endClass();
  }

  protected function addServiceInclude($id, $definition)
  {
    if (!is_null($definition->getFile()))
    {
      return sprintf("    require_once %s;\n\n", $this->dumpValue($definition->getFile()));
    }
  }

  protected function addServiceShared($id, $definition)
  {
    if ($definition->isShared())
    {
      return <<<EOF
    if (isset(\$this->shared['$id'])) return \$this->shared['$id'];


EOF;
    }
  }

  protected function addServiceReturn($id, $definition)
  {
    if ($definition->isShared())
    {
      return <<<EOF

    return \$this->shared['$id'] = \$instance;
  }

EOF;
    }
    else
    {
      return <<<EOF

    return \$instance;
  }

EOF;
    }
  }

  protected function addServiceInstance($id, $definition)
  {
    $class = $this->dumpValue($definition->getClass());

    if (is_string($definition->getArguments()))
    {
      $arguments = array($this->dumpValue($definition->getArguments()));
    }
    else
    {
      $arguments = array();
      foreach ($definition->getArguments() as $value)
      {
        $arguments[] = $this->dumpValue($value);
      }
    }

    if (!is_null($definition->getConstructor()))
    {
      return sprintf("    \$instance = call_user_func(array(%s, '%s'), %s);\n", $class, $definition->getConstructor(), implode(', ', $arguments));
    }
    else
    {
      if ($class != "'".$definition->getClass()."'")
      {
        return sprintf("    \$class = %s;\n    \$instance = new \$class(%s);\n", $class, implode(', ', $arguments));
      }
      else
      {
        return sprintf("    \$instance = new %s(%s);\n", $definition->getClass(), implode(', ', $arguments));
      }
    }
  }

  protected function addServiceMethodCalls($id, $definition)
  {
    $calls = '';
    foreach ($definition->getMethodCalls() as $call)
    {
      if (is_string($call[1]))
      {
        $arguments = array($this->dumpValue($call[1]));
      }
      else
      {
        $arguments = array();
        foreach ($call[1] as $value)
        {
          $arguments[] = $this->dumpValue($value);
        }
      }

      $calls .= sprintf("    \$instance->%s(%s);\n", $call[0], implode(', ', $arguments));
    }

    return $calls;
  }

  protected function addServiceConfigurator($id, $definition)
  {
    if ($callable = $definition->getConfigurator())
    {
      if (is_array($callable))
      {
        if (is_object($callable[0]) && $callable[0] instanceof sfServiceReference)
        {
          return sprintf("    %s->%s(\$instance);\n", $this->getServiceCall((string) $callable[0]), $callable[1]);
        }
        else
        {
          return sprintf("    %s::%s(\$instance);\n", $callable[0], $callable[1]);
        }
      }
      else
      {
        return sprintf("    %s(\$instance);\n", $callable);
      }
    }
  }

  protected function addService($id, $definition)
  {
    $name = sfServiceContainer::camelize($id);

    $code = <<<EOF

  protected function get{$name}Service()
  {

EOF;

    $code .= $this->addServiceInclude($id, $definition);
    $code .= $this->addServiceShared($id, $definition);
    $code .= $this->addServiceInstance($id, $definition);
    $code .= $this->addServiceMethodCalls($id, $definition);
    $code .= $this->addServiceConfigurator($id, $definition);
    $code .= $this->addServiceReturn($id, $definition);

    return $code;
  }

  protected function addServices()
  {
    $code = '';
    foreach ($this->container->getServiceDefinitions() as $id => $definition)
    {
      $code .= $this->addService($id, $definition);
    }

    return $code;
  }

  protected function startClass($class)
  {
    $code = <<<EOF
<?php

class $class extends sfServiceContainer
{
  protected \$shared = array();

EOF;

    if ($this->container->getParameters())
    {
      $code .= <<<EOF

  public function __construct()
  {
    parent::__construct(\$this->getDefaultParameters());
  }

EOF;
    }

    return $code;
  }

  protected function endClass()
  {
    $code = '';

    if ($this->container->getParameters())
    {
      $parameters = var_export($this->container->getParameters(), true);

      $code .= <<<EOF

  protected function getDefaultParameters()
  {
    return $parameters;
  }

EOF;
    }

    return $code.<<<EOF
}

EOF;
  }

  protected function dumpValue($value)
  {
    if (is_array($value))
    {
      $code = array();
      foreach ($value as $k => $v)
      {
        $code[] = sprintf("%s => %s", $this->dumpValue($k), $this->dumpValue($v));
      }

      return sprintf("array(%s)", implode(', ', $code));
    }
    elseif (is_object($value) && $value instanceof sfServiceReference)
    {
      return $this->getServiceCall((string) $value);
    }
    elseif (is_string($value))
    {
      if (preg_match('/^%([^%]+)%$/', $value, $match))
      {
        // we do this to deal with non string values (boolean, integer, ...)
        // the preg_replace_callback converts them to strings
        return sprintf("\$this->getParameter('%s')", strtolower($match[1]));
      }
      else
      {
        $code = preg_replace_callback('/(%{1,2})([^%]+)\1/', array($this, 'replaceParameter'), var_export($value, true));

        // optimize string
        $code = preg_replace(array("/^''\./", "/\.''$/", "/\.''\./"), array('', '', '.'), $code);

        return $code;
      }
    }
    elseif (is_object($value) || is_resource($value))
    {
      throw new RuntimeException('Unable to dump a service container if a parameter is an object or a resource.');
    }
    else
    {
      return var_export($value, true);
    }
  }

  public function replaceParameter($match)
  {
    if ('%%' == $match[1])
    {
      // % escaping
      return '%'.$match[2].'%';
    }

    return sprintf("'.\$this->getParameter('%s').'", strtolower($match[2]));
  }

  protected function getServiceCall($id)
  {
    if ('service_container' == $id)
    {
      return '$this';
    }

    if ($this->container->hasServiceDefinition($id))
    {
      return sprintf('$this->get%sService()', sfServiceContainer::camelize($id));
    }

    return sprintf('$this->getService(\'%s\')', $id);
  }
}
