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
 * @subpackage Metrics
 * @author     Manuel Pichler <mapi@manuel-pichler.de>
 * @copyright  2008 Manuel Pichler. All rights reserved.
 * @license    http://www.opensource.org/licenses/bsd-license.php  BSD License
 * @version    SVN: $Id$
 * @link       http://www.manuel-pichler.de/
 */

require_once 'PHP/Depend/Code/NodeVisitor/AbstractDefaultVisitor.php';
require_once 'PHP/Depend/Metrics/AnalyzerI.php';
require_once 'PHP/Depend/Metrics/NodeAwareI.php';
require_once 'PHP/Depend/Metrics/ProjectAwareI.php';

/**
 * This analyzer collects different lines of code metrics.
 * 
 * It collects the total Lines Of Code(<b>loc</b>), the None Comment Lines Of
 * Code(<b>ncloc) and the Comment Lines Of Code(<b>cloc</b>) for files, classes, 
 * interfaces, methods, properties and function.
 * 
 * The current implementation has a limitation, that affects inline comments. 
 * The following code will suppress one line of code.
 * 
 * <code>
 * function foo() {
 *     foobar(); // Bad behaviour...
 * }
 * </code>
 * 
 * The same rule applies to class methods. mapi, <b>PLEASE, FIX THIS ISSUE.</b>
 *
 * @category   QualityAssurance
 * @package    PHP_Depend
 * @subpackage Metrics
 * @author     Manuel Pichler <mapi@manuel-pichler.de>
 * @copyright  2008 Manuel Pichler. All rights reserved.
 * @license    http://www.opensource.org/licenses/bsd-license.php  BSD License
 * @version    Release: @package_version@
 * @link       http://www.manuel-pichler.de/
 */
