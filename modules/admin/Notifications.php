<?php
// Shim سازگاری — کد واقعی به modules/notifications/admin.php منتقل شده (۹٫۹۹٫۲۲)
declare(strict_types=1);
if (!defined('CMS_ADMIN_PANEL')) { http_response_code(403); exit; }
require_once __DIR__ . '/../notifications/admin.php';
