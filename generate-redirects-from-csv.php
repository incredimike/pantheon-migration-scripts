<?php
$file     = $argv[1];

$row = 1;
if (($handle = fopen($file, "r")) === FALSE) {
    exit;
}

$basic_redirects = [];
$regex_redirects = [];

$last = '';

echo <<<PHP
<?php
namespace PantheonRedirects;


// Skip CLI entirely
if (php_sapi_name() == 'cli') {
    return;
}

function __redirect(\$url) {
    header('Location: ' . \$url, true, 301); exit;
}

\$address = rtrim(\$_SERVER['SERVER_NAME'] . '/' . \$_SERVER['REQUEST_URI'], '/');


PHP;


while (($data = fgetcsv($handle)) !== FALSE) {

    //var_dump($data);
    //die;

    list($from, $to, $type) = $data;

    // Skip if from or to entries are empty.
    if (empty($from) && empty($to)) {
        continue;
    }

    $to = 'https://' . $to;
    if ($type !== 'regex') {
        $type = 'simple';
    }

    if ($type === 'regex') {
        if ($last === 'simple') {
            echo output_basic_redirects();
        }

        echo output_regex_redirect($from, $to);

        //die;
    } else {
        add_basic_redirect($from, $to);
    }
    $last = $type;
}
fclose($handle);

echo output_basic_redirects();

//header('Location: ' . $redirect, true, 301);

//echo "\nheader('Location: ' . \$redirect, true, 301);\n";


echo "// " . report_memory_usage();




function add_basic_redirect($from, $to): void
{
    global $basic_redirects;
    //$basic_redirects[$to][] = $from;
    $basic_redirects[$from] = $to;
}

//function output_basic_redirects(): string
//{
//    global $basic_redirects;
//
//    $out = "switch (\$address):\n";
//    foreach ($basic_redirects as $to => $redirects) {
//        foreach ($redirects as $from) {
//            $out .= "   case '$from':\n";
//        }
//        //$out .= "       header('Location: $to', true, 301); exit;\n";
//        $out .= "       __redirect('$to');\n";
//    }
//
//    $out .= "endswitch;\n";
//    //echo $out;
//
//    return $out;
//}

function output_basic_redirects(): string
{
    global $basic_redirects;

    // var_dump( $basic_redirects );
    // die;

    $array = var_export($basic_redirects, true);

    $out = <<<OUT
\$redirects = $array;
if (array_key_exists(\$address, \$redirects)) {
    __redirect(\$redirects[\$address]);
}

OUT;

    $basic_redirects = [];

    return $out;
}


function output_regex_redirect($from, $to) {

    $from = trim($from);
    $to = trim($to);

    if (stripos($to, '*', -1) !== FALSE) {
        $to = rtrim($to, '*');
        $to = rtrim($to, '/');
        $to .= '';
        //$out_to = "'Location: $to/' . \$matches[1]";
        $out_to = "'$to/' . \$matches[1]";
    } else {
        //$out_to = "'Location: $to'";
        $out_to = "'$to'";
    }

    //header($out_to, true, 301); exit;

    $out = <<<OUT
if (preg_match('~^$from~', \$address, \$matches)) {
    __redirect($out_to);
};

OUT;

    return $out;
}

function convert($size)
{
    $unit=array('b','kb','mb','gb','tb','pb');
    return @round($size/pow(1024,($i=floor(log($size,1024)))),2).' '.$unit[$i];
}
function report_memory_usage()
{
    return "Memory Usage: " . convert(memory_get_usage(true)) . "\n";
}

