<?php

/*
 * AXEL HAHN's PHP WEB INSTALLER
 * www.axel-hahn.de
 * 
 * I N S T A L L E R
 * 
 * STATUS: alpha - do not use yet
 * 
 * @author Axel Hahn
 */

/**
 * class ahwi
 * make download and install ...
 */
class ahwi {

    // ----------------------------------------------------------------------
    // INTERNAL CONFIG
    // ----------------------------------------------------------------------
    var $aCfg = array();
    var $iTimeStart = false;
    var $sAbout = "PHP WEB INSTALLER";

    // ----------------------------------------------------------------------
    // METHODS
    // ----------------------------------------------------------------------
    public function __construct($aCfg) {
        if (!function_exists("curl_init")) {
            die("ERROR: curl module is required for this installer. Please install php5-curl first.");
        }
        $this->iTimeStart = microtime(true);
        $this->_setConfig($aCfg);
        return true;
    }

    /**
     * make an http(s) get request and return the response body
     * @param string   $url          url to fetch
     * @param boolean  $bHeaderOnly  send header only
     * @return string
     */
    private function _httpGet($url, $bHeaderOnly = false) {
        $ch = curl_init($url);
        if ($bHeaderOnly) {
            curl_setopt($ch, CURLOPT_HEADER, 1);
            curl_setopt($ch, CURLOPT_NOBODY, 1);
        } else {
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_USERAGENT, 'php-curl :: web installer');
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

        $res = curl_exec($ch);
        curl_close($ch);
        return ($res);
    }

    /**
     * set config
     * @param array $aCfg  new project data
     * @return array
     */
    private function _setConfig($aCfg = array()) {
        // verify array
        $sErrors = '';
        foreach (array('product', 'source', 'installdir') as $sKey) {
            if (!array_key_exists($sKey, $aCfg)) {
                $sErrors.="ERROR: missing key $sKey ...\n";
            }
        }
        if ($sErrors) {
            echo $sErrors;
            die();
        }
        $this->aCfg = $aCfg;
        return $this->aCfg;
    }
    
    private function _getZipfilename() {
        $sZipfile=(getenv('temp') ? getenv('temp') : '/tmp')
                .'/'
                .str_replace(" ", "_", $this->aCfg['product'])
                .'__'
                .md5($this->aCfg['source'])
                .'.zip'
                ;
        return $sZipfile;
    }

    /**
     * download latest package of the product
     * @return bool
     */
    function download() {
        $sUrl = $this->aCfg['source'];
        // $sZipfile = $this->aCfg['tmpzip'];
        $sZipfile = $this->_getZipfilename();

        if (file_exists($sZipfile)) {
            // unlink($sZipfile);
        }

        if (!file_exists($sZipfile)) {
            echo "INFO: fetching $sUrl ...\n";
            $sData = $this->_httpGet($sUrl);
            echo strlen($sData) . " byte\n";
            if (strlen($sData) < 1000) {
                die("FATAL ERROR: download failed. The download file seems to be too small.\n");
            }
            file_put_contents($sZipfile, $sData);
            echo "file was saved: $sZipfile\n";
        } else {
            echo "INFO: using existing $sZipfile (no download)\n";
        }

        return true;
    }

    protected function _moveIfSingleSubdir($sSubdir, $aEntries) {
        $sTargetPath = $this->aCfg['installdir'];
        $sFirstDir = $sTargetPath . '/' . $sSubdir;

        // rsort($aEntries);
        $aErrors=array();
        echo "INFO: Copying entries from $sFirstDir to $sTargetPath.\n";
        foreach ($aEntries as $sEntry) {
            $sFrom = $sTargetPath . '/' . $sEntry;
            $sTo = str_replace($sTargetPath . '/'.$sSubdir.'/', $sTargetPath . '/', $sFrom);
            echo "... ";
            if (is_dir($sFrom)){
                echo "INFO: directory $sFrom";
                if (is_dir($sTo)){
                    echo " already exists.";
                } else {
                    if (mkdir($sTo, 0750, true)){
                        echo " $sTo was created.";
                    } else {
                        echo " FAILED to create $sTo.";
                        $aErrors[]="failed to create directory $sTo";
                    }
                }
            } else {
                echo (file_exists($sTo) ? 'UPDATE ' : 'CREATE ');
                if (copy($sFrom, $sTo)){
                    echo " $sTo was OK.";
                } else {
                    echo " FAILED to copy to $sTo.";
                    $aErrors[]="failed copy $sFrom to $sTo";
                }
            }
            echo "\n";
        }

        if (count($aErrors)){
            echo "ERRORS occured ... keeping subdir $sSubdir with all latest files.\n";
        } else {
            echo "INFO: Copy was successful. Now cleaning up dir $sSubdir ...\n";
            rsort($aEntries);
            foreach ($aEntries as $sEntry) {
                $sFrom = $sTargetPath . '/' . $sEntry;
                if (is_dir($sFrom)){
                    if (rmdir($sFrom)){
                        echo "... DELETED DIR $sFrom\n";
                    } else {
                        echo "... ERROR: DIR NOT DELETED $sFrom\n";
                        $aErrors[]="failed delete dir $sFrom";
                    }
                } else {
                    if (unlink($sFrom)){
                        echo "... DELETED $sFrom\n";
                    } else {
                        echo "... ERROR: NOT DELETED $sFrom\n";
                        $aErrors[]="failed delete $sFrom";
                    }
                }
            }
        }
        if (count($aErrors)){
            echo "ERRORS occured while deleting ... some entries in subdir $sSubdir still exist.\n";
        } else {
            echo "OK, cleanup was successful\n";
        }
    }
    /**
     * install/ unzip
     */
    function install() {
        // $sZipfile = $this->aCfg['tmpzip'];
        $sZipfile = $this->_getZipfilename();
        $sTargetPath = $this->aCfg['installdir'];
        $zip = new ZipArchive;
        if (is_dir($sTargetPath)) {
            echo "INFO: target directory already exists. Making an update.\n";
        }

        echo "INFO: extracting $sZipfile...\n";
        $res = $zip->open($sZipfile);
        if ($res === TRUE) {
            $zip->extractTo($sTargetPath);
            $aDirs=array();
            $aEntries=array();
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $sFirstDir=preg_replace('#[/\\\].*#', '', dirname($zip->getNameIndex($i).'x'));
                $aDirs[$sFirstDir]=1;
                $aEntries[]=$zip->getNameIndex($i);
                echo '... ' . $zip->getNameIndex($i) . " - $sFirstDir\n";
            }
            echo $zip->getStatusString() . "\n";
            echo $zip->numFiles . " entries are in the zip file.\n";
            $zip->close();
            echo "SUCCESS: files were extracted to directory \"$sTargetPath\".\n";

            // print_r(array_keys($aDirs));
            if(count(array_keys($aDirs))===1){
                $this->_moveIfSingleSubdir($sFirstDir, $aEntries);
            }
            
            // unlink($sZipfile);
        } else {
            die("ERROR: unable to open ZIP file\n");
        }
        if (array_key_exists('postmessage', $this->aCfg)) {
            echo $this->aCfg['postmessage'] . "\n";
        }
    }


    function postinstall() {
        return true;
    }


    /**
     * show welcome message
     */
    function welcome() {
        echo "
===== " . $this->sAbout . " [" . $this->aCfg['product'] . "] =====

What happens next:

--- Download of the files from
    " . $this->aCfg['source'] . "

--- " . $this->aCfg['product'] . " will be installed in directory
    " . $this->aCfg['installdir'] . "
    Current directory is 
    " . getcwd() . "

";
    }

}
