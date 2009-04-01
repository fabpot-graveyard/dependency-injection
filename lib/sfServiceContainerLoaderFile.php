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
 * sfServiceContainerLoader is the abstract class used by all built-in loaders that are file based.
 *
 * @package    symfony
 * @subpackage service
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 * @version    SVN: $Id$
 */
abstract class sfServiceContainerLoaderFile extends sfServiceContainerLoader
{
  protected
    $paths = array();

  /**
   * Constructor.
   *
   * @param sfServiceContainerBuilder $container A sfServiceContainerBuilder instance
   * @param array                     $paths An array of paths where to look for resources
   */
  public function __construct(sfServiceContainerBuilder $container = null, $paths = array())
  {
    parent::__construct($container);

    if (!is_array($paths))
    {
      $paths = array($paths);
    }

    $this->paths = $paths;
  }

  protected function getAbsolutePath($file, $currentPath = null)
  {
    if (self::isAbsolutePath($file))
    {
      return $file;
    }
    else if (!is_null($currentPath) && file_exists($currentPath.DIRECTORY_SEPARATOR.$file))
    {
      return $currentPath.DIRECTORY_SEPARATOR.$file;
    }
    else
    {
      foreach ($this->paths as $path)
      {
        if (file_exists($path.DIRECTORY_SEPARATOR.$file))
        {
          return $path.DIRECTORY_SEPARATOR.$file;
        }
      }
    }

    return $file;
  }

  static protected function isAbsolutePath($file)
  {
    if ($file[0] == '/' || $file[0] == '\\' ||
        (strlen($file) > 3 && ctype_alpha($file[0]) &&
         $file[1] == ':' &&
         ($file[2] == '\\' || $file[2] == '/')
        )
       )
    {
      return true;
    }

    return false;
  }
}
