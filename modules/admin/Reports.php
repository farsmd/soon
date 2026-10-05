<?php
// Shim سازگاری — کد واقعی به modules/reports/admin.php منتقل شده (۹٫۹۹٫۲۲)
declare(strict_types=1);
if (!defined('CMS_ADMIN_PANEL')) { http_response_code(403); exit; }
require_once __DIR__ . '/../reports/admin.php';
