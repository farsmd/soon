<?php
// Shim سازگاری — کد واقعی به modules/orders/admin.php منتقل شده (۹٫۹۹٫۱۵)
declare(strict_types=1);
if (!defined('CMS_ADMIN_PANEL')) { http_response_code(403); exit; }
require_once __DIR__ . '/../orders/admin.php';
