<?php
$l = postavka('Udruga.Logo');
$dat = $l ? podaci('foto/' . basename($l)) : '';
if (!$l || !is_file($dat)) {
    http_response_code(404);
    exit;
}
posalji_datoteku($dat, 86400);
