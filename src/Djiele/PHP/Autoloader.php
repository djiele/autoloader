<?php

namespace Djiele\PHP;

use RuntimeException;

if (!defined('__NAMESPACE_SEPARATOR__')) {
    define('__NAMESPACE_SEPARATOR__', chr(92));
}

class Autoloader
{
    private $id;
    private $useCache;
    private $classMapDir = __DIR__;
    private $folders = [__DIR__];
    private $classMap = [];

    public function __construct($id, $useCache = true)
    {
		$this->id = $id;
        $this->useCache = $useCache;
    }
    
    public function register(): void
    {
        if(0 === count($this->classMap)) {
            $classMapFile = rtrim($this->classMapDir, '\\/') . DIRECTORY_SEPARATOR . $this->id . '-classmap.php';
            if(true === $this->useCache && is_file($classMapFile) && is_readable($classMapFile)) {
                $this->classMap = include $classMapFile;
            } else {
                $this->setClassMap();
				if(true === $this->useCache) {
					if(is_writable($this->classMapDir)) {
					   file_put_contents($classMapFile, '<?php return ' . var_export($this->classMap, true) . ';', LOCK_EX);
					} else {
						trigger_error('Can not write to ' . (($dir = dirname($this->classMapDir)) ? $dir : '.') . '/' . basename($this->classMapDir), E_USER_NOTICE);
					}
				}
            }
        }

        spl_autoload_register(function($class) {
            if(array_key_exists($class, $this->classMap)) {
                $fileCandidate = $this->classMap[$class]['in_file'];
                if(is_file($fileCandidate) && is_readable($fileCandidate)) {
                    include $fileCandidate;
                    return true;
                }
            }
            return false;
        });
    }
    
    public function setFolders($folders): Autoloader
    {
        if (!is_array($folders)) {
            $this->folders = [$folders];
        } else {
            $this->folders = $folders;
        }
        
        return $this;
    }
    
