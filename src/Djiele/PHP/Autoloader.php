<?php

namespace Djiele\PHP;

class Autoloader
{
    private $noCache;
    private $classMapDir = __DIR__;
    private $folders = [__DIR__];
    private $classMap = null;
    
    public function __construct($noCache = false)
    {
        $this->noCache = $noCache;
    }
    
    public function register()
    {
        if(null == $this->classMap) {
            $classMapFile = rtrim($this->classMapDir, '\\/') . DIRECTORY_SEPARATOR . 'autoloader-classmap.php';
            if(false === $this->noCache && is_file($classMapFile) && is_readable($classMapFile)) {
                $this->classMap = include $classMapFile;
            } else {
                self::setClassMap();
                if(is_writable($this->classMapDir)) {
                   file_put_contents($classMapFile, '<?php return ' . var_export($this->classMap, true) . ';', LOCK_EX);
                } else {
                    trigger_error('Can not write to ' . $classMapDir, E_USER_NOTICE);
                }
            }
        }

        spl_autoload_register(function($class) {
            if(array_key_exists($class, $this->classMap)) {
                $fileCandidate = $this->classMap[$class];
                if(is_file($fileCandidate) && is_readable($fileCandidate)) {
                    include $fileCandidate;
                    return true;
                }
            }
            return false;
        });
    }
    
    public function setFolders($folders) {
        if (!is_array($folders)) {
            $this->folders = [$folders];
        } else {
            $this->folders = $folders;
        }
        
        return $this;
    }
    
    public function setClassMapDir($classMapDir)
    {
        $this->classMapDir = $classMapDir;
        if (false === realpath($this->classMapDir)) {
            mkdir($this->classMapDir, 0777, true);
        }
        
        return $this;
    }
    
    protected function setClassMap() 
    {
        $this->classMap = [];
        $ltrimLen = strlen(__DIR__)+1;
        
        while (null !== ($folder = array_shift($this->folders))) {
            
            $fileBuff = glob($folder.'/*');

            while (null !== ($candidate = array_shift($fileBuff))) {
               if(is_dir($candidate)) {
                   if(is_readable($candidate)) {
                       $fileBuff = array_merge($fileBuff, glob($candidate.'/*'));
                   }
               } else {
                   $tokens = token_get_all(php_strip_whitespace($candidate));
                   $currentNamespace = '';
                   $namespaceFound = false;
                   $classFound = false;
                   foreach ($tokens as $i => $token) {
                        if (is_array($token) && T_NAMESPACE == $token[0]) {
                            $namespaceFound = true;
                            $namespaceAccu = '';
                        } elseif (is_array($token) && (T_CLASS == $token[0] || T_INTERFACE == $token[0] || T_TRAIT == $token[0])) {
                            $classFound = true;
                        } elseif (is_array($token) && T_WHITESPACE == $token[0]) {
                            continue;
                        } else if($namespaceFound && is_array($token) && T_STRING == $token[0]) {
                            $namespaceAccu .= $token[1].'\\';
                        } elseif($namespaceFound && !is_array($token) && (';' == $token || '{' == $token)) {
                            $namespaceAccu = rtrim($namespaceAccu, '\\');
                            $currentNamespace = $namespaceAccu;
                            $namespaceFound = false;
                        } elseif($classFound) {
                            $this->classMap[ltrim($currentNamespace . '\\' . $token[1], '\\')] = $candidate; //substr($candidate, $ltrimLen);
                            $classFound = false;
                        }
                   }
               }
            }
        }
    }
}