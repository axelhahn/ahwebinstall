<?php

/*
 * AXEL HAHN's PHP WEB INSTALLER
 * www.axel-hahn.de
 * 
 * G E N E R A T O R
 * 
 * STATUS: alpha - do not use yet
 *
 * @author Axel Hahn
 */

class ahwigenerator {
    // ----------------------------------------------------------------------
    // INTERNAL CONFIG
    // ----------------------------------------------------------------------

    /**
     * ahwi source url
     * @var string
     */
    protected $_sSourceUrl = "https://github.com/axelhahn/ahwebinstall";

    /**
     * filename of the installer class; this will be included in the generator
     * to build the installer
     * @var string
     */
    protected $_sInstallerClass = "ahwi-installer.class.php";

    /**
     * project dir
     * @var string
     */
    protected $_sPrjDir = false;

    /**
     * output dir
     * @var string
     */
    protected $_sOutDir = false;
    
    /**
     * flag: compress file $_sInstallerClass in generated installer?
     * suggestion: true
     * @var type 
     */
    protected $_bCompressInstaller = true;

    // ----------------------------------------------------------------------
    // INIT
    // ----------------------------------------------------------------------

    /**
     * init ahwigenerator
     * @return boolean
     */
    public function __construct() {
        $sBasedir = dirname(__DIR__);
        $this->_sOutDir = str_replace('\\', '/', $sBasedir . '/output/');
        $this->_sPrjDir = str_replace('\\', '/', $sBasedir . '/projects/');

        return true;
    }

    // ----------------------------------------------------------------------
    // PRIVATE METHODS
    // ----------------------------------------------------------------------

    /**
     * minify php string
     * SOURCE: http://php.net/manual/en/function.php-strip-whitespace.php
     * with removed lowercase function
     * 
     * @staticvar array $IW
     * @param string $src  php code
     * @return string
     */
    protected function _compress_php_src($src) {
        if(!$this->_bCompressInstaller){
            return $src;
        }
        // Whitespaces left and right from this signs can be ignored
        static $IW = array(
            T_CONCAT_EQUAL, // .=
            T_DOUBLE_ARROW, // =>
            T_BOOLEAN_AND, // &&
            T_BOOLEAN_OR, // ||
            T_IS_EQUAL, // ==
            T_IS_NOT_EQUAL, // != or <>
            T_IS_SMALLER_OR_EQUAL, // <=
            T_IS_GREATER_OR_EQUAL, // >=
            T_INC, // ++
            T_DEC, // --
            T_PLUS_EQUAL, // +=
            T_MINUS_EQUAL, // -=
            T_MUL_EQUAL, // *=
            T_DIV_EQUAL, // /=
            T_IS_IDENTICAL, // ===
            T_IS_NOT_IDENTICAL, // !==
            T_DOUBLE_COLON, // ::
            T_PAAMAYIM_NEKUDOTAYIM, // ::
            T_OBJECT_OPERATOR, // ->
            T_DOLLAR_OPEN_CURLY_BRACES, // ${
            T_AND_EQUAL, // &=
            T_MOD_EQUAL, // %=
            T_XOR_EQUAL, // ^=
            T_OR_EQUAL, // |=
            T_SL, // <<
            T_SR, // >>
            T_SL_EQUAL, // <<=
            T_SR_EQUAL, // >>=
        );
        if (is_file($src)) {
            if (!$src = file_get_contents($src)) {
                return false;
            }
        }
        $tokens = token_get_all($src);

        $new = "";
        $c = sizeof($tokens);
        $iw = false; // ignore whitespace
        $ih = false; // in HEREDOC
        $ls = "";    // last sign
        $ot = null;  // open tag
        for ($i = 0; $i < $c; $i++) {
            $token = $tokens[$i];
            if (is_array($token)) {
                list($tn, $ts) = $token; // tokens: number, string, line
                $tname = token_name($tn);
                if ($tn == T_INLINE_HTML) {
                    $new .= $ts;
                    $iw = false;
                } else {
                    if ($tn == T_OPEN_TAG) {
                        if (strpos($ts, " ") || strpos($ts, "\n") || strpos($ts, "\t") || strpos($ts, "\r")) {
                            $ts = rtrim($ts);
                        }
                        $ts .= " ";
                        $new .= $ts;
                        $ot = T_OPEN_TAG;
                        $iw = true;
                    } elseif ($tn == T_OPEN_TAG_WITH_ECHO) {
                        $new .= $ts;
                        $ot = T_OPEN_TAG_WITH_ECHO;
                        $iw = true;
                    } elseif ($tn == T_CLOSE_TAG) {
                        if ($ot == T_OPEN_TAG_WITH_ECHO) {
                            $new = rtrim($new, "; ");
                        } else {
                            $ts = " " . $ts;
                        }
                        $new .= $ts;
                        $ot = null;
                        $iw = false;
                    } elseif (in_array($tn, $IW)) {
                        $new .= $ts;
                        $iw = true;
                    } elseif ($tn == T_CONSTANT_ENCAPSED_STRING || $tn == T_ENCAPSED_AND_WHITESPACE) {
                        if ($ts[0] == '"') {
                            $ts = addcslashes($ts, "\n\t\r");
                        }
                        $new .= $ts;
                        $iw = true;
                    } elseif ($tn == T_WHITESPACE) {
                        $nt = @$tokens[$i + 1];
                        if (!$iw && (!is_string($nt) || $nt == '$') && !in_array($nt[0], $IW)) {
                            $new .= " ";
                        }
                        $iw = false;
                    } elseif ($tn == T_START_HEREDOC) {
                        $new .= "<<<S\n";
                        $iw = false;
                        $ih = true; // in HEREDOC
                    } elseif ($tn == T_END_HEREDOC) {
                        $new .= "S;";
                        $iw = true;
                        $ih = false; // in HEREDOC
                        for ($j = $i + 1; $j < $c; $j++) {
                            if (is_string($tokens[$j]) && $tokens[$j] == ";") {
                                $i = $j;
                                break;
                            } else if ($tokens[$j][0] == T_CLOSE_TAG) {
                                break;
                            }
                        }
                    } elseif ($tn == T_COMMENT || $tn == T_DOC_COMMENT) {
                        $iw = true;
                    } else {
                        /*
                         * Axel: DISABLE lowercase - it has bad impact on constants
                         * 
                          if (!$ih) {
                          $ts = strtolower($ts);
                          }
                         * 
                         */
                        $new .= $ts;
                        $iw = false;
                    }
                }
                $ls = "";
            } else {
                if (($token != ";" && $token != ":") || $ls != $token) {
                    $new .= $token;
                    $ls = $token;
                }
                $iw = true;
            }
        }
        return $new;
    }

