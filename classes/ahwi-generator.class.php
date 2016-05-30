<?php
/**
 * 
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
    var $sSourceClass = "ahwi-installer.class.php";
    var $sPrjDir = false;
    var $sOutDir = false;

    // ----------------------------------------------------------------------
    // METHODS
    // ----------------------------------------------------------------------
    
    /**
     * init ahwigenerator
     * @return boolean
     */
    public function __construct() {
        $this->sOutDir = str_replace('\\', '/', dirname(__DIR__) . '/output/');
        $this->sPrjDir = str_replace('\\', '/', dirname(__DIR__) . '/projects/');

        return true;
    }

    /**
     * get a flat list of existng project configs
     * 
     * @return array
     */
    public function getProjects() {
        $aReturn = array();

        foreach (glob($this->sPrjDir . '*.json') as $sFilename) {
            $aReturn[] = $sFilename;
        }
        return $aReturn;
    }

    /**
     * minify php string
     * SOURCE: http://php.net/manual/en/function.php-strip-whitespace.php
     * 
     * @staticvar array $IW
     * @param string $src  php code
     * @return string
     */
    private function _compress_php_src($src) {
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
                        if (!$ih) {
                            $ts = strtolower($ts);
                        }
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
     * (re)generate installer of a single project
     * 
     * @param string  $sCfgfile  config file
     * @return boolean
     */
    public function generate($sCfgfile) {
        echo "INFO: starting generator using $sCfgfile\n";
        if (!file_exists($sCfgfile)) {
            echo "ERROR: given config does not exist.\n";
            exit(1);
        }

        $sCfg = file_get_contents($sCfgfile);
        $aCfg = json_decode($sCfg, true);
        // print_r($aCfg);
        
        // generate the installer
        $sContent = '';
        $sContent.="<?php \n/*\n\n"
                . "    THIS IS A GENERATED PHP WEB INSTALLER \n"
                . "    FOR [" . $aCfg['product'] . "]\n"
                . "\n"
                // ."sourcefile: $sCfgfile\n"
                . "    generated on " . date("Y-m-d H:i:s") . "\n"
                . "*/ \n"
                . "?>\n"
                . $this->_compress_php_src(file_get_contents(__DIR__ . '/ahwi-installer.class.php'))
                . "\n"
                . "// ----------------------------------------------------------------------\n"
                . "// CONFIG\n"
                . "// ----------------------------------------------------------------------\n"
                ."\n"
                . "global \$aCfg;\n"
                ."\$aCfg=json_decode(\n"
                ."'" . $sCfg . "'\n"
                .", true);\n"
                ."\n"
                ."// ----------------------------------------------------------------------\n"
                ."// MAIN\n"
                ."// ----------------------------------------------------------------------\n"
                ."\n"
                ."\$oInstaller = new ahwi(\$aCfg);\n"
                ."\n"
                ."\$oInstaller->welcome();\n"
                ."\$oInstaller->download();\n"
                ."\$oInstaller->install();\n"
                // ."\$oInstaller->postinstall();\n"
                ;

        // write installer file to output dir
        $sOutFile = $this->sOutDir
                . str_replace('.json', '', basename($sCfgfile))
                . '/installer.php'
        ;


        if (!is_dir(dirname($sOutFile))) {
            mkdir(dirname($sOutFile), 0750);
        }

        file_put_contents($sOutFile, $sContent);
        echo "DONE: installer file was generated: $sOutFile\n";
        return true;
    }

    /**
     * (re)generate all installers of all projects
     * 
     * @return boolean
     */
    public function generateAll() {
        echo "INFO: starting to generate all...\n";
        foreach ($this->getProjects() as $sCfgfile) {
            $this->generate($sCfgfile);
        }
        echo "DONE: generate all...\n";
        return true;
    }

}
