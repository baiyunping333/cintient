<?php
/*
 * 
 * Cintient, Continuous Integration made simple.
 * Copyright (c) 2011, Pedro Mata-Mouros Fonseca
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
 * Usage:
 * 
 * $exec = new BuilderElement_Task_Exec();
 * $exec->setExecutable('php');
 * $exec->setArgs(array('runMe.php arg1 arg2'));
 * $exec->setDir('/tmp/');
 * $exec->setOutputProperty('fooBar');
 * echo $exec->toString('ant');
 */
class BuilderElement_Task_Exec extends BuilderElementAbstract
{
  protected $_executable; 
  protected $_args;            // The arguments to the executable command, if any, a space separated string
  protected $_dir;             // The directory in which the command should be executed in
  protected $_outputProperty;  // Log the command's output to the variable with this name
  protected $_failOnError;     // Stop the build if the command fails (Ant only)
  
  public function __construct()
  {
    $this->_executable = null;
    $this->_args = null;
    $this->_dir = null;
    $this->_outputProperty = null;
    $this->_failOnError = null;
  }
}