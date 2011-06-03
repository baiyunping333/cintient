<?php
/*
 *
 *  Cintient, Continuous Integration made simple.
 *  Copyright (c) 2010, 2011, Pedro Mata-Mouros Fonseca
 *
 *  This file is part of Cintient.
 *
 *  Cintient is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  Cintient is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with Cintient. If not, see <http://www.gnu.org/licenses/>.
 *
 */

/**
 *
 * PHP_Depend task builder element, for invoking PHP_Depend and generating
 * an Overview Pyramid and diagram of analyzed packages. Output from the
 * pdepend.php script invocation:
 *
 *  Usage: pdepend [options] [logger] <dir[,dir[,...]]>
 *
 *    --jdepend-chart=<file>    Generates a diagram of the analyzed packages.
 *    --jdepend-xml=<file>      Generates the package dependency log.
 *
 *    --overview-pyramid=<file> Generates a chart with an Overview Pyramid for the
 *                              analyzed project.
 *
 *    --phpunit-xml=<file>      Generates a metrics xml log that is compatible with
 *                              PHPUnit --log-metrics.
 *
 *    --summary-xml=<file>      Generates a xml log with all metrics.
 *
 *    --coderank-mode=<*[,...]> Used CodeRank strategies. Comma separated list of
 *                              'inheritance'(default), 'property' and 'method'.
 *    --coverage-report=<file>  Clover style CodeCoverage report, as produced by
 *                              PHPUnit's --coverage-clover option.
 *
 *    --configuration=<file>    Optional PHP_Depend configuration file.
 *
 *    --suffix=<ext[,...]>      List of valid PHP file extensions.
 *    --ignore=<dir[,...]>      List of exclude directories.
 *    --exclude=<pkg[,...]>     List of exclude packages.
 *
 *    --without-annotations     Do not parse doc comment annotations.
 *
 *    --debug                   Prints debugging information.
 *    --help                    Print this help text.
 *    --version                 Print the current version.
 *    -d key[=value]            Sets a php.ini value.
 *
 *
 * @package Builder
 */
class BuilderElement_Task_PhpDepend extends BuilderElement
{
  protected $_includeDirs;     // A comma with no spaces separated string with all the dirs to process.
                               // PHP_Depend 0.10.5 has a problem with ~ started dirs
  protected $_excludeDirs;     // A comma with no spaces separated string with all the dirs to ignore.
  protected $_excludePackages; // A comma with no spaces separated string with all package names to ignore.
  protected $_jdependChartFile;
  protected $_overviewPyramidFile;
  protected $_summaryFile;

  public function __construct()
  {
    parent::__construct();
    $this->_includeDirs = null;
    $this->_excludeDirs = null;
    $this->_excludePackages = null;
    $this->_jdependChartFile = null;
    $this->_overviewPyramidFile = null;
    $this->_summaryFile = null;
  }
}