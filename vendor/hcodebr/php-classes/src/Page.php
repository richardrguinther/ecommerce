<?php

namespace Hcode;

use Rain\Tpl;

class Page
{
    private $tpl;
    private $options = [];
    private $defaults = [
        "header" => true,
        "footer" => true,
        "data" => []
    ];

    public function __construct($opts = array(), $tpl_dir = "/views/")
    {
        // Caso seja definido, este bloco substituirá os defaults pelas options defininidas
        $this->options = array_merge($this->defaults, $opts);
        // End

        // Este bloco configura o TPL, à partir da root do website
        $config = array(
            "tpl_dir"       => $_SERVER["DOCUMENT_ROOT"] . $tpl_dir,
            "cache_dir"     => $_SERVER["DOCUMENT_ROOT"] . "/views-cache/",
            "debug"         => false
        );

        Tpl::configure($config);
        // End

        // Este bloco instancía o RainTPL e, caso as opções permitam, dá set no header
        $this->tpl = new Tpl;

        $this->setData($this->options["data"]);

        if ($this->options["header"] === true) $this->tpl->draw("header");
        // End
    }

    // Este método define a criação do RainTPL
    private function setData($data = array())
    {
        foreach ($data as $key => $value) {
            $this->tpl->assign($key, $value);
        }
    }
    // End

    // Este método faz com que o RainTPL renderize a página
    public function setTpl($name, $data = array(), $returnHTML = false)
    {
        $this->setData($data);

        return $this->tpl->draw($name, $returnHTML);
    }
    // End

    // Quando tudo estiver feito, este método fará com que o footer seja renderizado, caso as opções permitam
    public function __destruct()
    {
        if ($this->options["footer"] === true) $this->tpl->draw("footer");
    }
    // End
}
