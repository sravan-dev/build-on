<?php

// Simple TCPDF stub for expense reports
// This is a minimal implementation for PDF generation

if (!class_exists('TCPDF')) {
    class TCPDF {
        private $page_orientation = 'P';
        private $unit = 'mm';
        private $page_format = 'A4';
        private $header_data = '';
        private $header_font = ['helvetica', '', 10];
        private $footer_font = ['helvetica', '', 8];
        private $monospaced_font = 'courier';
        private $margins = ['left' => 15, 'top' => 27, 'right' => 15];
        private $header_margin = 5;
        private $footer_margin = 10;
        private $auto_page_break = true;
        private $bottom_margin = 25;
        private $image_scale = 1.25;
        private $current_page = 0;
        private $content = '';
        
        public function __construct($orientation = 'P', $unit = 'mm', $format = 'A4', $unicode = true, $encoding = 'UTF-8', $diskcache = false) {
            $this->page_orientation = $orientation;
            $this->unit = $unit;
            $this->page_format = $format;
        }
        
        public function SetCreator($creator) {}
        public function SetAuthor($author) {}
        public function SetTitle($title) {}
        public function SetSubject($subject) {}
        
        public function SetHeaderData($logo = '', $logo_width = 0, $title = '', $subtitle = '') {
            $this->header_data = $title;
        }
        
        public function setHeaderFont($font) {
            $this->header_font = $font;
        }
        
        public function setFooterFont($font) {
            $this->footer_font = $font;
        }
        
        public function SetDefaultMonospacedFont($font) {
            $this->monospaced_font = $font;
        }
        
        public function SetMargins($left, $top, $right) {
            $this->margins = ['left' => $left, 'top' => $top, 'right' => $right];
        }
        
        public function SetHeaderMargin($margin) {
            $this->header_margin = $margin;
        }
        
        public function SetFooterMargin($margin) {
            $this->footer_margin = $margin;
        }
        
        public function SetAutoPageBreak($auto, $margin = 0) {
            $this->auto_page_break = $auto;
            $this->bottom_margin = $margin;
        }
        
        public function setImageScale($scale) {
            $this->image_scale = $scale;
        }
        
        public function AddPage($orientation = '', $format = '', $keepmargins = false, $tocpage = false) {
            $this->current_page++;
        }
        
        public function writeHTML($html, $ln = true, $fill = false, $reseth = false, $cell = false, $align = '') {
            $this->content .= $html;
        }
        
        public function Output($name = 'doc.pdf', $dest = 'I') {
            // Simple HTML to PDF conversion
            $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Expense Report</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .header { text-align: center; margin-bottom: 20px; }
        .summary { margin: 20px 0; }
    </style>
</head>
<body>
    <div class="header">
        <h1>' . htmlspecialchars($this->header_data, ENT_QUOTES, 'UTF-8') . '</h1>
    </div>
    ' . $this->content . '
</body>
</html>';
            
            if ($dest === 'D') {
                header('Content-Type: application/pdf');
                header('Content-Disposition: attachment; filename="' . $name . '"');
                // For now, output as HTML - in production, use a proper PDF library
                echo $html;
            } else {
                echo $html;
            }
        }
    }
}

// Define constants
if (!defined('PDF_PAGE_ORIENTATION')) define('PDF_PAGE_ORIENTATION', 'P');
if (!defined('PDF_UNIT')) define('PDF_UNIT', 'mm');
if (!defined('PDF_PAGE_FORMAT')) define('PDF_PAGE_FORMAT', 'A4');
if (!defined('PDF_FONT_NAME_MAIN')) define('PDF_FONT_NAME_MAIN', 'helvetica');
if (!defined('PDF_FONT_SIZE_MAIN')) define('PDF_FONT_SIZE_MAIN', 10);
if (!defined('PDF_FONT_NAME_DATA')) define('PDF_FONT_NAME_DATA', 'helvetica');
if (!defined('PDF_FONT_SIZE_DATA')) define('PDF_FONT_SIZE_DATA', 8);
if (!defined('PDF_FONT_MONOSPACED')) define('PDF_FONT_MONOSPACED', 'courier');
if (!defined('PDF_MARGIN_LEFT')) define('PDF_MARGIN_LEFT', 15);
if (!defined('PDF_MARGIN_TOP')) define('PDF_MARGIN_TOP', 27);
if (!defined('PDF_MARGIN_RIGHT')) define('PDF_MARGIN_RIGHT', 15);
if (!defined('PDF_MARGIN_HEADER')) define('PDF_MARGIN_HEADER', 5);
if (!defined('PDF_MARGIN_FOOTER')) define('PDF_MARGIN_FOOTER', 10);
if (!defined('PDF_MARGIN_BOTTOM')) define('PDF_MARGIN_BOTTOM', 25);
if (!defined('PDF_IMAGE_SCALE_RATIO')) define('PDF_IMAGE_SCALE_RATIO', 1.25);

?>
