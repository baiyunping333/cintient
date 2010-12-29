<?php
/*
 * Cintient, Continuous Integration made simple.
 * 
 * Copyright (c) 2011, Pedro Mata-Mouros <pedro.matamouros@gmail.com>
 * All rights reserved.
 * 
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 * 
 * . Redistributions of source code must retain the above copyright
 *   notice, this list of conditions and the following disclaimer.
 * 
 * . Redistributions in binary form must reproduce the above
 *   copyright notice, this list of conditions and the following
 *   disclaimer in the documentation and/or other materials provided
 *   with the distribution.
 *   
 * . Neither the name of Pedro Mata-Mouros Fonseca, Cintient, nor
 *   the names of its contributors may be used to endorse or promote
 *   products derived from this software without specific prior
 *   written permission.
 *   
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDER AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS
 * FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT,
 * INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING,
 * BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
 * CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT
 * LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN
 * ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 * 
 */

/**
 * What to consider:
 *  $GLOBALS['filesets'][<ID>]                      Holds all filesets
 *  $GLOBALS['filesets'][<ID>]['dir']               The fileset root dir
 *  $GLOBALS['filesets'][<ID>]['defaultExcludes']   Holds default exclusions (optional, default is use them)
 *  $GLOBALS['filesets'][<ID>]['exclude']           Holds files/dirs to exclude
 *  $GLOBALS['filesets'][<ID>]['include']           Holds files/dirs to include
 *  $GLOBALS['properties'][]           Holds global project properties
 *  $GLOBALS['properties'][<TASK>][]   Holds local task related properties
 *  $GLOBALS['result']['ok']           Holds the success or failure of last task's execution
 *  $GLOBALS['result']['output']       Holds the output of the last task's execution, if any
 *  $GLOBALS['result']['stacktrace']   The stacktrace of the error
 *  $GLOBALS['result']['task']         Holds the last task executed
 *  $GLOBALS['targets']['default']     Holds the name of the default target to execute
 *  $GLOBALS['targets'][]              0-index based array with all the targets in their actual execution order
 */
class BuilderConnector_Php
{
  static public function BuilderElement_Project(BuilderElement_Project $o)
  {
    $php = '';
    if (!$o->getTargets()) {
      SystemEvent::raise(SystemEvent::ERROR, 'No targets set for the project.', __METHOD__);
      return false;
    }
    if (!$o->getDefaultTarget()) {
      SystemEvent::raise(SystemEvent::ERROR, 'No default target set for the project.', __METHOD__);
      return false;
    }
    $php .= <<<EOT
//error_reporting(0);
EOT;
    if ($o->getBaseDir() !== null) {
      $php .= <<<EOT
set_include_path(get_include_path() . PATH_SEPARATOR . {$o->getBaseDir()});
EOT;
    }
    if ($o->getDefaultTarget() !== null) {
      $php .= <<<EOT
\$GLOBALS['targets'] = array();
\$GLOBALS['targetDefault'] = '{$o->getDefaultTarget()}';
\$GLOBALS['result'] = array();
\$GLOBALS['result']['ok'] = false;
\$GLOBALS['result']['output'] = '';
\$GLOBALS['result']['stacktrace'] = array();
function output(\$task, \$message)
{
  \$GLOBALS['result']['stacktrace'][] = "[" . date('H:i:s') . "] [{\$task}] {\$message}";
}
EOT;
    }
    $properties = $o->getProperties();
    if ($o->getProperties()) {
      foreach ($properties as $property) {
        $php .= self::BuilderElement_Type_Property($property);
      }
    }
    $targets = $o->getTargets();
    if ($o->getTargets()) {
      foreach ($targets as $target) {
        $php .= <<<EOT
\$GLOBALS['targets'][] = '{$target->getName()}';
EOT;
        $php .= self::BuilderElement_Target($target);
      }
    }
    $php .= <<<EOT
foreach (\$GLOBALS['targets'] as \$target) {
  output('target', "Executing target \"\$target\"...");
  if (\$target() === false) {
    \$error = error_get_last();
    \$GLOBALS['result']['output'] = \$error['message'] . ', on line ' . \$error['line'] . '.';
    output('target', "Target \"\$target\" failed.");
    return false;
  } else {
    output('target', "Target \"\$target\" executed.");
  }
}
EOT;
    return $php;
  }
  
