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
				if(false === $this->noCache) {
					if(is_writable($this->classMapDir)) {
					   file_put_contents($classMapFile, '<?php return ' . var_export($this->classMap, true) . ';', LOCK_EX);
					} else {
						trigger_error('Can not write to ' . $classMapDir, E_USER_NOTICE);
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
	
	public function getClassInfos($class) {
		if (array_key_exists($class, $this->classMap)) {
			
			return $this->classMap[$class];
		}
		
		return null;
	}
	
	public function getImplementers($interface) {
		$ret = [];
		if(!is_array($interface)) {
			$interface = func_get_args();
		}
		foreach ($this->classMap as $class => $data) {
			if ($interface == array_intersect($interface, $data['implements'])) {
				$ret[] = $class;
			}
		}
		
		return $ret;
	}
    
    protected function setClassMap() 
    {
        $this->classMap = [];
        $ltrimLen = strlen(__DIR__)+1;
		$lastClassFound = null;
        
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
				   $classType = 'unknown';
                   foreach ($tokens as $i => $token) {
                        if (is_array($token) && T_NAMESPACE == $token[0]) {
                            $namespaceFound = true;
                            $namespaceAccu = '';
                        } elseif (is_array($token) && (T_CLASS == $token[0] || T_INTERFACE == $token[0] || T_TRAIT == $token[0])) {
                            $classFound = true;
							if (T_CLASS == $token[0]) {
								$classType = 'class';
							} elseif(T_INTERFACE == $token[0]) {
								$classType = 'interface';
							} elseif(T_TRAIT == $token[0]) {
								$classType = 'trait';
							} else {
								$classType = 'unknown';
							}
						} elseif (is_array($token) && T_EXTENDS == $token[0]) {
							$j = ($i +1);
							$extendAccu = '';
							do {
								$j++;
								if(is_array($tokens[$j])) {
									if(T_NS_SEPARATOR == $tokens[$j][0]) {
										$extendAccu .= '\\';
									} else {
										$extendAccu .= $tokens[$j][1];
									}
								}
							} while(T_WHITESPACE !== $tokens[$j][0]);
							if (false === strpos($extendAccu, '\\')) {
								$extendAccu = $currentNamespace . '\\' . $extendAccu;
							}
							$this->classMap[$lastClassFound]['extends'] = $extendAccu;
						} elseif (is_array($token) && T_IMPLEMENTS == $token[0]) {
							$j = $i;
							$interfaceAccu = '';
							do {
								$j++;
								if(is_array($tokens[$j])) {
									if(T_WHITESPACE == $tokens[$j][0]) {
										continue;
									} elseif(T_NS_SEPARATOR == $tokens[$j][0]) {
										$interfaceAccu .= '\\';
									} else {
										$interfaceAccu .= $tokens[$j][1];
									}
								} elseif(',' == $tokens[$j]) {
									if (false === strpos($interfaceAccu, '\\')) {
										$interfaceAccu = $currentNamespace . '\\' . $interfaceAccu;
									}
									$this->classMap[$lastClassFound]['implements'][] = $interfaceAccu;
									$interfaceAccu = '';
									continue;
								}
							} while('{' !== $tokens[$j+1]);
							if('' != $interfaceAccu) {
								if (false === strpos($interfaceAccu, '\\')) {
									$interfaceAccu = $currentNamespace . '\\' . $interfaceAccu;
								}
								$this->classMap[$lastClassFound]['implements'][] = $interfaceAccu;
							}
                        } elseif (is_array($token) && T_WHITESPACE == $token[0]) {
                            continue;
                        } else if($namespaceFound && is_array($token) && T_STRING == $token[0]) {
                            $namespaceAccu .= $token[1].'\\';
                        } elseif($namespaceFound && !is_array($token) && (';' == $token || '{' == $token)) {
                            $namespaceAccu = rtrim($namespaceAccu, '\\');
                            $currentNamespace = $namespaceAccu;
                            $namespaceFound = false;
                        } elseif($classFound) {
							$isAbstract = false;
							$isFinal = false;
							if(isset($tokens[$i-4]) && is_array($tokens[$i-4])) {
								if(T_ABSTRACT == $tokens[$i-4][0]) {
									$isAbstract = true;
								} elseif(T_FINAL == $tokens[$i-4][0]) {
									$isFinal = true;
								}
							}
							$lastClassFound = ltrim($currentNamespace . '\\' . $token[1], '\\');
                            $this->classMap[$lastClassFound] = [
								'type' => $classType,
								'is_final' => $isFinal,
								'is_abstract' => $isAbstract,
								'extends' => null,
								'implements' => [],
								'in_file' => $candidate
							];
                            $classFound = false;
							$classType = 'unknown';
                        }
                   }
               }
            }
        }
    }
}