class PHP_Depend_Metrics_NodeLoc_Analyzer
       extends PHP_Depend_Code_NodeVisitor_AbstractDefaultVisitor
    implements PHP_Depend_Metrics_AnalyzerI,
               PHP_Depend_Metrics_NodeAwareI,
               PHP_Depend_Metrics_ProjectAwareI
{
    /**
     * Collected node metrics
     *
     * @type array<array>
     * @var array(string=>array) $_nodeMetrics
     */
    private $_nodeMetrics = null;
    
    /**
     * Collected project metrics.
     *
     * @type array<array>
     * @var array(string=>integer) $_projectMetrics
     */
    private $_projectMetrics = array(
        'loc'    =>  0,
        'cloc'   =>  0,
        'ncloc'  =>  0
    );
    
    /**
     * This method will return an <b>array</b> with all generated metric values 
     * for the given <b>$node</b> instance. If there are no metrics for the 
     * requested node, this method will return an empty <b>array</b>.
     * 
     * <code>
     * array(
     *     'loc'    =>  23,
     *     'cloc'   =>  17,
     *     'ncloc'  =>  42
     * )
     * </code>
     *
     * @param PHP_Depend_Code_NodeI $node The context node instance.
     * 
     * @return array(string=>mixed)
     */
    public function getNodeMetrics(PHP_Depend_Code_NodeI $node)
    {
        if (isset($this->_nodeMetrics[$node->getUUID()])) {
            return $this->_nodeMetrics[$node->getUUID()];
        }
        return array();
    }
    
    /**
     * Provides the project summary as an <b>array</b>.
     * 
     * <code>
     * array(
     *     'loc'    =>  23,
     *     'cloc'   =>  17,
     *     'ncloc'  =>  42
     * )
     * </code>
     *
     * @return array(string=>mixed)
     */
    public function getProjectMetrics()
    {
        return $this->_projectMetrics;
    }
    
    /**
     * Processes all {@link PHP_Depend_Code_Package} code nodes.
     *
     * @param PHP_Depend_Code_NodeIterator $packages All code packages.
     * 
     * @return void
     */
    public function analyze(PHP_Depend_Code_NodeIterator $packages)
    {
        // Check for previous run
        if ($this->_nodeMetrics === null) {
            // Init node metrics
            $this->_nodeMetrics = array();
            
            // Process all packages
            foreach ($packages as $package) {
                $package->accept($this);
            }
        }
    }
    
    /**
     * Visits a class node. 
     *
     * @param PHP_Depend_Code_Class $class The current class node.
     * 
     * @return void
     * @see PHP_Depend_Code_NodeVisitor_AbstractDefaultVisitor::visitClass()
     */
    public function visitClass(PHP_Depend_Code_Class $class)
    {
        $class->getSourceFile()->accept($this);
        
        $loc = $class->getEndLine() - $class->getStartLine() + 1;
        
        $this->_nodeMetrics[$class->getUUID()] = array(
            'loc'    =>  $loc,
            'cloc'   =>  0,
            'ncloc'  =>  $loc,
        );

        foreach ($class->getMethods() as $method) {
            $method->accept($this);
        }
        foreach ($class->getProperties() as $property) {
            $property->accept($this);
        }
        
        $cloc = 0;
        if (($comment = $class->getDocComment()) !== null) {
            $cloc = substr_count($comment, "\n") + 1;
        }
        $this->_updateFileLoc($class->getSourceFile(), $cloc);
    }
    
    /**
     * Visits a file node. 
     *
     * @param PHP_Depend_Code_File $file The current file node.
     * 
     * @return void
     * @see PHP_Depend_Code_NodeVisitor_AbstractDefaultVisitor::visitFile()
     */
    public function visitFile(PHP_Depend_Code_File $file)
    {
        // Skip for dummy files
        if ($file->getFileName() === null) {
            return;
        }
        // Check for initial file
        $uuid = $file->getUUID();
        if (isset($this->_nodeMetrics[$uuid])) {
            return;
        }
        
        $cloc = 0;
        if (($comment = $file->getDocComment()) !== null) {
            $cloc = count(explode("\n", $comment));
        }
        
        $loc   = count($file->getLoc());
        $ncloc = $loc - $cloc; 
        
        $this->_nodeMetrics[$uuid] = array(
            'loc'    =>  $loc,
            'cloc'   =>  $cloc,
            'ncloc'  =>  $ncloc
        );
        
        // Update project metrics
        $this->_projectMetrics['loc']   += $loc;
        $this->_projectMetrics['cloc']  += $cloc;
        $this->_projectMetrics['ncloc'] += $ncloc;
    }
    
    /**
     * Visits a function node. 
     *
     * @param PHP_Depend_Code_Function $function The current function node.
     * 
     * @return void
     * @see PHP_Depend_Code_NodeVisitor_AbstractDefaultVisitor::visitFunction()
     */
    public function visitFunction(PHP_Depend_Code_Function $function)
    {
        $function->getSourceFile()->accept($this);
        
        $cloc = 0;
        foreach ($function->getTokens() as $token) {
            if ($token[0] === PHP_Depend_Code_TokenizerI::T_COMMENT) {
                ++$cloc;
            } else if ($token[0] === PHP_Depend_Code_TokenizerI::T_DOC_COMMENT) {
                $cloc += count(explode("\n", $token[1]));
            }
        }
        
        $loc   = $function->getEndLine() - $function->getStartLine() + 1;
        $ncloc = $loc - $cloc;
        
        $this->_nodeMetrics[$function->getUUID()] = array(
            'loc'    =>  $loc,
            'cloc'   =>  $cloc,
            'ncloc'  =>  $ncloc
        );

        if (($comment = $function->getDocComment()) !== null) {
            $cloc += count(explode("\n", $comment));
        }
        $this->_updateFileLoc($function->getSourceFile(), $cloc);
    }
    
    /**
     * Visits a code interface object.
     *
     * @param PHP_Depend_Code_Interface $interface The context code interface.
     * 
     * @return void
     * @see PHP_Depend_Code_NodeVisitor_AbstractDefaultVisitor::visitInterface()
     */
    public function visitInterface(PHP_Depend_Code_Interface $interface)
    {
        $interface->getSourceFile()->accept($this);
        
        $loc = $interface->getEndLine() - $interface->getStartLine() + 1;

        $this->_nodeMetrics[$interface->getUUID()] = array(
            'loc'    =>  $loc,
            'cloc'   =>  0,
            'ncloc'  =>  $loc,
        );

        $cloc = 0;
        if (($comment = $interface->getDocComment()) !== null) {
            $cloc = substr_count($comment, "\n") + 1;
        }
        $this->_updateFileLoc($interface->getSourceFile(), $cloc);
        
        foreach ($interface->getMethods() as $method) {
            $method->accept($this);
        }
    }
    
    /**
     * Visits a method node. 
     *
     * @param PHP_Depend_Code_Class $method The method class node.
     * 
     * @return void
     * @see PHP_Depend_Code_NodeVisitor_AbstractDefaultVisitor::visitMethod()
     */
    public function visitMethod(PHP_Depend_Code_Method $method)
    {
        $cloc = 0;
        foreach ($method->getTokens() as $token) {
            if ($token[0] === PHP_Depend_Code_TokenizerI::T_COMMENT) {
                ++$cloc;
            } else if ($token[0] === PHP_Depend_Code_TokenizerI::T_DOC_COMMENT) {
                $cloc += count(explode("\n", $token[1]));
            }
        }

        $loc   = $method->getEndLine() - $method->getStartLine() + 1;
        $ncloc = $loc - $cloc;
        
        $this->_nodeMetrics[$method->getUUID()] = array(
            'loc'    =>  $loc,
            'cloc'   =>  $cloc,
            'ncloc'  =>  $ncloc
        );

        if (($comment = $method->getDocComment()) !== null) {
            $cloc += substr_count($comment, "\n") + 1;
        }
        $this->_updateParentLoc($method->getParent(), $cloc);
    }
    
    /**
     * Visits a property node. 
     *
     * @param PHP_Depend_Code_Property $property The property class node.
     * 
     * @return void
     * @see PHP_Depend_Code_NodeVisitor_AbstractDefaultVisitor::visitProperty()
     */
    public function visitProperty(PHP_Depend_Code_Property $property)
    {
        $this->_nodeMetrics[$property->getUUID()] = array(
            'loc'    =>  1,
            'cloc'   =>  0,
            'ncloc'  =>  1
        );
        
        $cloc = 0;
        if (($comment = $property->getDocComment()) !== null) {
            $cloc = substr_count($comment, "\n") + 1;
        }
        $this->_updateParentLoc($property->getParent(), $cloc);
    }
    
    /**
     * Updates the <b>cloc</b> and <b>ncloc</b> values of a parent node. 
     *
     * @param PHP_Depend_Code_AbstractItem $item The parent node instance.
     * @param integer                      $cloc The cloc value.
     * 
     * @return void
     */
    private function _updateParentLoc(PHP_Depend_Code_AbstractItem $item, $cloc)
    {
        if (isset($this->_nodeMetrics[$item->getUUID()])) {
            // Update parent node metrics
            $this->_nodeMetrics[$item->getUUID()]['cloc']  += $cloc;
            $this->_nodeMetrics[$item->getUUID()]['ncloc'] -= $cloc;
        
            // Update source file metrics
            $this->_updateFileLoc($item->getSourceFile(), $cloc);
        }
    }
    
    /**
     * Updates the <b>cloc</b> and <b>ncloc</b> values of a file. 
     *
     * @param PHP_Depend_Code_File $file The parent file instance.
     * @param integer              $cloc The cloc value.
     * 
     * @return void
     */
    private function _updateFileLoc(PHP_Depend_Code_File $file, $cloc)
    {
        if (isset($this->_nodeMetrics[$file->getUUID()])) {
            // Update file node metrics
            $this->_nodeMetrics[$file->getUUID()]['cloc']  += $cloc;
            $this->_nodeMetrics[$file->getUUID()]['ncloc'] -= $cloc;
        
            // Update project metrics
            $this->_projectMetrics['cloc']  += $cloc;
            $this->_projectMetrics['ncloc'] -= $cloc;
        }
    }
}