  static public function BuilderElement_Target(BuilderElement_Target $o)
  {
    $php = '';
    if (!$o->getName()) {
      SystemEvent::raise(SystemEvent::ERROR, 'No name set for the target.', __METHOD__);
      return false;
    }
    //
    // TODO: Make sure these function names cannot colide with existing functions!
    //
    $php .= <<<EOT
function {$o->getName()}() {
EOT;
    if ($o->getProperties()) {
      $properties = $o->getProperties();
      foreach ($properties as $property) {
        $php .= <<<EOT
  {self::BuilderElement_Type_Property($property)}
EOT;
      }
    }
    if ($o->getDependencies()) {
      $deps = $o->getDependencies();
      foreach ($deps as $dep) {
        $php .= <<<EOT
  \$GLOBALS['targetDeps']['{$o->getName()}'][] = '{$dep}';
EOT;
      }
    }
    if ($o->getTasks()) {
      $tasks = $o->getTasks();
      foreach ($tasks as $task) {
        $method = get_class($task);
        $taskOutput = self::$method($task);
        $php .= <<<EOT
  {$taskOutput}
EOT;
      }
    }
    $php .= <<<EOT
}
EOT;
    return $php;
  }
  
  static public function BuilderElement_Task_Delete(BuilderElement_Task_Delete $o)
  {
    $php = '';
    if (!$o->getFilesets()) {
      SystemEvent::raise(SystemEvent::ERROR, 'No files not set for task delete.', __METHOD__);
      return false;
    }
    if ($o->getFilesets()) {
      $filesets = $o->getFilesets();
      foreach ($filesets as $fileset) {
        $php .= self::BuilderElement_Type_Fileset($fileset);
        //
        // In the following callback we assume that the fileset returns a
        // directory only *after* all it's content.
        //
        $php .= "
\$callback = function (\$entry) {
  \$ret = true;
  if (is_file(\$entry) || ({$o->getIncludeEmptyDirs()} && is_dir(\$entry))) { // includeemptydirs
    //\$ret = unlink(\$entry);
    echo \"\\n  Removing \$entry.\";
  }
  if (!\$ret) {
    output('delete', 'Failed deleting \$entry.');
  } else {
    output('delete', 'Deleted \$entry.');
  }
  return \$ret;
};
if (!fileset{$fileset->getId()}(\$callback)) {
  \$GLOBALS['result']['ok'] = false;
  if ({$o->getFailOnError()}) { // failonerror
    return false;
  }
}
";
      }
    }
    return $php;
  }
  
  static public function BuilderElement_Task_Echo(BuilderElement_Task_Echo $o)
  {
    $php = '';
    if (!$o->getMessage()) {
      SystemEvent::raise(SystemEvent::ERROR, 'Message not set for echo task.', __METHOD__);
      return false;
    }
    if ($o->getFile()) {
      $append = 'w'; // the same as append == false (default for Ant and Phing)
      if ($o->getAppend()) {
        $append = 'a';
      }
      $php .= <<<EOT
\$fp = fopen('{$o->getFile()}', '{$append}');
\$GLOBALS['result']['ok'] = (fwrite(\$fp, '{$o->getMessage()}') === false ?:true);
fclose(\$fp);
EOT;
    } else {
      $php .= <<<EOT
echo "{$o->getMessage()}\n";
output('echo', '{$o->getMessage()}');
EOT;
    }
    return $php;
  }
  
