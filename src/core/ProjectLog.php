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
 * 
 */
class ProjectLog
{
  private $_id;           // the build's incremental ID
  private $_date;         // the build's date
  private $_type;         // the label on the build, also used to name the release package file
  private $_message;      // a user generated description text (prior or after the build triggered).
  private $_username;     // The username that triggered the log entry
  private $_projectId;    // goes into the table name - it's not an attribute
  private $_signature;    // Internal flag to control whether a save to database is required

  /**
   * Magic method implementation for calling vanilla getters and setters. This
   * is rigged to work only with private/protected non-static class variables
   * whose nomenclature follows the Zend Coding Standard.
   * 
   * @param $name
   * @param $args
   */
  public function __call($name, $args)
  {
    if (strpos($name, 'get') === 0) {
      $var = '_' . lcfirst(substr($name, 3));
      return $this->$var;
    } elseif (strpos($name, 'set') === 0) {
      $var = '_' . lcfirst(substr($name, 3));
      $this->$var = $args[0];
      return true;
    }
    return false;
  }
  
  public function __construct($projectId)
  {
    $this->_projectId = $projectId;
    $this->_id = null;
    $this->_date = null;
    $this->_type = '';
    $this->_message = '';
    $this->_username = '';
    $this->_signature = null;
  }
  
  public function __destruct()
  {
    $this->_save();
  }
  
  public function delete()
  {
    $sql = "DROP TABLE projectlog{$this->getProjectId()}";
    if (!Database::execute($sql)) {
      SystemEvent::raise(SystemEvent::ERROR, "Couldn't delete project log table. [TABLE={$this->getProjectId()}]", __METHOD__);
      return false;
    }
    return true;
  }
  
  private function _getCurrentSignature()
  {
    $arr = get_object_vars($this);
    $arr['_signature'] = null;
    unset($arr['_signature']);
    return md5(serialize($arr));
  }
  
  private function _save($force=false)
  {
    if ($this->_getCurrentSignature() == $this->_signature && !$force) {
      SystemEvent::raise(SystemEvent::DEBUG, "Save called, but no saving is required.", __METHOD__);
      return false;
    }
    if (!Database::beginTransaction()) {
      return false;
    }
    $sql = 'INSERT INTO projectlog' . $this->getProjectId()
         . ' (type, message, username)'
         . ' VALUES (?,?,?)';
    $val = array(
      $this->getType(),
      $this->getMessage(),
      $this->getUsername(),
    );
    if (!($id = Database::insert($sql, $val)) || !is_numeric($id)) {
      Database::rollbackTransaction();
      SystemEvent::raise(SystemEvent::ERROR, "Problems saving to db.", __METHOD__);
      return false;
    }
    $this->setId($id);
    
    if (!Database::endTransaction()) {
      SystemEvent::raise(SystemEvent::ERROR, "Something occurred while finishing transaction. The project log might not have been saved. [PID={$this->getProjectId()}]", __METHOD__);
      return false;
    }
    #if DEBUG
    SystemEvent::raise(SystemEvent::DEBUG, "Saved project log. [PID={$this->getProjectId()}]", __METHOD__);
    #endif
    $this->updateSignature();
    return true;
  }
  
  public function updateSignature()
  {
    $this->setSignature($this->_getCurrentSignature());
  }
  
  static public function write($msg)
  {
    //
    // Oh boy... there goes proper layer separation...
    //
    $projectLog = new ProjectLog($_SESSION['project']->getId());
    $projectLog->setType(0);
    $projectLog->setMessage($msg);
    $projectLog->setUsername($_SESSION['user']->getUsername());
  }
  
  static public function getListByProject($project, $user, $access = Access::READ, array $options = array())
  {
    isset($options['sort'])?:$options['sort']=Sort::DATE_DESC;
    isset($options['pageStart'])?:$options['pageStart']=0;
    isset($options['pageLength'])?:$options['pageLength']=CINTIENT_BUILDS_PAGE_LENGTH;
    
    $ret = false;
    $access = (int)$access; // Unfortunately, no enums, no type hinting, no cry.
    $sql = 'SELECT pl.*'
         . ' FROM projectlog' . $project->getId() . ' pl, projectuser pu'
         . ' WHERE pu.projectid=?'
         . ' AND pu.userid=?'
         . ' AND pu.access & ?';
    if ($options['sort'] != Sort::NONE) {
      $sql .= ' ORDER BY';
      switch ($options['sort']) {
        case Sort::DATE_ASC:
          $sql .= ' pl.id ASC';
          break;
        case Sort::DATE_DESC:
          $sql .= ' pl.id DESC';
      }
    }
    $sql .= ' LIMIT ?, ?';
    $val = array($project->getId(), $user->getId(), $access, $options['pageStart'], $options['pageLength']);
    if ($rs = Database::query($sql, $val)) {
      $ret = array();
      while ($rs->nextRow()) {
        $projectLog = self::_getObject($rs, $project->getId());
        $ret[] = $projectLog;
      }
    }
    return $ret;
  }
  
  static private function _getObject(Resultset $rs, $projectId)
  {
    $ret = new ProjectLog($projectId);
    $ret->setId($rs->getId());
    $ret->setDate($rs->getDate());
    $ret->setType($rs->getType());
    $ret->setMessage($rs->getMessage());
    $ret->setUsername($rs->getUsername());
    
    $ret->updateSignature();
    return $ret;
  }
  
  static public function install($projectId)
  {
    $sql = <<<EOT
CREATE TABLE IF NOT EXISTS projectlog{$projectId} (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  date DATETIME DEFAULT CURRENT_TIMESTAMP,
  type TINYINT DEFAULT 0,
  message TEXT DEFAULT '',
  username VARCHAR(20) NOT NULL DEFAULT ''
);
EOT;
    if (!Database::execute($sql)) {
      SystemEvent::raise(SystemEvent::ERROR, "Problems creating table. [TABLE={$projectId}]", __METHOD__);
      return false;
    }
    return true;
  }
  
  static public function uninstall($projectId)
  {
    $sql = "DROP TABLE projectlog{$projectId}";
    if (!Database::execute($sql)) {
      SystemEvent::raise(SystemEvent::ERROR, "Couldn't delete project log table. [TABLE={$projectId}]", __METHOD__);
      return false;
    }
    return true;
  }
}