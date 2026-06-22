<?php
// Désactivé pour des raisons de sécurité (fuite d'informations personnelles)
header('HTTP/1.1 403 Forbidden');
header('Content-Type: application/json');
echo json_encode(["error" => "Access denied"]);
exit();
