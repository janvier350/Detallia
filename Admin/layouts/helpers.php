<?php
// Formatea una cantidad decimal quitando ceros y punto decimal innecesarios (1.00 -> 1, 1.50 -> 1.5)
function format_qty($value)
{
    $formatted = number_format((float) $value, 2, '.', '');
    if (strpos($formatted, '.') !== false) {
        $formatted = rtrim(rtrim($formatted, '0'), '.');
    }
    return $formatted === '' ? '0' : $formatted;
}
