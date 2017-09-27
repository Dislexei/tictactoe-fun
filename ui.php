<?php
class UI
{
    private $array = array();

    function __construct($textPath)
    {
        $file = fopen($textPath, "r");
        while ($line = fgetcsv($file))
        {
            $key = array_shift($line);
            $this->array[$key] = $line;
        }
    }

    public function yell($phrase)
    {
        //echo "Messenger called";
        return $this->array[$phrase][0];
    }

    public function cls()
    {
        passthru('clear');
    }

}
?>
