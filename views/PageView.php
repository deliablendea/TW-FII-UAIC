<?php
class PageView {
    public function render($template, $data = []) {
        extract($data);
        ob_start();
        include "views/templates/{$template}.php";
        $content = ob_get_clean();
        echo $content;
    }
    
    public function partial($template, $data = []) {
        extract($data);
        include "views/templates/partials/{$template}.php";
    }
    
    public function css($file) {
        return "<link rel='stylesheet' href='public/css/{$file}.css'>";
    }
    
    public function js($file) {
        return "<script src='public/js/{$file}.js'></script>";
    }
    
    public function escape($string) {
        return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
    }
}
?> 