  static public function BuilderElement_Task_Exec(BuilderElement_Task_Exec $o)
  {
    $php = '';
    if (!$o->getExecutable()) {
      SystemEvent::raise(SystemEvent::ERROR, 'Executable not set for exec task.', __METHOD__);
      return false;
    }
    $dir = '';
    if ($o->getDir()) {
      $dir = "cd {$o->getDir()}; ";
    }
    $args = '';
    if ($o->getArgs()) {
      $args = ' ' . implode(' ', $o->getArgs());
    }
    $php .= "\$GLOBALS['result']['task'] = '" . __FUNCTION__ . "';
output('exec', 'Executing \"{$dir}{$o->getExecutable()}{$args}\".');
\$ret = exec('{$dir}{$o->getExecutable()}{$args}', \$lines, \$retval);
foreach (\$lines as \$line) {
  output('exec', \$line);
}
";
    if ($o->getOutputProperty()) {
      $php .= "\$GLOBALS['properties']['{$o->getOutputProperty()}'] = \$ret;
";
    }
    $php .= "if (\$retval > 0) {
  output('exec', 'Failed.');
} else {
  output('exec', 'Success.');
}
";
    //TODO: bullet proof this for boolean falses (they're not showing up)
    /*
    $php .= "if ({$o->getFailOnError()} && !\$ret) {
  \$GLOBALS['result']['ok'] = false;
  return false;
}
\$GLOBALS['result']['ok'] = true;
return true;
";*/
    return $php;
  }
  
  static public function BuilderElement_Task_Mkdir(BuilderElement_Task_Mkdir $o)
  {
    $php = '';
    if (!$o->getDir()) {
      SystemEvent::raise(SystemEvent::ERROR, 'Dir not set for mkdir task.', __METHOD__);
      return false;
    }
    $php .= "\$GLOBALS['result']['task'] = '" . __FUNCTION__ . "';
if (!file_exists('{$o->getDir()}')) {
  if (mkdir('{$o->getDir()}', " . DEFAULT_DIR_MASK . ", true) === false) {
    \$GLOBALS['result']['ok'] = false;
    output('mkdir', 'Could not create \"{$o->getDir()}\".');
    return false;
  } else {
    output('mkdir', 'Created \"{$o->getDir()}\".');
  }
} else {
  output('mkdir', '\"{$o->getDir()}\" already exists.');
}
";
    return $php;
  }
  
  static public function BuilderElement_Task_PhpLint(BuilderElement_Task_PhpLint $o)
  {
    $php = '';
    if (!$o->getFilesets()) {
      SystemEvent::raise(SystemEvent::ERROR, 'No files not set for task PHP lint.', __METHOD__);
      return false;
    }
    $php .= "\$GLOBALS['result']['task'] = '" . __FUNCTION__ . "';
output('phplint', 'Starting...');
";
    if ($o->getFilesets()) {
      $filesets = $o->getFilesets();
      foreach ($filesets as $fileset) {
        $php .= self::BuilderElement_Type_Fileset($fileset);
        //
        // In the following callback we assume that the fileset returns a
        // directory only *after* all it's content.
        //
        $php .= "
\$callback = function (\$entry) {
  if (is_file(\$entry)) {
    \$ret = null;
    \$output = array();
    exec(\"" . CINTIENT_PHP_BINARY . " -l \$entry\", \$output, \$ret);
    if (\$ret > 0) {
      \$GLOBALS['result']['ok'] = false;
      output('phplint', 'Errors parsing ' . \$entry . '.');
      return false;
    } else {
      \$GLOBALS['result']['ok'] = true;
      output('phplint', 'No syntax errors detected in ' . \$entry . '.');
    }
  }
  return true;
};
if (!fileset{$fileset->getId()}(\$callback)) {
  output('phplint', 'Failed.');
  if ({$o->getFailOnError()}) { // failonerror
    return false;
  }
} else {
  output('phplint', 'Done.');
}
";
      }
    }
    return $php;
  }
  
  static public function BuilderElement_Task_PhpUnit(BuilderElement_Task_PhpUnit $o)
  {
    $php = '';
    if (!$o->getFilesets()) {
      SystemEvent::raise(SystemEvent::ERROR, 'No files not set for task PHPUnit.', __METHOD__);
      return false;
    }
    $php .= "\$GLOBALS['result']['task'] = 'phpunit';
output('phpunit', 'Starting unit tests...');
";
    $logJunitXmlFile = '';
    if ($o->getLogJunitXmlFile()) {
      $logJunitXmlFile = ' --log-junit ' . $o->getLogJunitXmlFile();
    }
    $codeCoverageXmlFile = '';
    if ($o->getCodeCoverageXmlFile()) {
      if (!extension_loaded('xdebug')) {
        $php .= "
output('phpunit', 'Code coverage only possible with the Xdebug extension loaded. Option \"--coverage-clover\" disabled.');
";
      } else {
        $codeCoverageXmlFile = ' --coverage-clover ' . $o->getCodeCoverageXmlFile();
      }
    }
    $codeCoverageHtmlFile = '';
    if ($o->getCodeCoverageHtmlFile()) {
      if (!extension_loaded('xdebug')) {
        $php .= "
output('phpunit', 'Code coverage only possible with the Xdebug extension loaded. Option \"--coverage-html\" disabled.');
";
      } else {
        $codeCoverageHtmlFile = ' --coverage-html ' . $o->getCodeCoverageHtmlFile();
      }
    }
    if ($o->getFilesets()) {
      $filesets = $o->getFilesets();
      foreach ($filesets as $fileset) {
        $php .= self::BuilderElement_Type_Fileset($fileset);
        //
        // In the following callback we assume that the fileset returns a
        // directory only *after* all it's content.
        //
        $php .= "
\$callback = function (\$entry) {
  if (is_file(\$entry)) {
    \$ret = null;
    \$output = array();
    exec(\"" . CINTIENT_PHPUNIT_BINARY . "{$logJunitXmlFile}{$codeCoverageXmlFile}{$codeCoverageHtmlFile} \$entry\", \$output, \$ret);
    output('phpunit', \$entry . ': ' . array_pop(\$output));
    if (\$ret > 0) {
      \$GLOBALS['result']['ok'] = false;
      return false;
    } else {
      \$GLOBALS['result']['ok'] = true;
    }
  }
  return true;
};
if (!fileset{$fileset->getId()}(\$callback)) {
  output('phpunit', 'Tests failed.');
  if ({$o->getFailOnError()}) { // failonerror
    return false;
  }
} else {
  output('phpunit', 'All tests ok.');
}
";
      }
    }
    return $php;
  }
  
  /**
   * Loosely based on phing's SelectorUtils::matchPath.
   * 
   * @param BuilderElement_Type_Fileset $o
   */
  static public function BuilderElement_Type_Fileset(BuilderElement_Type_Fileset $o)
  {
    $php = '';
    //
    // Generic class to process the includes/excludes filters
    //
    //TODO: Implement $isCaseSensitive!!!!
    //TODO: Implement only a single top level class for this
    $php = "if (!class_exists('FilesetFilterIterator', false)) {
  class FilesetFilterIterator extends FilterIterator
  {
    private \$_filesetId;
    
    public function __construct(\$o, \$filesetId)
    {
      \$this->_filesetId = \$filesetId;
      parent::__construct(\$o);
    }
    
    public function accept()
    {      
      // if it is excluded promptly return false
      foreach (\$GLOBALS['filesets'][\$this->_filesetId]['exclude'] as \$exclude) {
        if (\$this->_isMatch(\$exclude)) {
          return false;
        }
      }
      // if it is included promptly return true
      foreach (\$GLOBALS['filesets'][\$this->_filesetId]['include'] as \$include) {
        if (\$this->_isMatch(\$include)) {
          return true;
        }
      }
    }
    
    private function _isMatch(\$pattern)
    {
      \$isCaseSensitive = true;
      \$rePattern = preg_quote(\$GLOBALS['filesets'][\$this->_filesetId]['dir'] . \$pattern, '/');
      \$dirSep = preg_quote(DIRECTORY_SEPARATOR, '/');
      \$patternReplacements = array(
        \$dirSep.'\*\*' => '\/?.*',
        '\*\*'.\$dirSep => '.*',
        '\*\*' => '.*',
        '\*' => '[^'.\$dirSep.']*',
        '\?' => '[^'.\$dirSep.']'
      );
      \$rePattern = str_replace(array_keys(\$patternReplacements), array_values(\$patternReplacements), \$rePattern);
      \$rePattern = '/^'.\$rePattern.'$/'.(\$isCaseSensitive ? '' : 'i');
      return (bool) preg_match(\$rePattern, \$this->current());
    }
  }
}
";
    if (!$o->getDir()) {
      SystemEvent::raise(SystemEvent::ERROR, 'Root dir not set for type fileset.', __METHOD__);
      return false;
    }
    $php .= "\$GLOBALS['filesets']['{$o->getId()}'] = array();
\$GLOBALS['filesets']['{$o->getId()}']['dir'] = '';
\$GLOBALS['filesets']['{$o->getId()}']['defaultExcludes'] = array(
  '**/*~',
  '**/#*#',
  '**/.#*',
  '**/%*%',
  '**/._*',
  '**/CVS',
  '**/CVS/**',
  '**/.cvsignore',
  '**/SCCS',
  '**/SCCS/**',
  '**/vssver.scc',
  '**/.svn',
  '**/.svn/**',
  '**/.DS_Store',
  '**/.git',
  '**/.git/**',
  '**/.gitattributes',
  '**/.gitignore',
  '**/.gitmodules',
  '**/.hg',
  '**/.hg/**',
  '**/.hgignore',
  '**/.hgsub',
  '**/.hgsubstate',
  '**/.hgtags',
  '**/.bzr',
  '**/.bzr/**',
  '**/.bzrignore',
);
\$GLOBALS['filesets']['{$o->getId()}']['exclude'] = array();
\$GLOBALS['filesets']['{$o->getId()}']['include'] = array();
";
    if ($o->getDir()) {
      $php .= "\$GLOBALS['filesets']['{$o->getId()}']['dir'] = '{$o->getDir()}';
";
    }
    if (!$o->getDefaultExcludes()) {
      $php .= "\$GLOBALS['filesets']['{$o->getId()}']['defaultExcludes'] = array();
";
    }
    if ($o->getInclude()) {
      $includes = $o->getInclude();
      foreach ($includes as $include) {
        $php .= "\$GLOBALS['filesets']['{$o->getId()}']['include'][] = '{$include}';
";
      }
    }
    if ($o->getExclude()) {
      $excludes = $o->getExclude();
      foreach ($excludes as $exclude) {
        $php .= "\$GLOBALS['filesets']['{$o->getId()}']['exclude'][] = '{$exclude}';
";
      }
    }
    //
    // Make sure RecursiveIteratorIterator::CHILD_FIRST is used, so that dirs
    // are only processed after *all* their children are.
    //
    $php .= "function fileset{$o->getId()}(\$callback)
{
  try {
    foreach (new FilesetFilterIterator(new RecursiveIteratorIterator(new RecursiveDirectoryIterator('{$o->getDir()}'), RecursiveIteratorIterator::CHILD_FIRST), '{$o->getId()}') as \$entry) {
      if (!\$callback(\$entry, '{$o->getDir()}')) {
        \$GLOBALS['result']['ok'] = false;
        \$msg = 'Callback applied to fileset returned false [CALLBACK=\$callback] [FILESET={$o->getId()}]';
        \$GLOBALS['result']['output'] = \$msg;
        //output(__METHOD__, \$msg);
        return false;
      }
    }
  } catch (UnexpectedValueException \$e) { // Typical permission denied
    \$GLOBALS['result']['ok'] = false;
    \$GLOBALS['result']['output'] = \$e->getMessage();
    output(__METHOD__, \$e->getMessage());
    return false;
  }
  return true;
";
    $php .= "
}
";
    return $php;
  }
  
  static public function BuilderElement_Type_Property(BuilderElement_Type_Property $o)
  {
    $php = '';
    if (!$o->getName() || !$o->getValue()) {
      SystemEvent::raise(SystemEvent::ERROR, 'Name and value not set for type property.', __METHOD__);
      return false;
    }
    $php .= <<<EOT
\$GLOBALS['properties']['{$o->getName()}'] = '{$o->getValue()}';
EOT;
    return $php;
  }
  
  static public function execute($code)
  {
    eval ($code);
    if ($GLOBALS['result']['ok'] !== true) {
      if (!empty($GLOBALS['result']['task'])) {
        SystemEvent::raise(SystemEvent::INFO, "Failed on specific task. [TASK={$GLOBALS['result']['task']}] [OUTPUT={$GLOBALS['result']['output']}]", __METHOD__);
        SystemEvent::raise(SystemEvent::DEBUG, "Stacktrace: " . print_r($GLOBALS['result'], true), __METHOD__);
      } else {
        SystemEvent::raise(SystemEvent::INFO, "Failed for unknown reasons. [OUTPUT={$GLOBALS['result']['output']}]", __METHOD__);
      }
      return false;
    }
    return true;
  }
}