<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

/**  
 * @name dompdf_lib
 * @copyright O&C, 2011.
 * @author jez & Has Taiar
 */

require_once(APPPATH."libraries/dompdf/dompdf_config.inc.php");

class dompdf_lib extends DOMPDF {
    
    public $html;
    private $generated;
    function __construct() {
        parent::__construct();
        $this->generated = FALSE;
    }

    /**
     * generate a PDF from the provided html.
     */
    function generate(){
        if(!empty($this->html)){
            //$this->set_base_path("http://4x4.oandc.com.au/");
           // $this->html = $this->tidy($this->html);            
            $this->load_html($this->html);
            $this->render();               
            $this->generated = TRUE;
            return TRUE;
        }else{
            return FALSE;
        }        
    }        
    
    /**
     * Send the PDF to the browser as a binary attachment for downloading
     * @param string $filename The filename for the generated PDF
     * @return bool Success or failure
     * @author jez
     */
    function download($filename = 'document.pdf'){
        if($this->generated){
            header('Content-type: application/pdf');
            header('Pragma: public'); // required
            header('Expires: 0');
            header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
            header('Cache-Control: private',FALSE); // required for certain browsers
            header('Content-Disposition: attachment; filename="'.$filename.'"');		                
            header('Content-Transfer-Encoding: binary');		
            header('Content-Description: File Transfer');   
            header('Content-Transfer-Encoding: binary');
            header('Expires: 0');    
            header('Pragma: public');					               
            echo $this->output();
            return TRUE;
        }else{
            return FALSE;
        }
    }
    
    /**
     * Return the generated PDF as a string
     * @return string The PDF as a string
     * @author jez
     */
    function get_string(){
        return $this->output();
    }
    
    
    /**
     * Tidy the HTML for the PDF generation.
     * @param type $html
     * @param array $userConfig Configuration settings for Tidy. See the Tidy website for details.
     * @return string The Tidy'd HTML
     * @author The internets & jez
     */  
    function tidy($html, $userConfig = FALSE ) {       
        // default tidyConfig. Most of these are default settings.
        $config = array(
            'show-body-only' => false,
            'clean' => true,
            'char-encoding' => 'utf8',
            'add-xml-decl' => true,
            'add-xml-space' => true,
            'output-html' => false,
            'output-xml' => false,
            'output-xhtml' => true,
            'numeric-entities' => false,
            'ascii-chars' => false,
            'doctype' => 'strict',
            'bare' => true,
            'fix-uri' => true,
            'indent' => true,
            'indent-spaces' => 4,
            'tab-size' => 4,
            'wrap-attributes' => true,
            'wrap' => 0,
            'indent-attributes' => true,
            'join-classes' => false,
            'join-styles' => false,
            'enclose-block-text' => true,
            'fix-bad-comments' => true,
            'fix-backslash' => true,
            'replace-color' => false,
            'wrap-asp' => false,
            'wrap-jste' => false,
            'wrap-php' => false,
            'write-back' => true,
            'drop-proprietary-attributes' => false,
            'hide-comments' => false,
            'hide-endtags' => false,
            'literal-attributes' => false,
            'drop-empty-paras' => true,
            'enclose-text' => true,
            'quote-ampersand' => true,
            'quote-marks' => false,
            'quote-nbsp' => true,
            'vertical-space' => true,
            'wrap-script-literals' => false,
            'tidy-mark' => true,
            'merge-divs' => false,
            'repeated-attributes' => 'keep-last',
            'break-before-br' => true,
        );               
        
        if( is_array($userConfig) ) {
            $config = array_merge($config, $userConfig);           
        }

        $tidy = new tidy();
        $output = $tidy->repairString($html, $config, 'UTF8');        
        return($output);
    }

}

// end file dompdf_lib