    public function setClassMapDir($classMapDir): Autoloader
    {
        $this->classMapDir = $classMapDir;
        if (
            (false === realpath($this->classMapDir)) 
            && !mkdir($concurrentDirectory = $this->classMapDir, 0755, true) 
            && !is_dir($concurrentDirectory)
        ) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $concurrentDirectory));
        }
        
        return $this;
    }
	
	public function getClassInfos($class)
    {
		if (array_key_exists($class, $this->classMap)) {
			
			return $this->classMap[$class];
		}
		
		return null;
	}
	
	public function getImplementers($interface): array
    {
		$ret = [];
		if(!is_array($interface)) {
			$interface = func_get_args();
		}
		foreach ($this->classMap as $class => $data) {
			if ($interface === array_intersect($interface, $data['implements'])) {
				$ret[] = $class;
			}
		}
		
		return $ret;
	}
    
    protected function setClassMap(): void
    {
        $this->classMap = [];
		$lastClassFound = null;
        $namespaceAccumulator = '';
        while (null !== ($folder = array_shift($this->folders))) {
            $fileBuff = glob($folder.'/*');
            while (null !== ($candidate = array_shift($fileBuff))) {
               if(is_dir($candidate)) {
                   if(is_readable($candidate)) {
                       $fileBuff += glob($candidate . '/*');
                   }
               } else {
                   $tokens = token_get_all(php_strip_whitespace($candidate));
                   $currentNamespace = '';
                   $namespaceFound = false;
                   $classFound = false;
				   $classType = 'unknown';
                   foreach ($tokens as $i => $token) {
                       if (is_array($token)) {
                           if($classFound && T_WHITESPACE !== $token[0]) {
                               $isAbstract = false;
                               $isFinal = false;
                               if(isset($tokens[$i-4]) && is_array($tokens[$i-4])) {
                                   if(T_ABSTRACT === $tokens[$i-4][0]) {
                                       $isAbstract = true;
                                   } elseif(T_FINAL === $tokens[$i-4][0]) {
                                       $isFinal = true;
                                   }
                               }
                               $lastClassFound = ltrim($currentNamespace . __NAMESPACE_SEPARATOR__ . $token[1], chr(92));
                               $classInfos = [
                                   'type' => $classType,
                                   'is_final' => $isFinal,
                                   'is_abstract' => $isAbstract,
                                   'extends' => null,
                                   'implements' => [],
                                   'in_file' => $candidate
                               ];
                               if ('interface' === $classType) {
                                   unset($classInfos['implements']);
                               }
                               $this->classMap[$lastClassFound] = $classInfos;
                               $classFound = false;
                               $classType = 'unknown';
                           } elseif (T_NAMESPACE === $token[0]) {
                               $namespaceFound = true;
                               $namespaceAccumulator = '';
                           } elseif (T_CLASS === $token[0] || T_INTERFACE === $token[0] || T_TRAIT === $token[0]) {
                               $classFound = true;
                               $classType = $token[1];
                           } elseif (T_EXTENDS === $token[0]) {
                               $j = ($i +1);
                               $extendAccu = '';
                               do {
                                   $j++;
                                   if(is_array($tokens[$j])) {
                                       if(T_NS_SEPARATOR === $tokens[$j][0]) {
                                           $extendAccu .= __NAMESPACE_SEPARATOR__;
                                       } else {
                                           $extendAccu .= $tokens[$j][1];
                                       }
                                   }
                               } while(T_WHITESPACE !== $tokens[$j][0]);
                               if (false === strpos($extendAccu, __NAMESPACE_SEPARATOR__)) {
                                   $extendAccu = $currentNamespace . __NAMESPACE_SEPARATOR__ . $extendAccu;
                               }
                               $this->classMap[$lastClassFound]['extends'] = $extendAccu;
                           } elseif (T_IMPLEMENTS === $token[0]) {
                               $j = $i;
                               $interfaceAccu = '';
                               do {
                                   $j++;
                                   if(is_array($tokens[$j])) {
                                       if (T_WHITESPACE === $tokens[$j][0]) {
                                           continue;
                                       }
                                       if(T_NS_SEPARATOR === $tokens[$j][0]) {
                                           $interfaceAccu .= __NAMESPACE_SEPARATOR__;
                                       } else {
                                           $interfaceAccu .= $tokens[$j][1];
                                       }
                                   } elseif(',' === $tokens[$j]) {
                                       if (false === strpos($interfaceAccu, __NAMESPACE_SEPARATOR__)) {
                                           $interfaceAccu = $currentNamespace . __NAMESPACE_SEPARATOR__ . $interfaceAccu;
                                       }
                                       $this->classMap[$lastClassFound]['implements'][] = $interfaceAccu;
                                       $interfaceAccu = '';
                                       continue;
                                   }
                               } while('{' !== $tokens[$j+1]);
                               if('' !== $interfaceAccu) {
                                   if (false === strpos($interfaceAccu, __NAMESPACE_SEPARATOR__)) {
                                       $interfaceAccu = $currentNamespace . __NAMESPACE_SEPARATOR__ . $interfaceAccu;
                                   }
                                   $this->classMap[$lastClassFound]['implements'][] = $interfaceAccu;
                               }
                           } elseif (T_WHITESPACE === $token[0]) {
                               continue;
                           } else if($namespaceFound && T_STRING === $token[0]) {
                               $namespaceAccumulator .= $token[1] . __NAMESPACE_SEPARATOR__;
                           }
                       } elseif($namespaceFound && !is_array($token) && (';' === $token || '{' === $token)) {
                           $namespaceAccumulator = rtrim($namespaceAccumulator, __NAMESPACE_SEPARATOR__);
                           $currentNamespace = $namespaceAccumulator;
                           $namespaceFound = false;
                       }
                   }
               }
            }
        }
    }
}