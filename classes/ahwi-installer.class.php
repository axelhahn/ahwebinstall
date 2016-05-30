<?php

/*
 * AXEL HAHN's PHP WEB INSTALLER
 * www.axel-hahn.de
 * 
 * I N S T A L L E R
 * 
 * STATUS: alpha - do not use yet
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
        $this->iTimeStart = microtime(true);
        $this->_setConfig($aCfg);
        return true;
    }

    /**
     * make an http get request and return the response body
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
        // curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);

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
        foreach (array('product', 'source', 'installdir', 'tmpzip') as $sKey) {
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

    /**
     * download latest package of the product
     * @return bool
     */
    function download() {
        $sUrl = $this->aCfg['source'];
        $sZipfile = $this->aCfg['tmpzip'];

        if (file_exists($sZipfile)) {
            // unlink($sZipfile);
        }

        if (!file_exists($sZipfile)) {
            echo "INFO: fetching $sUrl ...\n";
            $sData = $this->_httpGet($sUrl);
            echo strlen($sData) . " byte\n";
            if (strlen($sData) < 100000) {
                die("FATAL ERROR: download failed.\n");
            }
            file_put_contents($sZipfile, $sData);
            echo "file was saved: $sZipfile\n";
        } else {
            echo "INFO: using existing $sZipfile (no download)\n";
        }

        return true;
    }

    function postinstall() {
        return true;
    }

    /**
     * install/ unzip
     */
    function install() {
        $sZipfile = $this->aCfg['tmpzip'];
        $sTargetPath = $this->aCfg['installdir'];
        $zip = new ZipArchive;
        if (is_dir($sTargetPath)) {
            echo "INFO: target directory already exists. Making an update.\n";
        }

        echo "INFO: extracting $sZipfile...\n";
        $res = $zip->open($sZipfile);
        if ($res === TRUE) {
            $zip->extractTo($sTargetPath);
            $zip->close();
            echo "SUCCESS: files were extracted to directory $sTargetPath.\n";
            // unlink($sZipfile);
        } else {
            die("ERROR: unable to open ZIP file\n");
        }
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