    /**
     * check a given config array; if OK it returns all valid data.
     * It sends a die() if the config is wrong.
     * 
     * @param array  $aCfg
     * @return array
     */
    protected function _checkCfgfile($aCfg) {
        $sErrors = '';
        if (!is_array($aCfg)) {
            echo "ERROR: given config is an invalid json.\n";
            exit(3);
        }
        if (!array_key_exists("installer", $aCfg)) {
            $sErrors.="ERROR: wrong config - missing section [installer].\n";
        } else {
            foreach (array("product", "source", "installdir") as $sKey) {
                if (!array_key_exists($sKey, $aCfg['installer'])) {
                    $sErrors.="ERROR: wrong config - missing section [installer][$sKey].\n";
                }
            }
        }
        if ($sErrors) {
            echo $sErrors;
            exit(3);
        }
        return $aCfg;
    }

    /**
     * read data from a given project config file
     * 
     * @see getProjectFiles() to get all project files
     * 
     * @param string $sCfgfile  full path of a config file
     * @return array
     */
    protected function _getConfigFromFile($sCfgfile) {
        if (!file_exists($sCfgfile)) {
            echo "ERROR: given config does not exist.\n";
            exit(1);
        }
        $sCfg = file_get_contents($sCfgfile);
        $aCfg = $this->_checkCfgfile(json_decode($sCfg, true));
        if (!$aCfg) {
            echo "ERROR: config is invalid.\n";
            exit(2);
        }
        return $aCfg;
    }

    // ----------------------------------------------------------------------
    // GETTER
    // ----------------------------------------------------------------------

    /**
     * get a flat list of existing project config files
     * 
     * @return array
     */
    public function getProjectFiles() {
        $aReturn = array();

        foreach (glob($this->_sPrjDir . '*.json') as $sFilename) {
            $aReturn[] = $sFilename;
        }
        return $aReturn;
    }

    // ----------------------------------------------------------------------
    // GENERATE
    // ----------------------------------------------------------------------

    /**
     * (re)generate installer of a single project
     * 
     * @param string  $sCfgfile  config file
     * @return boolean
     */
    public function generate($sCfgfile) {
        echo "INFO: starting generator using $sCfgfile\n";

        $aCfg = $this->_getConfigFromFile($sCfgfile);
        $sCfgJsonOut = (defined('JSON_PRETTY_PRINT')) ? json_encode($aCfg['installer'], JSON_PRETTY_PRINT) : json_encode($aCfg['installer'])
        ;
        // print_r($aCfg);
        // generate the installer
        $sContent = '';
        $sContent.="<?php \n"
                . "// ----------------------------------------------------------------------\n"
                . "//\n"
                . "//   This is an installer for\n"
                . "//   " . $aCfg['installer']['product'] . "\n"
                . "//\n"
                . "//   (generated on " . date("Y-m-d H:i:s") . ")\n"
                . "//\n"
                . "// ----------------------------------------------------------------------\n"
                . "//   If you want to use this installer in your own projects\n"
                . "//   see " . $this->_sSourceUrl . "\n"
                . "// ----------------------------------------------------------------------\n"
                . "\n"
                . "// ----------------------------------------------------------------------\n"
                . "// CONFIG\n"
                . "// ----------------------------------------------------------------------\n"
                . "\n"
                . "global \$aCfg;\n"
                . "\$aCfg=json_decode(\n"
                . "'" . $sCfgJsonOut . "'"
                . ", true);\n"
                . "\n"
                . "?>\n"
                . $this->_compress_php_src(file_get_contents(__DIR__ . '/' . $this->_sInstallerClass))
                . "\n"
                . "\n"
                . "// ----------------------------------------------------------------------\n"
                . "// MAIN\n"
                . "// ----------------------------------------------------------------------\n"
                . "\n"
                . "\$oInstaller = new ahwi(\$aCfg);\n"
                . "\n"
                . "\$oInstaller->welcome();\n"
                . "\$oInstaller->download();\n"
                . "\$oInstaller->install();\n"
        // ."\$oInstaller->postinstall();\n"
        ;

        // write installer file to output dir
        $sOutFile = $this->_sOutDir
                . str_replace('.json', '', basename($sCfgfile))
                . '/installer.php'
        ;


        if (!is_dir(dirname($sOutFile))) {
            mkdir(dirname($sOutFile), 0750);
        }

        file_put_contents($sOutFile, $sContent);
        echo "DONE: installer file was generated: $sOutFile\n\n";
        return true;
    }

    /**
     * (re)generate all installers of all projects
     * 
     * @return boolean
     */
    public function generateAll() {
        echo "INFO: starting to generate all...\n\n";
        foreach ($this->getProjectFiles() as $sCfgfile) {
            $this->generate($sCfgfile);
        }
        echo "DONE: generate all...\n";
        return true;
    }

}
