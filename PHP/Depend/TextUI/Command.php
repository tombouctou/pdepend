<?php
/**
 * This file is part of PHP_Depend.
 * 
 * PHP Version 5
 *
 * Copyright (c) 2008, Manuel Pichler <mapi@pmanuel-pichler.de>.
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 *
 *   * Redistributions of source code must retain the above copyright
 *     notice, this list of conditions and the following disclaimer.
 *
 *   * Redistributions in binary form must reproduce the above copyright
 *     notice, this list of conditions and the following disclaimer in
 *     the documentation and/or other materials provided with the
 *     distribution.
 *
 *   * Neither the name of Manuel Pichler nor the names of his
 *     contributors may be used to endorse or promote products derived
 *     from this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS
 * FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT,
 * INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING,
 * BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
 * CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT
 * LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN
 * ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 *
 * @category   QualityAssurance
 * @package    PHP_Depend
 * @subpackage TextUI
 * @author     Manuel Pichler <mapi@manuel-pichler.de>
 * @copyright  2008 Manuel Pichler. All rights reserved.
 * @license    http://www.opensource.org/licenses/bsd-license.php  BSD License
 * @version    SVN: $Id$
 * @link       http://www.manuel-pichler.de/
 */

require_once 'PHP/Depend.php';
require_once 'PHP/Depend/Log/LoggerFactory.php';
require_once 'PHP/Depend/Util/ExcludePathFilter.php';
require_once 'PHP/Depend/Util/FileExtensionFilter.php';
/**
 * 
 *
 * @category   QualityAssurance
 * @package    PHP_Depend
 * @subpackage TextUI
 * @author     Manuel Pichler <mapi@manuel-pichler.de>
 * @copyright  2008 Manuel Pichler. All rights reserved.
 * @license    http://www.opensource.org/licenses/bsd-license.php  BSD License
 * @version    Release: @package_version@
 * @link       http://www.manuel-pichler.de/
 */
class PHP_Depend_TextUI_Command
{
    private $_logOptions = null;
    
    private $_options = array();
    
    private $_directories = array();
    
    public function run()
    {
        if ($this->handleArguments() === false) {
            $this->printHelp();
            return;
        }
        
        if (isset($this->_options['--help'])) {
            $this->printHelp();
            return;            
        }
        if (isset($this->_options['--version'])) {
            $this->printVersion();
            return;
        }
        
        $pdepend = new PHP_Depend();
        $pdepend->addFilter(new PHP_Depend_Util_FileExtensionFilter(array('php', 'inc')));
        $pdepend->addFilter(new PHP_Depend_Util_ExcludePathFilter(array('.svn/', 'CVS')));
        
        try {
            foreach ($this->_directories as $dir) {
                $pdepend->addDirectory($dir);
            }
        } catch (RuntimeException $e) {
            $this->printUsage();
            echo $e->getMessage(), "\n";
            return;
        }
        
        $loggerFactory = new PHP_Depend_Log_LoggerFactory();
        
        // Get a copy of all options
        $options = $this->_options;
        
        // Get an array with all available log options
        $logOptions = $this->collectLogOptions();
        
        foreach ($options as $option => $value) {
            if (isset($logOptions[$option])) {
                // Reduce recieved option list
                unset($options[$option]);
                
                // Remove leading hyphens
                $identifier = substr($option, 2);
                
                // Create a logger
                $logger = $loggerFactory->createLogger($identifier, $value);
                
                // Register logger
                $pdepend->addLogger($logger);
            }
        }
        
        if (count($options) > 0) {
            $this->printHelp();
            echo "Unknown option '", key($options), "' given.\n";
            return;
        }
        
        $pdepend->analyze2();
        
    }
    
    protected function handleArguments()
    {
        if (!isset($_SERVER['argv'])) {
            if (false === (boolean) ini_get('register_argc_argv')) {
                echo 'Please enable register_argc_argv in your php.ini', PHP_EOL;
            } else {
                echo 'Unknown error, no $argv array available', PHP_EOL;
            }
            return false;
        }
        
        // Get cli arguments
        $argv = $_SERVER['argv'];
        
        // Remove the pdepend command line file
        array_shift($argv);
        
        if (count($argv) === 0) {
            return false;
        }
        
        // Last argument must be a list of source directories
        if (strpos(end($argv), '--') !== 0) {
            $this->_directories = explode(',', array_pop($argv));
        }
        
        foreach ($argv as $option) {
            if (strpos($option, '=') === false) {
                $value = true;
            } else {
                list($option, $value) = explode('=', $option);
            }
            $this->_options[$option] = $value;
        }
        
        return true;
    }
    
    protected function printVersion()
    {
        echo "PHP_Depend @package_version@ by Manuel Pichler\n\n";
    }
    
    protected function printUsage()
    {
        $this->printVersion();
        echo "Usage: pdepend [options] [logger] <directory>\n\n";        
    }

    protected function printHelp()
    {
        $this->printUsage();
        
        $length = $this->printLogOptions();
        
        $helpOption    = str_pad('--help', $length, ' ', STR_PAD_RIGHT);
        $versionOption = str_pad('--version', $length, ' ', STR_PAD_RIGHT);
             
        echo "  {$helpOption} Print this help text.\n",
             "  {$versionOption} Print the current PHP_Depend version.\n\n";
    }
    
    protected function printLogOptions()
    {
        $maxLength = 0;
        $options   = array();
        foreach ($this->collectLogOptions() as $option => $path) {
            // Build log option identifier
            $identifier = "{$option}=<file>";
            // Store in options array
            $options[$identifier] = (string) simplexml_load_file($path)->message;
            
            if (($length = strlen($identifier)) > $maxLength) {
                $maxLength = $length;
            }
        }
        
        ksort($options);

        $last = null;
        foreach ($options as $option => $message) {
            
            $current = substr($option, 0, strrpos($option, '-'));
            if ($last !== null && $last !== $current) {
                echo "\n";
            }
            $last = $current;
            
            $option = str_pad($option, $maxLength, ' ', STR_PAD_RIGHT);
            echo '  ', $option, ' ', $message, "\n";
        }
        echo "\n";
        
        return $maxLength;
    }
    
    protected function collectLogOptions()
    {
        if ($this->_logOptions !== null) {
            return $this->_logOptions;
        }
        
        $this->_logOptions = array();
        
        $base = realpath(dirname(__FILE__) . '/../Log');
        $dirs = new DirectoryIterator($base);
        
        foreach ($dirs as $dir) {
            if (!$dir->isDir() || substr($dir->getFilename(), 0, 1) === '.') {
                continue;
            }
            
            $files = new DirectoryIterator($dir->getPathname());
            foreach ($files as $file) {
                if (!$file->isFile()) {
                    continue;
                }
                if (substr($file->getFilename(), -4, 4) !== '.xml') {
                    continue;
                }
                
                $option = '--' . strtolower($dir->getFilename()) 
                        . '-' . strtolower(substr($file->getFilename(), 0, -4));
                        
                $this->_logOptions[$option] = $file->getPathname();
            }
        }
        return $this->_logOptions;
    }

    public static function main()
    {
        $command = new PHP_Depend_TextUI_Command();
        $command->run();
    }    
}